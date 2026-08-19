package com.localmesh.chat.core.networking

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.domain.connections.TransportKind
import kotlinx.coroutines.flow.StateFlow
import java.io.DataInputStream
import java.io.DataOutputStream
import java.io.IOException
import java.io.InputStream
import java.io.OutputStream
import java.nio.ByteBuffer
import java.nio.ByteOrder

data class NeighborLink(
    val peerId: UserId,
    val transport: TransportKind,
    val remoteAddress: String,
    val send: suspend (ByteArray) -> Unit,
)

interface MeshTransport {
    val kind: TransportKind
    val connectedPeers: StateFlow<Set<UserId>>
    val available: StateFlow<Boolean>
    val enabled: StateFlow<Boolean>
    suspend fun start()
    suspend fun stop()
    suspend fun sendTo(peerId: UserId, rawPacket: ByteArray): Boolean
    suspend fun broadcast(rawPacket: ByteArray)
    fun isDirect(peerId: UserId): Boolean
}

class FramedIo(
    input: InputStream,
    output: OutputStream,
) {
    private val dataIn = DataInputStream(input)
    private val dataOut = DataOutputStream(output)
    private val writeLock = Any()

    fun writeFrame(bytes: ByteArray) {
        if (bytes.size > Protocol.MAX_PACKET_BYTES) throw IOException("frame too large")
        val header = ByteBuffer.allocate(4).order(ByteOrder.LITTLE_ENDIAN).putInt(bytes.size).array()
        synchronized(writeLock) {
            dataOut.write(header)
            dataOut.write(bytes)
            dataOut.flush()
        }
    }

    fun readFrame(): ByteArray {
        val len = readIntLe()
        if (len <= 0 || len > Protocol.MAX_PACKET_BYTES) throw IOException("invalid frame length $len")
        val body = ByteArray(len)
        dataIn.readFully(body)
        return body
    }

    private fun readIntLe(): Int {
        val b0 = dataIn.readUnsignedByte()
        val b1 = dataIn.readUnsignedByte()
        val b2 = dataIn.readUnsignedByte()
        val b3 = dataIn.readUnsignedByte()
        return b0 or (b1 shl 8) or (b2 shl 16) or (b3 shl 24)
    }

    fun closeQuietly() {
        try {
            dataIn.close()
        } catch (_: Exception) {
        }
        try {
            dataOut.close()
        } catch (_: Exception) {
        }
    }
}

data class LinkSession(
    val sendKey: ByteArray,
    val recvKey: ByteArray,
)
