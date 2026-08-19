package com.localmesh.chat.core.routing

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.domain.connections.TransportKind

data class RouteEntry(
    val peerId: UserId,
    val nextHop: UserId,
    val hopCount: Int,
    val transport: TransportKind,
    val lastSeenMs: Long,
    val connectionState: String,
)

class RoutingTable(
    private val ttlMs: Long = Protocol.ROUTE_TTL_MS,
    private val clock: () -> Long = { System.currentTimeMillis() },
) {
    private val routes = LinkedHashMap<String, RouteEntry>()

    @Synchronized
    fun upsert(entry: RouteEntry) {
        val existing = routes[entry.peerId.hex]
        if (existing == null || entry.hopCount < existing.hopCount || entry.lastSeenMs >= existing.lastSeenMs) {
            if (existing != null && entry.hopCount > existing.hopCount && entry.nextHop != existing.nextHop) {
                if (entry.lastSeenMs - existing.lastSeenMs < 2_000L) return
            }
            routes[entry.peerId.hex] = entry
        }
    }

    @Synchronized
    fun lookup(peerId: UserId): RouteEntry? {
        expire()
        val entry = routes[peerId.hex] ?: return null
        if (clock() - entry.lastSeenMs > ttlMs) {
            routes.remove(peerId.hex)
            return null
        }
        return entry
    }

    @Synchronized
    fun remove(peerId: UserId) {
        routes.remove(peerId.hex)
        val stale = routes.filterValues { it.nextHop == peerId }.keys.toList()
        stale.forEach { routes.remove(it) }
    }

    @Synchronized
    fun snapshot(): List<RouteEntry> {
        expire()
        return routes.values.toList()
    }

    @Synchronized
    fun neighbors(): List<RouteEntry> = snapshot().filter { it.hopCount == 1 }

    private fun expire() {
        val now = clock()
        val dead = routes.filterValues { now - it.lastSeenMs > ttlMs }.keys.toList()
        dead.forEach { routes.remove(it) }
    }
}
