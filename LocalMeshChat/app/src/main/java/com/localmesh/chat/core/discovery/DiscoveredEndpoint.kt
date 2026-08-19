package com.localmesh.chat.core.discovery

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.domain.connections.TransportKind

data class DiscoveredEndpoint(
    val userIdHint: UserId?,
    val transport: TransportKind,
    val address: String,
    val port: Int = 0,
    val bluetoothAddress: String? = null,
    val lastSeenMs: Long = System.currentTimeMillis(),
)
