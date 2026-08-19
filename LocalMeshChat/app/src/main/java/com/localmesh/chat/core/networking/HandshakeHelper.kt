package com.localmesh.chat.core.networking

import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.IdentityStore
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.sha256
import com.localmesh.chat.core.crypto.toHex
import com.localmesh.chat.core.protocol.CapabilityFlags
import com.localmesh.chat.core.protocol.HelloPayload
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.PacketCodec
import com.localmesh.chat.core.protocol.PacketFactory
import com.localmesh.chat.core.protocol.PacketType
import com.localmesh.chat.core.protocol.Protocol
import java.security.KeyPair
import java.security.PublicKey

class HandshakeHelper(
    private val identityStore: IdentityStore,
    private val identity: LocalIdentity,
    private val packetFactory: PacketFactory,
) {
    data class Result(
        val peerId: UserId,
        val displayName: String,
        val identityPublicKey: ByteArray,
        val keyAgreementPublicKey: ByteArray,
        val capabilities: Int,
        val groupKeyEpoch: Long,
        val groupKeyId: ByteArray,
        val session: LinkSession,
        val ackPacket: MeshPacket?,
    )

    fun localHello(
        eph: KeyPair,
        capabilities: Int,
        groupEpoch: Long,
        groupKeyId: ByteArray,
        type: PacketType = PacketType.HELLO,
    ): MeshPacket {
        val payload = PacketCodec.encodeHello(
            HelloPayload(
                displayName = identity.displayName,
                identityPublicKey = identity.identityPublicKey,
                keyAgreementPublicKey = identity.keyAgreementPublicKey,
                ephemeralPublicKey = CryptoCore.encodeUncompressed(eph.public),
                capabilities = capabilities,
                groupKeyEpoch = groupEpoch,
                groupKeyId = groupKeyId,
            ),
        )
        return packetFactory.build(type, UserId.GROUP, payload)
    }

    fun accept(
        incoming: MeshPacket,
        localEph: KeyPair,
        capabilities: Int,
        groupEpoch: Long,
        groupKeyId: ByteArray,
        sendAck: Boolean,
    ): Result {
        if (incoming.type != PacketType.HELLO && incoming.type != PacketType.HELLO_ACK) {
            throw HandshakeException("expected hello")
        }
        val hello = PacketCodec.decodeHello(incoming.payload)
        val claimedId = UserId.fromIdentityPublicKey(hello.identityPublicKey)
        if (claimedId != incoming.source) throw HandshakeException("user id mismatch")
        val idPub: PublicKey = CryptoCore.decodeUncompressed(hello.identityPublicKey)
        val region = PacketCodec.signedRegion(incoming)
        if (!CryptoCore.verify(idPub, region, incoming.signature)) {
            throw HandshakeException("bad hello signature")
        }
        val peerEph = CryptoCore.decodeUncompressed(hello.ephemeralPublicKey)
        val shared = CryptoCore.ecdh(localEph.private, peerEph)
        val localEphBytes = CryptoCore.encodeUncompressed(localEph.public)
        val idParts = listOf(hello.identityPublicKey, identity.identityPublicKey).sortedBy { it.toHex() }
        val ephParts = listOf(hello.ephemeralPublicKey, localEphBytes).sortedBy { it.toHex() }
        val transcript = sha256(idParts[0] + idParts[1] + ephParts[0] + ephParts[1])
        val okm = CryptoCore.hkdf(shared, transcript, "localmesh-link-v1".toByteArray(), 64)
        val k1 = okm.copyOfRange(0, 32)
        val k2 = okm.copyOfRange(32, 64)
        val sendFirst = identity.userId.hex < claimedId.hex
        val session = if (sendFirst) LinkSession(k1, k2) else LinkSession(k2, k1)
        val ack = if (sendAck) {
            localHello(localEph, capabilities, groupEpoch, groupKeyId, PacketType.HELLO_ACK)
        } else {
            null
        }
        return Result(
            peerId = claimedId,
            displayName = hello.displayName,
            identityPublicKey = hello.identityPublicKey,
            keyAgreementPublicKey = hello.keyAgreementPublicKey,
            capabilities = hello.capabilities,
            groupKeyEpoch = hello.groupKeyEpoch,
            groupKeyId = hello.groupKeyId,
            session = session,
            ackPacket = ack,
        )
    }

    fun encrypt(session: LinkSession, packet: ByteArray): ByteArray {
        val (iv, ct) = CryptoCore.aesGcmEncrypt(session.sendKey, packet)
        return iv + ct
    }

    fun decrypt(session: LinkSession, frame: ByteArray): ByteArray {
        if (frame.size < CryptoCore.AES_GCM_IV_BYTES + 16) throw HandshakeException("short frame")
        val iv = frame.copyOfRange(0, CryptoCore.AES_GCM_IV_BYTES)
        val ct = frame.copyOfRange(CryptoCore.AES_GCM_IV_BYTES, frame.size)
        return CryptoCore.aesGcmDecrypt(session.recvKey, iv, ct)
    }

    companion object {
        fun capabilityBits(wifi: Boolean, bluetooth: Boolean, bridge: Boolean): Int {
            var bits = 0
            if (wifi) bits = bits or CapabilityFlags.WIFI
            if (bluetooth) bits = bits or CapabilityFlags.BLUETOOTH
            if (bridge) bits = bits or CapabilityFlags.BRIDGE
            return bits
        }
    }
}

class HandshakeException(message: String) : Exception(message)
