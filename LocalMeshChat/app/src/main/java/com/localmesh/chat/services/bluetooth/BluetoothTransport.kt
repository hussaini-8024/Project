package com.localmesh.chat.services.bluetooth

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothServerSocket
import android.bluetooth.BluetoothSocket
import android.bluetooth.le.AdvertiseCallback
import android.bluetooth.le.AdvertiseData
import android.bluetooth.le.AdvertiseSettings
import android.bluetooth.le.BluetoothLeAdvertiser
import android.bluetooth.le.ScanCallback
import android.bluetooth.le.ScanFilter
import android.bluetooth.le.ScanResult
import android.bluetooth.le.ScanSettings
import android.content.Context
import android.os.ParcelUuid
import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.UserId
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
import java.util.UUID
import java.util.concurrent.ConcurrentHashMap

@SuppressLint("MissingPermission")
class BluetoothTransport(
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
    override val kind: TransportKind = TransportKind.BLUETOOTH

    private val adapter: BluetoothAdapter? =
        (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val _connected = MutableStateFlow<Set<UserId>>(emptySet())
    override val connectedPeers: StateFlow<Set<UserId>> = _connected.asStateFlow()
    private val _available = MutableStateFlow(adapter != null)
    override val available: StateFlow<Boolean> = _available.asStateFlow()
    private val _enabled = MutableStateFlow(false)
    override val enabled: StateFlow<Boolean> = _enabled.asStateFlow()

    private val rfcommUuid: UUID = UUID.fromString(Protocol.RFCOMM_UUID)
    private val bleUuid: UUID = UUID.fromString(Protocol.BLE_SERVICE_UUID)

    private val sessions = ConcurrentHashMap<String, BtSession>()
    private val connectingMacs = ConcurrentHashMap.newKeySet<String>()
    private val mutex = Mutex()
    private var serverSocket: BluetoothServerSocket? = null
    private var advertiser: BluetoothLeAdvertiser? = null
    private var advertiseCallback: AdvertiseCallback? = null
    private var scanCallback: ScanCallback? = null
    private var jobs: List<Job> = emptyList()

    override suspend fun start() {
        stop()
        val bt = adapter
        if (bt == null || !bt.isEnabled) {
            _available.value = bt != null
            _enabled.value = false
            SecureLog.w("bluetooth unavailable or disabled")
            return
        }
        _available.value = true
        _enabled.value = true
        serverSocket = try {
            bt.listenUsingInsecureRfcommWithServiceRecord("LocalMeshChat", rfcommUuid)
        } catch (e: Exception) {
            SecureLog.w("rfcomm listen failed")
            null
        }
        jobs = listOf(
            scope.launch { acceptLoop() },
        )
        startBleAdvertise()
        startBleScan()
        SecureLog.i("bluetooth transport started")
    }

    override suspend fun stop() {
        _enabled.value = false
        jobs.forEach { it.cancel() }
        jobs = emptyList()
        stopBle()
        sessions.values.forEach { it.close() }
        sessions.clear()
        _connected.value = emptySet()
        try {
            serverSocket?.close()
        } catch (_: Exception) {
        }
        serverSocket = null
    }

    override suspend fun sendTo(peerId: UserId, rawPacket: ByteArray): Boolean {
        val session = sessions[peerId.hex] ?: return false
        return try {
            session.sendEncrypted(rawPacket)
            true
        } catch (_: Exception) {
            drop(peerId)
            false
        }
    }

    override suspend fun broadcast(rawPacket: ByteArray) {
        sessions.keys.toList().forEach { sendTo(UserId(it), rawPacket) }
    }

    override fun isDirect(peerId: UserId): Boolean = sessions.containsKey(peerId.hex)

    fun adapterEnabled(): Boolean = adapter?.isEnabled == true

    private suspend fun acceptLoop() {
        val server = serverSocket ?: return
        while (scope.isActive && _enabled.value) {
            try {
                val socket = server.accept()
                scope.launch { runSession(socket, initiator = false) }
            } catch (e: CancellationException) {
                throw e
            } catch (_: Exception) {
                if (_enabled.value) delay(500)
            }
        }
    }

    private suspend fun runSession(socket: BluetoothSocket, initiator: Boolean) {
        val framed = FramedIo(socket.inputStream, socket.outputStream)
        val eph = CryptoCore.generateEcKeyPair()
        try {
            if (!socket.isConnected) {
                socket.connect()
            }
            val result = if (initiator) {
                val hello = handshake.localHello(eph, capabilities(), groupEpoch(), groupKeyId())
                framed.writeFrame(PacketCodec.encodeUnsigned(hello))
                handshake.accept(
                    PacketCodec.decode(framed.readFrame()),
                    eph,
                    capabilities(),
                    groupEpoch(),
                    groupKeyId(),
                    sendAck = false,
                )
            } else {
                val accepted = handshake.accept(
                    PacketCodec.decode(framed.readFrame()),
                    eph,
                    capabilities(),
                    groupEpoch(),
                    groupKeyId(),
                    sendAck = true,
                )
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
                sessions[result.peerId.hex] = BtSession(result.peerId, framed, result.session, socket)
                _connected.value = sessions.keys.map { UserId(it) }.toSet()
            }
            onHandshake(result, TransportKind.BLUETOOTH)
            while (_enabled.value) {
                val frame = framed.readFrame()
                val plain = handshake.decrypt(result.session, frame)
                val packet = try {
                    PacketCodec.decode(plain)
                } catch (_: Exception) {
                    diagnostics.packetsDropped.incrementAndGet()
                    continue
                }
                onPacket(packet, result.peerId, TransportKind.BLUETOOTH)
            }
        } catch (e: CancellationException) {
            throw e
        } catch (_: Exception) {
            SecureLog.w("bluetooth session ended")
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

    private fun startBleAdvertise() {
        val adv = adapter?.bluetoothLeAdvertiser ?: return
        advertiser = adv
        val settings = AdvertiseSettings.Builder()
            .setAdvertiseMode(AdvertiseSettings.ADVERTISE_MODE_LOW_LATENCY)
            .setTxPowerLevel(AdvertiseSettings.ADVERTISE_TX_POWER_MEDIUM)
            .setConnectable(false)
            .setTimeout(0)
            .build()
        val userPrefix = identity.userId.bytes.copyOf(8)
        val data = AdvertiseData.Builder()
            .addServiceUuid(ParcelUuid(bleUuid))
            .addServiceData(ParcelUuid(bleUuid), userPrefix)
            .setIncludeDeviceName(false)
            .build()
        val callback = object : AdvertiseCallback() {
            override fun onStartSuccess(settingsInEffect: AdvertiseSettings) {
                SecureLog.i("ble advertise on")
            }
            override fun onStartFailure(errorCode: Int) {
                SecureLog.w("ble advertise failed")
            }
        }
        advertiseCallback = callback
        try {
            adv.startAdvertising(settings, data, callback)
        } catch (e: Exception) {
            SecureLog.w("ble advertise exception")
        }
    }

    private fun startBleScan() {
        val scanner = adapter?.bluetoothLeScanner ?: return
        val filter = ScanFilter.Builder().setServiceUuid(ParcelUuid(bleUuid)).build()
        val settings = ScanSettings.Builder()
            .setScanMode(ScanSettings.SCAN_MODE_LOW_LATENCY)
            .build()
        val callback = object : ScanCallback() {
            override fun onScanResult(callbackType: Int, result: ScanResult) {
                handleScan(result)
            }
            override fun onBatchScanResults(results: MutableList<ScanResult>) {
                results.forEach { handleScan(it) }
            }
            override fun onScanFailed(errorCode: Int) {
                SecureLog.w("ble scan failed")
            }
        }
        scanCallback = callback
        try {
            scanner.startScan(listOf(filter), settings, callback)
        } catch (e: Exception) {
            SecureLog.w("ble scan exception")
        }
    }

    private fun handleScan(result: ScanResult) {
        val record = result.scanRecord ?: return
        val data = record.getServiceData(ParcelUuid(bleUuid)) ?: return
        if (data.size >= 8 && data.copyOf(8).contentEquals(identity.userId.bytes.copyOf(8))) return
        connectDevice(result.device)
    }

    private fun connectDevice(device: BluetoothDevice) {
        val mac = device.address ?: return
        if (!connectingMacs.add(mac)) return
        if (sessions.values.any { it.socket.remoteDevice?.address == mac }) {
            connectingMacs.remove(mac)
            return
        }
        scope.launch {
            try {
                val socket = device.createInsecureRfcommSocketToServiceRecord(rfcommUuid)
                adapter?.cancelDiscovery()
                socket.connect()
                runSession(socket, initiator = true)
            } catch (_: Exception) {
            } finally {
                connectingMacs.remove(mac)
            }
        }
    }

    private fun stopBle() {
        try {
            advertiseCallback?.let { advertiser?.stopAdvertising(it) }
        } catch (_: Exception) {
        }
        try {
            scanCallback?.let { adapter?.bluetoothLeScanner?.stopScan(it) }
        } catch (_: Exception) {
        }
        advertiseCallback = null
        scanCallback = null
        advertiser = null
    }

    private class BtSession(
        val peerId: UserId,
        val framed: FramedIo,
        val session: LinkSession,
        val socket: BluetoothSocket,
    ) {
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
