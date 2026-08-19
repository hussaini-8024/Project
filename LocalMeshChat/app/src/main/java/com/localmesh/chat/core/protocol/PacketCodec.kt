package com.localmesh.chat.core.protocol

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.hexToBytes
import com.localmesh.chat.core.crypto.toHex
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.io.DataInputStream
import java.io.DataOutputStream
import java.nio.ByteBuffer
import java.nio.ByteOrder

/**
 * Binary codec for LocalMesh packets.
 *
 * Wire format (little-endian integers, unsigned where noted):
 * magic u32 | version u8 | type u8 | flags u8 | ttl u8 |
 * messageId 16 | timestamp u64 | source 32 | dest 32 |
 * payloadLen u16 | payload | signatureLen u16 | signature
 *
 * The signed region is every byte before signatureLen.
 */
object PacketCodec {
    class CodecException(message: String) : Exception(message)

    fun encodeUnsigned(packet: MeshPacket): ByteArray {
        if (packet.payload.size > Protocol.MAX_PAYLOAD_BYTES) {
            throw CodecException("payload too large")
        }
        if (packet.messageId.size != Protocol.MESSAGE_ID_LEN) {
            throw CodecException("bad message id")
        }
        val body = signedRegion(packet)
        val out = ByteArrayOutputStream(body.size + 2 + packet.signature.size)
        out.write(body)
        writeU16(out, packet.signature.size)
        out.write(packet.signature)
        val bytes = out.toByteArray()
        if (bytes.size > Protocol.MAX_PACKET_BYTES) {
            throw CodecException("packet too large")
        }
        return bytes
    }

    fun decode(raw: ByteArray): MeshPacket {
        if (raw.size < Protocol.HEADER_BYTES + 2) {
            throw CodecException("truncated packet")
        }
        if (raw.size > Protocol.MAX_PACKET_BYTES) {
            throw CodecException("packet exceeds size limit")
        }
        val buf = ByteBuffer.wrap(raw).order(ByteOrder.LITTLE_ENDIAN)
        val magic = buf.int
        if (magic != Protocol.MAGIC) throw CodecException("bad magic")
        val version = buf.get().toInt() and 0xFF
        if (version != Protocol.VERSION) throw CodecException("unsupported version $version")
        val type = PacketType.fromCode(buf.get().toInt() and 0xFF)
            ?: throw CodecException("unknown type")
        val flags = buf.get().toInt() and 0xFF
        val ttl = buf.get().toInt() and 0xFF
        val messageId = ByteArray(Protocol.MESSAGE_ID_LEN)
        buf.get(messageId)
        val timestamp = buf.long
        val source = ByteArray(Protocol.USER_ID_LEN)
        buf.get(source)
        val dest = ByteArray(Protocol.USER_ID_LEN)
        buf.get(dest)
        val payloadLen = buf.short.toInt() and 0xFFFF
        if (payloadLen > Protocol.MAX_PAYLOAD_BYTES) throw CodecException("payload too large")
        if (buf.remaining() < payloadLen + 2) throw CodecException("truncated payload")
        val payload = ByteArray(payloadLen)
        buf.get(payload)
        val sigLen = buf.short.toInt() and 0xFFFF
        if (sigLen < 8 || sigLen > 256) throw CodecException("bad signature length")
        if (buf.remaining() != sigLen) throw CodecException("trailing bytes or truncated signature")
        val signature = ByteArray(sigLen)
        buf.get(signature)
        return MeshPacket(
            version = version,
            type = type,
            flags = flags,
            ttl = ttl,
            messageId = messageId,
            timestampMs = timestamp,
            source = UserId.fromBytes(source),
            destination = UserId.fromBytes(dest),
            payload = payload,
            signature = signature,
        )
    }

    fun signedRegion(packet: MeshPacket): ByteArray {
        val out = ByteArrayOutputStream(Protocol.HEADER_BYTES + packet.payload.size)
        val data = DataOutputStream(out)
        writeU32Le(data, Protocol.MAGIC.toLong() and 0xFFFFFFFFL)
        data.writeByte(packet.version)
        data.writeByte(packet.type.code)
        data.writeByte(packet.flags)
        data.writeByte(packet.ttl)
        data.write(packet.messageId)
        writeU64Le(data, packet.timestampMs)
        data.write(packet.source.bytes)
        data.write(packet.destination.bytes)
        writeU16Le(data, packet.payload.size)
        data.write(packet.payload)
        data.flush()
        return out.toByteArray()
    }

    fun encodeHello(payload: HelloPayload): ByteArray {
        require(payload.displayName.length <= Protocol.MAX_DISPLAY_NAME)
        val out = ByteArrayOutputStream()
        val d = DataOutputStream(out)
        writePrefixed(d, payload.displayName.toByteArray(Charsets.UTF_8), 1)
        writePrefixed(d, payload.identityPublicKey, 1)
        writePrefixed(d, payload.keyAgreementPublicKey, 1)
        writePrefixed(d, payload.ephemeralPublicKey, 1)
        d.writeByte(payload.capabilities)
        writeU64Le(d, payload.groupKeyEpoch)
        writePrefixed(d, payload.groupKeyId, 1)
        return out.toByteArray()
    }

