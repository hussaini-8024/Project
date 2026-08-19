package com.localmesh.chat.core.protocol

import com.localmesh.chat.core.crypto.UserId

data class MeshPacket(
    val version: Int,
    val type: PacketType,
    val flags: Int,
    val ttl: Int,
    val messageId: ByteArray,
    val timestampMs: Long,
    val source: UserId,
    val destination: UserId,
    val payload: ByteArray,
    val signature: ByteArray,
) {
    val isGroupDestination: Boolean
        get() = destination.bytes.contentEquals(Protocol.GROUP_BROADCAST)

    fun withTtl(newTtl: Int): MeshPacket = copy(ttl = newTtl, flags = flags or PacketFlags.FORWARDED)

    override fun equals(other: Any?): Boolean {
        if (this === other) return true
        if (other !is MeshPacket) return false
        return messageId.contentEquals(other.messageId)
    }

    override fun hashCode(): Int = messageId.contentHashCode()
}

data class HelloPayload(
    val displayName: String,
    val identityPublicKey: ByteArray,
    val keyAgreementPublicKey: ByteArray,
    val ephemeralPublicKey: ByteArray,
    val capabilities: Int,
    val groupKeyEpoch: Long,
    val groupKeyId: ByteArray,
) {
    override fun equals(other: Any?): Boolean = other is HelloPayload &&
        displayName == other.displayName &&
        identityPublicKey.contentEquals(other.identityPublicKey) &&
        keyAgreementPublicKey.contentEquals(other.keyAgreementPublicKey) &&
        ephemeralPublicKey.contentEquals(other.ephemeralPublicKey) &&
        capabilities == other.capabilities &&
        groupKeyEpoch == other.groupKeyEpoch &&
        groupKeyId.contentEquals(other.groupKeyId)

    override fun hashCode(): Int = displayName.hashCode()
}

data class EncryptedChatPayload(
    val nonce: ByteArray,
    val ciphertext: ByteArray,
)

data class ChatControlPayload(
    val requester: UserId,
    val target: UserId,
    val requestId: ByteArray,
    val note: String = "",
)

data class GroupKeyWrapPayload(
    val epoch: Long,
    val origin: UserId,
    val wrappedKey: ByteArray,
    val ephemeralPublicKey: ByteArray,
)

data class AckPayload(
    val ofMessageId: ByteArray,
)

data class RouteAdvertPayload(
    val peerId: UserId,
    val hopCount: Int,
    val transports: Int,
)
