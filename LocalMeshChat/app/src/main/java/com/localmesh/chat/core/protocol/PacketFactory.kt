package com.localmesh.chat.core.protocol

import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.IdentityStore
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.randomBytes

class PacketFactory(
    private val identityStore: IdentityStore,
    private val localUserId: () -> UserId,
) {
    fun build(
        type: PacketType,
        destination: UserId,
        payload: ByteArray,
        ttl: Int = Protocol.DEFAULT_TTL,
        flags: Int = 0,
        timestampMs: Long = System.currentTimeMillis(),
        messageId: ByteArray = randomBytes(Protocol.MESSAGE_ID_LEN),
    ): MeshPacket {
        val unsigned = MeshPacket(
            version = Protocol.VERSION,
            type = type,
            flags = flags,
            ttl = ttl,
            messageId = messageId,
            timestampMs = timestampMs,
            source = localUserId(),
            destination = destination,
            payload = payload,
            signature = ByteArray(0),
        )
        val region = PacketCodec.signedRegion(unsigned)
        val signature = identityStore.sign(region)
        return unsigned.copy(signature = signature)
    }
}
