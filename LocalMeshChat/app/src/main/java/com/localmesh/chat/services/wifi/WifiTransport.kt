package com.localmesh.chat.services.wifi

import android.content.Context
import android.net.nsd.NsdManager
import android.net.nsd.NsdServiceInfo
import android.net.wifi.WifiManager
import android.os.Build
import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.toHex
import com.localmesh.chat.core.networking.FramedIo
import com.localmesh.chat.core.networking.HandshakeHelper
import com.localmesh.chat.core.networking.LinkSession
import com.localmesh.chat.core.networking.MeshTransport
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.PacketCodec
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.core.routing.MeshDiagnostics
import com.localmesh.chat.domain.connections.TransportKind
import com.localmesh.chat.util.SecureLog
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.net.DatagramPacket
import java.net.DatagramSocket
import java.net.Inet4Address
import java.net.InetAddress
import java.net.InetSocketAddress
import java.net.MulticastSocket
import java.net.NetworkInterface
import java.net.ServerSocket
import java.net.Socket
import java.util.concurrent.ConcurrentHashMap

class WifiTransport(
    private val context: Context,
    private val identity: LocalIdentity,
    private val handshake: HandshakeHelper,
    private val diagnostics: MeshDiagnostics,
    private val capabilities: () -> Int,
    private val groupEpoch: () -> Long,
    private val groupKeyId: () -> ByteArray,
    private val onHandshake: (HandshakeHelper.Result, TransportKind) -> Unit,
    private val onPacket: suspend (MeshPacket, UserId, TransportKind) -> Unit,
    private val onLost: (UserId) -> Unit,
) : MeshTransport {
    override val kind: TransportKind = TransportKind.WIFI
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val _connected = MutableStateFlow<Set<UserId>>(emptySet())
    override val connectedPeers: StateFlow<Set<UserId>> = _connected.asStateFlow()
    private val _available = MutableStateFlow(true)
    override val available: StateFlow<Boolean> = _available.asStateFlow()
    private val _enabled = MutableStateFlow(false)
    override val enabled: StateFlow<Boolean> = _enabled.asStateFlow()

    private val sessions = ConcurrentHashMap<String, WifiSession>()
    private val connecting = ConcurrentHashMap.newKeySet<String>()
    private val mutex = Mutex()
    private var server: ServerSocket? = null
    private var multicast: MulticastSocket? = null
    private var multicastLock: WifiManager.MulticastLock? = null
    private var nsdManager: NsdManager? = null
    private var nsdRegistration: NsdManager.RegistrationListener? = null
    private var nsdDiscovery: NsdManager.DiscoveryListener? = null
    private var jobs: List<Job> = emptyList()

    override suspend fun start() {
        stop()
        _enabled.value = true
        _available.value = localIpv4() != null
        val serverSocket = ServerSocket().apply {
            reuseAddress = true
            bind(InetSocketAddress(Protocol.TCP_PORT))
        }
        server = serverSocket
        acquireMulticast()
        registerNsd()
        discoverNsd()
        jobs = listOf(
            scope.launch { acceptLoop(serverSocket) },
            scope.launch { beaconLoop() },
            scope.launch { listenBeacons() },
        )
        SecureLog.i("wifi transport listening on ${Protocol.TCP_PORT}")
    }

    override suspend fun stop() {
        _enabled.value = false
        jobs.forEach { it.cancel() }
        jobs = emptyList()
        sessions.values.forEach { it.close() }
        sessions.clear()
        _connected.value = emptySet()
        try {
            server?.close()
        } catch (_: Exception) {
        }
        server = null
        try {
            multicast?.leaveGroup(InetAddress.getByName(Protocol.MULTICAST_GROUP))
        } catch (_: Exception) {
        }
        multicast?.close()
        multicast = null
        multicastLock?.release()
        multicastLock = null
        unregisterNsd()
    }

    override suspend fun sendTo(peerId: UserId, rawPacket: ByteArray): Boolean {
        val session = sessions[peerId.hex] ?: return false
        return try {
            session.sendEncrypted(rawPacket)
            true
        } catch (e: Exception) {
            SecureLog.w("wifi send failed")
            drop(peerId)
            false
        }
    }

    override suspend fun broadcast(rawPacket: ByteArray) {
        sessions.keys.toList().forEach { key ->
            val peer = UserId(key)
            sendTo(peer, rawPacket)
        }
    }

    override fun isDirect(peerId: UserId): Boolean = sessions.containsKey(peerId.hex)

    fun hasLink(): Boolean = _enabled.value && server != null

    private suspend fun acceptLoop(serverSocket: ServerSocket) {
        while (scope.isActive && _enabled.value) {
            try {
                val socket = serverSocket.accept()
                socket.tcpNoDelay = true
                socket.soTimeout = 30_000
                scope.launch { runSession(socket, initiator = false) }
            } catch (e: CancellationException) {
                throw e
            } catch (e: Exception) {
                if (_enabled.value) delay(400)
            }
        }
    }

    private suspend fun runSession(socket: Socket, initiator: Boolean) {
        val remote = socket.remoteSocketAddress?.toString() ?: "unknown"
        val framed = FramedIo(socket.getInputStream(), socket.getOutputStream())
        val eph = CryptoCore.generateEcKeyPair()
        try {
            val result = if (initiator) {
                val hello = handshake.localHello(eph, capabilities(), groupEpoch(), groupKeyId())
                framed.writeFrame(PacketCodec.encodeUnsigned(hello))
                val ack = PacketCodec.decode(framed.readFrame())
                handshake.accept(ack, eph, capabilities(), groupEpoch(), groupKeyId(), sendAck = false)
            } else {
                val hello = PacketCodec.decode(framed.readFrame())
                val accepted = handshake.accept(hello, eph, capabilities(), groupEpoch(), groupKeyId(), sendAck = true)
                framed.writeFrame(PacketCodec.encodeUnsigned(accepted.ackPacket!!))
                accepted
            }
            if (result.peerId == identity.userId) {
                framed.closeQuietly()
                socket.close()
                return
            }
            mutex.withLock {
                sessions[result.peerId.hex]?.close()
                sessions[result.peerId.hex] = WifiSession(result.peerId, framed, result.session, socket)
                _connected.value = sessions.keys.map { UserId(it) }.toSet()
            }
            onHandshake(result, TransportKind.WIFI)
            while (_enabled.value) {
                val frame = framed.readFrame()
                val plain = handshake.decrypt(result.session, frame)
                val packet = try {
                    PacketCodec.decode(plain)
                } catch (_: Exception) {
                    diagnostics.packetsDropped.incrementAndGet()
                    continue
                }
                onPacket(packet, result.peerId, TransportKind.WIFI)
            }
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            SecureLog.w("wifi session ended")
        } finally {
            framed.closeQuietly()
            try {
                socket.close()
            } catch (_: Exception) {
            }
            val lost = sessions.entries.firstOrNull { it.value.socket === socket }?.key
            if (lost != null) drop(UserId(lost))
        }
    }

    private fun drop(peerId: UserId) {
        sessions.remove(peerId.hex)?.close()
        _connected.value = sessions.keys.map { UserId(it) }.toSet()
        onLost(peerId)
    }

    private suspend fun beaconLoop() {
        val group = InetAddress.getByName(Protocol.MULTICAST_GROUP)
        val socket = DatagramSocket()
        socket.broadcast = true
        while (scope.isActive && _enabled.value) {
            try {
                val payload = beaconBytes()
                socket.send(DatagramPacket(payload, payload.size, group, Protocol.UDP_BEACON_PORT))
            } catch (_: Exception) {
            }
            delay(Protocol.BEACON_INTERVAL_MS)
        }
        socket.close()
    }

    private suspend fun listenBeacons() {
        val socket = MulticastSocket(Protocol.UDP_BEACON_PORT)
        multicast = socket
        socket.reuseAddress = true
        try {
            socket.joinGroup(InetAddress.getByName(Protocol.MULTICAST_GROUP))
        } catch (e: Exception) {
            SecureLog.w("multicast join failed; NSD/unicast still used")
        }
        val buf = ByteArray(512)
        while (scope.isActive && _enabled.value) {
            try {
                val packet = DatagramPacket(buf, buf.size)
                socket.receive(packet)
                handleBeacon(packet)
            } catch (e: CancellationException) {
                throw e
            } catch (_: Exception) {
                delay(200)
            }
        }
    }

    private fun handleBeacon(packet: DatagramPacket) {
        if (packet.length < 4 + 1 + 32 + 2) return
        val data = packet.data.copyOf(packet.length)
        if (String(data, 0, 4, Charsets.US_ASCII) != "LMSH") return
        val version = data[4].toInt()
        if (version != Protocol.VERSION) return
        val userBytes = data.copyOfRange(5, 37)
        val peerId = UserId.fromBytes(userBytes)
        if (peerId == identity.userId) return
        if (sessions.containsKey(peerId.hex) || connecting.contains(peerId.hex)) return
        val port = (data[37].toInt() and 0xFF) or ((data[38].toInt() and 0xFF) shl 8)
        val ip = packet.address.hostAddress ?: return
        connectAsync(ip, port, peerId)
    }

    private fun beaconBytes(): ByteArray {
        val out = ByteArray(4 + 1 + 32 + 2)
        "LMSH".toByteArray(Charsets.US_ASCII).copyInto(out)
        out[4] = Protocol.VERSION.toByte()
        identity.userId.bytes.copyInto(out, 5)
        out[37] = (Protocol.TCP_PORT and 0xFF).toByte()
        out[38] = ((Protocol.TCP_PORT ushr 8) and 0xFF).toByte()
        return out
    }

    private fun connectAsync(ip: String, port: Int, hint: UserId?) {
        val key = "$ip:$port"
        if (!connecting.add(key)) return
        scope.launch {
            try {
                if (hint != null && sessions.containsKey(hint.hex)) return@launch
                if (isSelfAddress(ip)) return@launch
                val socket = Socket()
                socket.tcpNoDelay = true
                socket.connect(InetSocketAddress(ip, port), 4_000)
                socket.soTimeout = 30_000
                runSession(socket, initiator = true)
            } catch (_: Exception) {
            } finally {
                connecting.remove(key)
            }
        }
    }

    private fun isSelfAddress(ip: String): Boolean {
        return localAddresses().contains(ip)
    }

    private fun localIpv4(): String? =
        localAddresses().firstOrNull()

    private fun localAddresses(): Set<String> {
        val out = mutableSetOf<String>()
        try {
            NetworkInterface.getNetworkInterfaces()?.toList().orEmpty().forEach { nif ->
                if (!nif.isUp || nif.isLoopback) return@forEach
                nif.inetAddresses.toList().forEach { addr ->
                    if (addr is Inet4Address && !addr.isLoopbackAddress) {
                        addr.hostAddress?.let { out += it }
                    }
                }
            }
        } catch (_: Exception) {
        }
        return out
    }

    private fun acquireMulticast() {
        val wifi = context.applicationContext.getSystemService(Context.WIFI_SERVICE) as? WifiManager
        multicastLock = wifi?.createMulticastLock("localmesh")?.apply {
            setReferenceCounted(false)
            acquire()
        }
    }

    private fun registerNsd() {
        nsdManager = context.getSystemService(Context.NSD_SERVICE) as NsdManager
        val info = NsdServiceInfo().apply {
            serviceName = Protocol.NSD_SERVICE_NAME + "-" + identity.userId.shortLabel()
            serviceType = Protocol.NSD_SERVICE_TYPE
            port = Protocol.TCP_PORT
            if (Build.VERSION.SDK_INT >= 21) {
                setAttribute("uid", identity.userId.shortLabel())
            }
        }
        val listener = object : NsdManager.RegistrationListener {
            override fun onRegistrationFailed(serviceInfo: NsdServiceInfo, errorCode: Int) {
                SecureLog.w("nsd register failed")
            }
            override fun onUnregistrationFailed(serviceInfo: NsdServiceInfo, errorCode: Int) {}
            override fun onServiceRegistered(serviceInfo: NsdServiceInfo) {
                SecureLog.i("nsd registered")
            }
            override fun onServiceUnregistered(serviceInfo: NsdServiceInfo) {}
        }
        nsdRegistration = listener
        nsdManager?.registerService(info, NsdManager.PROTOCOL_DNS_SD, listener)
    }

    private fun discoverNsd() {
        val manager = nsdManager ?: return
        val discovery = object : NsdManager.DiscoveryListener {
            override fun onStartDiscoveryFailed(serviceType: String, errorCode: Int) {}
            override fun onStopDiscoveryFailed(serviceType: String, errorCode: Int) {}
            override fun onDiscoveryStarted(serviceType: String) {}
            override fun onDiscoveryStopped(serviceType: String) {}
            override fun onServiceFound(serviceInfo: NsdServiceInfo) {
                if (serviceInfo.serviceType.contains("localmesh") ||
                    serviceInfo.serviceName.contains("LocalMesh")
                ) {
                    if (serviceInfo.serviceName.contains(identity.userId.shortLabel())) return
                    manager.resolveService(serviceInfo, object : NsdManager.ResolveListener {
                        override fun onResolveFailed(serviceInfo: NsdServiceInfo, errorCode: Int) {}
                        override fun onServiceResolved(resolved: NsdServiceInfo) {
                            val host = if (Build.VERSION.SDK_INT >= 34) {
                                resolved.hostAddresses.firstOrNull()?.hostAddress
                            } else {
                                @Suppress("DEPRECATION")
                                resolved.host?.hostAddress
                            } ?: return
                            connectAsync(host, resolved.port, null)
                        }
                    })
                }
            }
            override fun onServiceLost(serviceInfo: NsdServiceInfo) {}
        }
        nsdDiscovery = discovery
        manager.discoverServices(Protocol.NSD_SERVICE_TYPE, NsdManager.PROTOCOL_DNS_SD, discovery)
    }

    private fun unregisterNsd() {
        try {
            nsdRegistration?.let { nsdManager?.unregisterService(it) }
        } catch (_: Exception) {
        }
        try {
            nsdDiscovery?.let { nsdManager?.stopServiceDiscovery(it) }
        } catch (_: Exception) {
        }
        nsdRegistration = null
        nsdDiscovery = null
    }

    private class WifiSession(
        val peerId: UserId,
        val framed: FramedIo,
        val session: LinkSession,
        val socket: Socket,
    ) {
        private val helper = this
        fun sendEncrypted(raw: ByteArray) {
            val (iv, ct) = CryptoCore.aesGcmEncrypt(session.sendKey, raw)
            framed.writeFrame(iv + ct)
        }
        fun close() {
            framed.closeQuietly()
            try {
                socket.close()
            } catch (_: Exception) {
            }
        }
    }
}
