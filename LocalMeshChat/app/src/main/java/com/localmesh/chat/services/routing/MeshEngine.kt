package com.localmesh.chat.services.routing

import android.content.Context
import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.GroupCrypto
import com.localmesh.chat.core.crypto.IdentityStore
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.PrivateSessionManager
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.hexToBytes
import com.localmesh.chat.core.crypto.randomBytes
import com.localmesh.chat.core.crypto.toHex
import com.localmesh.chat.core.networking.HandshakeHelper
import com.localmesh.chat.core.protocol.CapabilityFlags
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.PacketCodec
import com.localmesh.chat.core.protocol.PacketFactory
import com.localmesh.chat.core.protocol.PacketFlags
import com.localmesh.chat.core.protocol.PacketType
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.core.routing.DuplicateCache
import com.localmesh.chat.core.routing.MeshDiagnostics
import com.localmesh.chat.core.routing.MeshRouter
import com.localmesh.chat.core.routing.RoutingTable
import com.localmesh.chat.core.security.PacketValidator
import com.localmesh.chat.core.security.RateLimiter
import com.localmesh.chat.core.security.ValidationResult
import com.localmesh.chat.core.storage.AppSettings
import com.localmesh.chat.data.repositories.InMemorySessionStore
import com.localmesh.chat.domain.connections.ConnectionState
import com.localmesh.chat.domain.connections.LocalCapabilities
import com.localmesh.chat.domain.connections.TransportKind
import com.localmesh.chat.domain.messaging.ChatMessage
import com.localmesh.chat.domain.messaging.ChatRequest
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import com.localmesh.chat.domain.messaging.MessageDelivery
import com.localmesh.chat.domain.messaging.MessageScope
import com.localmesh.chat.domain.users.Peer
import com.localmesh.chat.services.bluetooth.BluetoothTransport
import com.localmesh.chat.services.wifi.WifiTransport
import com.localmesh.chat.util.SecureLog
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.security.PublicKey

