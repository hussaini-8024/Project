package com.localmesh.chat.core.routing

import java.util.concurrent.atomic.AtomicLong

class MeshDiagnostics {
    val packetsSent = AtomicLong(0)
    val packetsReceived = AtomicLong(0)
    val packetsForwarded = AtomicLong(0)
    val packetsDropped = AtomicLong(0)
    val duplicates = AtomicLong(0)
    val handshakeFailures = AtomicLong(0)
    val decryptFailures = AtomicLong(0)

    fun snapshot(): DiagnosticsSnapshot = DiagnosticsSnapshot(
        packetsSent = packetsSent.get(),
        packetsReceived = packetsReceived.get(),
        packetsForwarded = packetsForwarded.get(),
        packetsDropped = packetsDropped.get(),
        duplicates = duplicates.get(),
        handshakeFailures = handshakeFailures.get(),
        decryptFailures = decryptFailures.get(),
    )

    fun reset() {
        packetsSent.set(0)
        packetsReceived.set(0)
        packetsForwarded.set(0)
        packetsDropped.set(0)
        duplicates.set(0)
        handshakeFailures.set(0)
        decryptFailures.set(0)
    }
}

data class DiagnosticsSnapshot(
    val packetsSent: Long,
    val packetsReceived: Long,
    val packetsForwarded: Long,
    val packetsDropped: Long,
    val duplicates: Long,
    val handshakeFailures: Long,
    val decryptFailures: Long,
)