    fun decodeHello(bytes: ByteArray): HelloPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        val name = readPrefixed(d, 1, Protocol.MAX_DISPLAY_NAME * 4).toString(Charsets.UTF_8)
        if (name.isBlank() || name.length > Protocol.MAX_DISPLAY_NAME) {
            throw CodecException("bad display name")
        }
        val idPub = readPrefixed(d, 1, 133)
        val kaPub = readPrefixed(d, 1, 133)
        val eph = readPrefixed(d, 1, 133)
        val caps = d.readUnsignedByte()
        val epoch = readU64Le(d)
        val keyId = readPrefixed(d, 1, 64)
        return HelloPayload(name, idPub, kaPub, eph, caps, epoch, keyId)
    }

    fun encodeChat(nonce: ByteArray, ciphertext: ByteArray): ByteArray {
        val out = ByteArrayOutputStream()
        val d = DataOutputStream(out)
        writePrefixed(d, nonce, 1)
        writePrefixed(d, ciphertext, 2)
        return out.toByteArray()
    }

    fun decodeChat(bytes: ByteArray): EncryptedChatPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        val nonce = readPrefixed(d, 1, 32)
        val ct = readPrefixed(d, 2, Protocol.MAX_PAYLOAD_BYTES)
        return EncryptedChatPayload(nonce, ct)
    }

    fun encodeControl(payload: ChatControlPayload): ByteArray {
        val out = ByteArrayOutputStream()
        val d = DataOutputStream(out)
        d.write(payload.requester.bytes)
        d.write(payload.target.bytes)
        writePrefixed(d, payload.requestId, 1)
        writePrefixed(d, payload.note.toByteArray(Charsets.UTF_8), 1)
        return out.toByteArray()
    }

    fun decodeControl(bytes: ByteArray): ChatControlPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        val requester = ByteArray(32)
        d.readFully(requester)
        val target = ByteArray(32)
        d.readFully(target)
        val reqId = readPrefixed(d, 1, 32)
        val note = readPrefixed(d, 1, 200).toString(Charsets.UTF_8)
        return ChatControlPayload(UserId.fromBytes(requester), UserId.fromBytes(target), reqId, note)
    }

    fun encodeKeyWrap(payload: GroupKeyWrapPayload): ByteArray {
        val out = ByteArrayOutputStream()
        val d = DataOutputStream(out)
        writeU64Le(d, payload.epoch)
        d.write(payload.origin.bytes)
        writePrefixed(d, payload.ephemeralPublicKey, 1)
        writePrefixed(d, payload.wrappedKey, 2)
        return out.toByteArray()
    }

    fun decodeKeyWrap(bytes: ByteArray): GroupKeyWrapPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        val epoch = readU64Le(d)
        val origin = ByteArray(32)
        d.readFully(origin)
        val eph = readPrefixed(d, 1, 133)
        val wrapped = readPrefixed(d, 2, 512)
        return GroupKeyWrapPayload(epoch, UserId.fromBytes(origin), wrapped, eph)
    }

    fun encodeAck(of: ByteArray): ByteArray {
        val out = ByteArrayOutputStream()
        writePrefixed(DataOutputStream(out), of, 1)
        return out.toByteArray()
    }

    fun decodeAck(bytes: ByteArray): AckPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        return AckPayload(readPrefixed(d, 1, 32))
    }

    fun encodeRoute(payload: RouteAdvertPayload): ByteArray {
        val out = ByteArrayOutputStream()
        val d = DataOutputStream(out)
        d.write(payload.peerId.bytes)
        d.writeByte(payload.hopCount)
        d.writeByte(payload.transports)
        return out.toByteArray()
    }

    fun decodeRoute(bytes: ByteArray): RouteAdvertPayload {
        val d = DataInputStream(ByteArrayInputStream(bytes))
        val id = ByteArray(32)
        d.readFully(id)
        val hops = d.readUnsignedByte()
        val transports = d.readUnsignedByte()
        return RouteAdvertPayload(UserId.fromBytes(id), hops, transports)
    }

    fun messageIdToHex(id: ByteArray): String = id.toHex()

    fun parseMessageId(hex: String): ByteArray = hex.hexToBytes()

    private fun writePrefixed(d: DataOutputStream, data: ByteArray, lenBytes: Int) {
        val len = data.size
        when (lenBytes) {
            1 -> {
                if (len > 255) throw CodecException("prefix overflow")
                d.writeByte(len)
            }
            2 -> writeU16Le(d, len)
            else -> throw CodecException("bad prefix")
        }
        d.write(data)
    }

    private fun readPrefixed(d: DataInputStream, lenBytes: Int, max: Int): ByteArray {
        val len = when (lenBytes) {
            1 -> d.readUnsignedByte()
            2 -> d.readUnsignedByte() or (d.readUnsignedByte() shl 8)
            else -> throw CodecException("bad prefix")
        }
        if (len > max) throw CodecException("prefixed field too large")
        val data = ByteArray(len)
        d.readFully(data)
        return data
    }

    private fun writeU16(out: ByteArrayOutputStream, value: Int) {
        out.write(value and 0xFF)
        out.write((value ushr 8) and 0xFF)
    }

    private fun writeU16Le(d: DataOutputStream, value: Int) {
        d.writeByte(value and 0xFF)
        d.writeByte((value ushr 8) and 0xFF)
    }

    private fun writeU32Le(d: DataOutputStream, value: Long) {
        d.writeByte((value and 0xFF).toInt())
        d.writeByte(((value ushr 8) and 0xFF).toInt())
        d.writeByte(((value ushr 16) and 0xFF).toInt())
        d.writeByte(((value ushr 24) and 0xFF).toInt())
    }

    private fun writeU64Le(d: DataOutputStream, value: Long) {
        var v = value
        repeat(8) {
            d.writeByte((v and 0xFF).toInt())
            v = v ushr 8
        }
    }

    private fun readU64Le(d: DataInputStream): Long {
        var v = 0L
        for (i in 0 until 8) {
            v = v or ((d.readUnsignedByte().toLong()) shl (8 * i))
        }
        return v
    }
}
