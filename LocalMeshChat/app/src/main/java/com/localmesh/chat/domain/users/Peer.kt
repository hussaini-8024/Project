package com.localmesh.chat.domain.users

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.domain.connections.ConnectionState
import com.localmesh.chat.domain.connections.TransportKind

data class Peer(
    val userId: UserId,
    val displayName: String,
    val identityPublicKey: ByteArray,
    val keyAgreementPublicKey: ByteArray,
    val transports: TransportKind,
    val connectionState: ConnectionState,
    val protocolVersion: Int,
    val lastSeenMs: Long,
    val hopCount: Int,
    val nextHop: UserId?,
    val viaWifi: Boolean,
    val viaBluetooth: Boolean,
) {
    val isDirect: Boolean get() = hopCount == 1

    fun transportLabel(): String = transports.label()

    override fun equals(other: Any?): Boolean = other is Peer && userId == other.userId
    override fun hashCode(): Int = userId.hashCode()
}
