package com.localmesh.chat.core.security

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.PacketType
import com.localmesh.chat.core.protocol.Protocol

class PacketValidator(
    private val clock: () -> Long = { System.currentTimeMillis() },
) {
    fun validateStructure(packet: MeshPacket): ValidationResult {
        if (packet.version != Protocol.VERSION) {
            return ValidationResult.Reject("unsupported protocol version")
        }
        if (packet.ttl !in 1..Protocol.MAX_TTL) {
            return ValidationResult.Reject("ttl out of range")
        }
        if (packet.messageId.size != Protocol.MESSAGE_ID_LEN) {
            return ValidationResult.Reject("bad message id")
        }
        if (packet.payload.size > Protocol.MAX_PAYLOAD_BYTES) {
            return ValidationResult.Reject("payload too large")
        }
        val now = clock()
        val skew = kotlin.math.abs(now - packet.timestampMs)
        if (skew > Protocol.CLOCK_SKEW_MS) {
            return ValidationResult.Reject("expired or future timestamp")
        }
        if (packet.source == UserId.EMPTY) {
            return ValidationResult.Reject("empty source")
        }
        if (packet.type == PacketType.PRIVATE_MSG && packet.isGroupDestination) {
            return ValidationResult.Reject("private message missing destination")
        }
        return ValidationResult.Ok
    }
}

sealed class ValidationResult {
    data object Ok : ValidationResult()
    data class Reject(val reason: String) : ValidationResult()
}