class MeshEngine(
    context: Context,
    private val identityStore: IdentityStore,
    val identity: LocalIdentity,
    val store: InMemorySessionStore,
    val groupCrypto: GroupCrypto,
    val privateSessions: PrivateSessionManager,
    val diagnostics: MeshDiagnostics,
) {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val factory = PacketFactory(identityStore) { identity.userId }
    private val handshake = HandshakeHelper(identityStore, identity, factory)
    private val validator = PacketValidator()
    private val rateLimiter = RateLimiter()
    private val router = MeshRouter(
        localUserId = { identity.userId },
        table = RoutingTable(),
        duplicates = DuplicateCache(),
        diagnostics = diagnostics,
    )
    private val knownKeys = LinkedHashMap<String, PublicKey>()
    private val sendMutex = Mutex()

    private val _settings = MutableStateFlow(AppSettings())
    private val _capabilities = MutableStateFlow(
        LocalCapabilities(
            wifiAvailable = true,
            bluetoothAvailable = true,
            wifiEnabled = false,
            bluetoothEnabled = false,
            bridgeEnabled = true,
            actingAsBridge = false,
        ),
    )
    val capabilities: StateFlow<LocalCapabilities> = _capabilities.asStateFlow()
    val routingTable get() = router.table
    val stats get() = diagnostics

    private var wifi: WifiTransport? = null
    private var bluetooth: BluetoothTransport? = null

    private val incoming: suspend (MeshPacket, UserId, TransportKind) -> Unit =
        { packet, from, transport -> handleIncoming(packet, from, transport) }

    private val onHello: (HandshakeHelper.Result, TransportKind) -> Unit = { result, transport ->
        rememberPeer(result, transport)
        router.noteDirect(result.peerId, transport)
        maybeSendGroupKey(result)
        refreshBridgeFlag()
    }

    private val onLost: (UserId) -> Unit = { peer ->
        router.lostDirect(peer)
        val remainingWifi = wifi?.isDirect(peer) == true
        val remainingBt = bluetooth?.isDirect(peer) == true
        if (!remainingWifi && !remainingBt) {
            store.peer(peer)?.let { existing ->
                store.upsertPeer(
                    existing.copy(
                        connectionState = ConnectionState.DISCONNECTED,
                        viaWifi = false,
                        viaBluetooth = false,
                        transports = TransportKind.NONE,
                    ),
                )
            }
        }
        refreshBridgeFlag()
    }

    init {
        wifi = WifiTransport(
            context = context,
            identity = identity,
            handshake = handshake,
            diagnostics = diagnostics,
            capabilities = { localCapBits() },
            groupEpoch = { groupCrypto.current()?.epoch ?: 0L },
            groupKeyId = { groupCrypto.current()?.keyId ?: ByteArray(0) },
            onHandshake = onHello,
            onPacket = incoming,
            onLost = onLost,
        )
        bluetooth = BluetoothTransport(
            context = context,
            identity = identity,
            handshake = handshake,
            diagnostics = diagnostics,
            capabilities = { localCapBits() },
            groupEpoch = { groupCrypto.current()?.epoch ?: 0L },
            groupKeyId = { groupCrypto.current()?.keyId ?: ByteArray(0) },
            onHandshake = onHello,
            onPacket = incoming,
            onLost = onLost,
        )
        groupCrypto.ensureLocalKey(identity.userId)
        knownKeys[identity.userId.hex] = CryptoCore.decodeUncompressed(identity.identityPublicKey)
    }

    fun applySettings(settings: AppSettings) {
        _settings.value = settings
        refreshBridgeFlag()
    }

    suspend fun start(wifiEnabled: Boolean, bluetoothEnabled: Boolean) {
        if (wifiEnabled) {
            try {
                wifi?.start()
            } catch (e: Exception) {
                SecureLog.w("wifi start failed")
            }
        } else {
            wifi?.stop()
        }
        if (bluetoothEnabled) {
            try {
                bluetooth?.start()
            } catch (e: Exception) {
                SecureLog.w("bluetooth start failed")
            }
        } else {
            bluetooth?.stop()
        }
        refreshBridgeFlag()
    }

    suspend fun stop() {
        wifi?.stop()
        bluetooth?.stop()
        refreshBridgeFlag()
    }

    suspend fun sendGroupMessage(text: String) {
        val body = text.trim()
        if (body.isEmpty() || body.length > 2000) return
        val aad = identity.userId.bytes
        val blob = groupCrypto.encrypt(body.toByteArray(Charsets.UTF_8), aad)
        val payload = PacketCodec.encodeChat(blob.nonce, blob.ciphertext)
        val packet = factory.build(
            type = PacketType.GROUP_MSG,
            destination = UserId.GROUP,
            payload = payload,
            flags = PacketFlags.NEEDS_ACK,
        )
        deliverGroupLocal(packet, body, fromSelf = true)
        router.markOutgoing(packet.messageId)
        emit(packet)
    }

    suspend fun sendPrivateMessage(peerId: UserId, text: String) {
        if (!store.isPrivateOpen(identity.userId, peerId)) return
        val body = text.trim()
        if (body.isEmpty() || body.length > 2000) return
        val peer = store.peer(peerId) ?: return
        if (!privateSessions.hasSession(peerId)) {
            privateSessions.establish(peerId, CryptoCore.decodeUncompressed(peer.keyAgreementPublicKey))
        }
        val aad = (identity.userId.hex + peerId.hex).toByteArray()
        val (nonce, ct) = privateSessions.encrypt(peerId, body.toByteArray(Charsets.UTF_8), aad)
        val packet = factory.build(
            type = PacketType.PRIVATE_MSG,
            destination = peerId,
            payload = PacketCodec.encodeChat(nonce, ct),
            flags = PacketFlags.NEEDS_ACK,
        )
        store.addPrivate(
            peerId,
            ChatMessage(
                id = packet.messageId.toHex(),
                scope = MessageScope.PRIVATE,
                senderId = identity.userId,
                senderName = identity.displayName,
                conversationId = peerId.hex,
                body = body,
                timestampMs = packet.timestampMs,
                fromSelf = true,
            ),
        )
        router.markOutgoing(packet.messageId)
        emit(packet)
    }

    suspend fun sendChatRequest(peerId: UserId) {
        if (store.isPrivateOpen(identity.userId, peerId)) return
        val existing = store.requestBetween(identity.userId, peerId)
        if (existing?.status == ChatRequestStatus.OUTGOING || existing?.status == ChatRequestStatus.INCOMING) return
        val requestId = randomBytes(16)
        val payload = PacketCodec.encodeControl(
            com.localmesh.chat.core.protocol.ChatControlPayload(
                requester = identity.userId,
                target = peerId,
                requestId = requestId,
            ),
        )
        val packet = factory.build(PacketType.CHAT_REQUEST, peerId, payload, flags = PacketFlags.NEEDS_ACK)
        store.upsertRequest(
            ChatRequest(
                requestId = requestId.toHex(),
                from = identity.userId,
                fromName = identity.displayName,
                to = peerId,
                status = ChatRequestStatus.OUTGOING,
                timestampMs = packet.timestampMs,
            ),
        )
        router.markOutgoing(packet.messageId)
        emit(packet)
    }

    suspend fun respondToRequest(request: ChatRequest, accept: Boolean) {
        val type = if (accept) PacketType.CHAT_ACCEPT else PacketType.CHAT_REJECT
        val payload = PacketCodec.encodeControl(
            com.localmesh.chat.core.protocol.ChatControlPayload(
                requester = request.from,
                target = identity.userId,
                requestId = request.requestId.let {
                    try {
                        it.hexToBytes()
                    } catch (_: Exception) {
                        randomBytes(16)
                    }
                },
            ),
        )
        val packet = factory.build(type, request.from, payload)
        store.upsertRequest(
            request.copy(status = if (accept) ChatRequestStatus.ACCEPTED else ChatRequestStatus.REJECTED),
        )
        if (accept) {
            store.peer(request.from)?.let { peer ->
                privateSessions.establish(request.from, CryptoCore.decodeUncompressed(peer.keyAgreementPublicKey))
            }
        }
        router.markOutgoing(packet.messageId)
        emit(packet)
    }

    fun clearSessionData() {
        store.clearMessages()
        groupCrypto.clear()
        groupCrypto.ensureLocalKey(identity.userId)
        privateSessions.clear()
        diagnostics.reset()
    }

    private suspend fun handleIncoming(packet: MeshPacket, from: UserId, transport: TransportKind) {
        if (!rateLimiter.allow(from)) {
            diagnostics.packetsDropped.incrementAndGet()
            return
        }
        when (val result = validator.validateStructure(packet)) {
            is ValidationResult.Reject -> {
                diagnostics.packetsDropped.incrementAndGet()
                SecureLog.w("drop packet: ${result.reason}")
                return
            }
            ValidationResult.Ok -> Unit
        }
        if (!verifySignature(packet)) {
            diagnostics.packetsDropped.incrementAndGet()
            SecureLog.w("invalid signature")
            return
        }
        val decision = router.ingest(packet, from, transport)
        if (decision.duplicate) return
        if (decision.deliverLocal) {
            try {
                dispatchLocal(packet)
            } catch (e: Exception) {
                diagnostics.packetsDropped.incrementAndGet()
                SecureLog.w("dispatch failed")
            }
        }
        val forward = decision.forwardPacket
        if (forward != null && _settings.value.bridgeEnabled) {
            diagnostics.packetsForwarded.incrementAndGet()
            emit(forward, except = decision.exceptNeighbor)
        } else if (forward != null && decision.flood) {
            // Same-transport flood still happens so Wi-Fi-only meshes can be multi-hop.
            diagnostics.packetsForwarded.incrementAndGet()
            emitSameTransport(forward, transport, except = decision.exceptNeighbor)
        } else if (forward != null && decision.nextHop != null) {
            diagnostics.packetsForwarded.incrementAndGet()
            emitTo(decision.nextHop, forward)
        }
    }

    private fun verifySignature(packet: MeshPacket): Boolean {
        val known = knownKeys[packet.source.hex]
        if (known != null) {
            return CryptoCore.verify(known, PacketCodec.signedRegion(packet), packet.signature)
        }
        if (packet.type != PacketType.HELLO && packet.type != PacketType.HELLO_ACK) return false
        return try {
            val hello = PacketCodec.decodeHello(packet.payload)
            val pub = CryptoCore.decodeUncompressed(hello.identityPublicKey)
            val ok = CryptoCore.verify(pub, PacketCodec.signedRegion(packet), packet.signature)
            if (ok) knownKeys[packet.source.hex] = pub
            ok
        } catch (_: Exception) {
            false
        }
    }

    private suspend fun dispatchLocal(packet: MeshPacket) {
        when (packet.type) {
            PacketType.GROUP_MSG -> receiveGroup(packet)
            PacketType.PRIVATE_MSG -> receivePrivate(packet)
            PacketType.CHAT_REQUEST -> receiveRequest(packet)
            PacketType.CHAT_ACCEPT -> receiveAccept(packet)
            PacketType.CHAT_REJECT -> receiveReject(packet)
            PacketType.GROUP_KEY_WRAP -> receiveGroupKey(packet)
            PacketType.ACK -> Unit
            PacketType.PING -> {
                val pong = factory.build(PacketType.PONG, packet.source, packet.payload)
                emit(pong)
            }
            PacketType.PONG, PacketType.HELLO, PacketType.HELLO_ACK, PacketType.ROUTE_ADVERT, PacketType.PEER_GONE -> Unit
        }
        if (packet.flags and PacketFlags.NEEDS_ACK != 0 && packet.destination == identity.userId) {
            val ack = factory.build(PacketType.ACK, packet.source, PacketCodec.encodeAck(packet.messageId))
            emit(ack)
        }
    }

    private fun receiveGroup(packet: MeshPacket) {
        val enc = PacketCodec.decodeChat(packet.payload)
        val aad = packet.source.bytes
        val plain = groupCrypto.decrypt(GroupCrypto.EncryptedBlob(enc.nonce, enc.ciphertext), aad)
        if (plain == null) {
            diagnostics.decryptFailures.incrementAndGet()
            return
        }
        val text = plain.toString(Charsets.UTF_8)
        if (text.isEmpty()) return
        val name = store.peer(packet.source)?.displayName ?: packet.source.shortLabel()
        deliverGroupLocal(packet, text, fromSelf = packet.source == identity.userId, senderName = name)
    }

    private fun deliverGroupLocal(
        packet: MeshPacket,
        text: String,
        fromSelf: Boolean,
        senderName: String = identity.displayName,
    ) {
        store.addGroup(
            ChatMessage(
                id = packet.messageId.toHex(),
                scope = MessageScope.GROUP,
                senderId = packet.source,
                senderName = senderName,
                conversationId = Protocol.LOCAL_GROUP_ID,
                body = text,
                timestampMs = packet.timestampMs,
                fromSelf = fromSelf,
                delivery = MessageDelivery.SENT,
            ),
        )
    }

    private fun receivePrivate(packet: MeshPacket) {
        if (packet.destination != identity.userId) return
        if (!store.isPrivateOpen(identity.userId, packet.source)) return
        val enc = PacketCodec.decodeChat(packet.payload)
        if (!privateSessions.hasSession(packet.source)) {
            store.peer(packet.source)?.let {
                privateSessions.establish(packet.source, CryptoCore.decodeUncompressed(it.keyAgreementPublicKey))
            }
        }
        val aad = (packet.source.hex + identity.userId.hex).toByteArray()
        val plain = privateSessions.decrypt(packet.source, enc.nonce, enc.ciphertext, aad)
        if (plain == null) {
            diagnostics.decryptFailures.incrementAndGet()
            return
        }
        val text = plain.toString(Charsets.UTF_8)
        val name = store.peer(packet.source)?.displayName ?: packet.source.shortLabel()
        store.addPrivate(
            packet.source,
            ChatMessage(
                id = packet.messageId.toHex(),
                scope = MessageScope.PRIVATE,
                senderId = packet.source,
                senderName = name,
                conversationId = packet.source.hex,
                body = text,
                timestampMs = packet.timestampMs,
                fromSelf = false,
            ),
        )
    }

    private fun receiveRequest(packet: MeshPacket) {
        val control = PacketCodec.decodeControl(packet.payload)
        if (control.target != identity.userId) return
        val existing = store.requestBetween(control.requester, identity.userId)
        if (existing?.status == ChatRequestStatus.ACCEPTED) return
        val name = store.peer(control.requester)?.displayName ?: packet.source.shortLabel()
        store.upsertRequest(
            ChatRequest(
                requestId = control.requestId.toHex(),
                from = control.requester,
                fromName = name,
                to = identity.userId,
                status = ChatRequestStatus.INCOMING,
                timestampMs = packet.timestampMs,
            ),
        )
    }

    private fun receiveAccept(packet: MeshPacket) {
        val control = PacketCodec.decodeControl(packet.payload)
        val req = store.requestBetween(identity.userId, packet.source) ?: return
        store.upsertRequest(req.copy(status = ChatRequestStatus.ACCEPTED))
        store.peer(packet.source)?.let {
            privateSessions.establish(packet.source, CryptoCore.decodeUncompressed(it.keyAgreementPublicKey))
        }
    }

    private fun receiveReject(packet: MeshPacket) {
        val req = store.requestBetween(identity.userId, packet.source) ?: return
        store.upsertRequest(req.copy(status = ChatRequestStatus.REJECTED))
        privateSessions.drop(packet.source)
    }

    private fun receiveGroupKey(packet: MeshPacket) {
        val wrap = PacketCodec.decodeKeyWrap(packet.payload)
        val state = groupCrypto.unwrap(wrap.wrappedKey, identityStore.keyAgreementPrivate(), wrap.origin) ?: return
        groupCrypto.consider(state)
    }

    private fun rememberPeer(result: HandshakeHelper.Result, transport: TransportKind) {
        knownKeys[result.peerId.hex] = CryptoCore.decodeUncompressed(result.identityPublicKey)
        val wifiOn = transport == TransportKind.WIFI || wifi?.isDirect(result.peerId) == true
        val btOn = transport == TransportKind.BLUETOOTH || bluetooth?.isDirect(result.peerId) == true
        val capsWifi = result.capabilities and CapabilityFlags.WIFI != 0
        val capsBt = result.capabilities and CapabilityFlags.BLUETOOTH != 0
        val observed = TransportKind.fromFlags(wifiOn, btOn)
        val advertised = TransportKind.fromFlags(capsWifi, capsBt)
        store.upsertPeer(
            Peer(
                userId = result.peerId,
                displayName = result.displayName,
                identityPublicKey = result.identityPublicKey,
                keyAgreementPublicKey = result.keyAgreementPublicKey,
                transports = if (observed != TransportKind.NONE) observed else advertised,
                connectionState = ConnectionState.CONNECTED,
                protocolVersion = Protocol.VERSION,
                lastSeenMs = System.currentTimeMillis(),
                hopCount = 1,
                nextHop = result.peerId,
                viaWifi = wifiOn,
                viaBluetooth = btOn,
            ),
        )
    }

    private fun maybeSendGroupKey(result: HandshakeHelper.Result) {
        scope.launch {
            try {
                val peerPub = CryptoCore.decodeUncompressed(result.keyAgreementPublicKey)
                val (state, packed) = groupCrypto.wrapFor(peerPub, identity.userId)
                val payload = PacketCodec.encodeKeyWrap(
                    com.localmesh.chat.core.protocol.GroupKeyWrapPayload(
                        epoch = state.epoch,
                        origin = identity.userId,
                        wrappedKey = packed,
                        ephemeralPublicKey = ByteArray(0),
                    ),
                )
                val packet = factory.build(PacketType.GROUP_KEY_WRAP, result.peerId, payload)
                emitTo(result.peerId, packet)
            } catch (e: Exception) {
                SecureLog.w("group key wrap failed")
            }
        }
    }

    private suspend fun emit(packet: MeshPacket, except: UserId? = null) {
        sendMutex.withLock {
            val raw = PacketCodec.encodeUnsigned(packet)
            diagnostics.packetsSent.incrementAndGet()
            wifi?.connectedPeers?.value?.forEach { peer ->
                if (peer != except) wifi?.sendTo(peer, raw)
            }
            bluetooth?.connectedPeers?.value?.forEach { peer ->
                if (peer != except) bluetooth?.sendTo(peer, raw)
            }
        }
    }

    private suspend fun emitSameTransport(packet: MeshPacket, transport: TransportKind, except: UserId?) {
        val raw = PacketCodec.encodeUnsigned(packet)
        diagnostics.packetsSent.incrementAndGet()
        val target = if (transport == TransportKind.WIFI) wifi else bluetooth
        target?.connectedPeers?.value?.forEach { peer ->
            if (peer != except) target.sendTo(peer, raw)
        }
    }

    private suspend fun emitTo(peer: UserId, packet: MeshPacket) {
        val raw = PacketCodec.encodeUnsigned(packet)
        diagnostics.packetsSent.incrementAndGet()
        if (wifi?.sendTo(peer, raw) == true) return
        if (bluetooth?.sendTo(peer, raw) == true) return
        emit(packet)
    }

    private fun localCapBits(): Int {
        val wifiOn = wifi?.enabled?.value == true
        val btOn = bluetooth?.enabled?.value == true
        return HandshakeHelper.capabilityBits(wifiOn, btOn, _settings.value.bridgeEnabled && wifiOn && btOn)
    }

    private fun refreshBridgeFlag() {
        val wifiOn = wifi?.enabled?.value == true && (wifi?.connectedPeers?.value?.isNotEmpty() == true || wifi?.hasLink() == true)
        val btOn = bluetooth?.enabled?.value == true
        val wifiPeers = wifi?.connectedPeers?.value?.isNotEmpty() == true
        val btPeers = bluetooth?.connectedPeers?.value?.isNotEmpty() == true
        val bridge = _settings.value.bridgeEnabled && wifiOn && btOn && wifiPeers && btPeers
        _capabilities.value = LocalCapabilities(
            wifiAvailable = true,
            bluetoothAvailable = bluetooth?.available?.value != false,
            wifiEnabled = wifi?.enabled?.value == true,
            bluetoothEnabled = bluetooth?.enabled?.value == true,
            bridgeEnabled = _settings.value.bridgeEnabled,
            actingAsBridge = bridge,
        )
    }
}
