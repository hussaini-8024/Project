package com.localmesh.chat.core.routing

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.domain.connections.TransportKind

data class RouteResult(
    val duplicate: Boolean = false,
    val deliverLocal: Boolean = false,
    val forwardPacket: MeshPacket? = null,
    val nextHop: UserId? = null,
    val flood: Boolean = false,
    val exceptNeighbor: UserId? = null,
)

class MeshRouter(
    private val localUserId: () -> UserId,
    val table: RoutingTable,
    private val duplicates: DuplicateCache,
    private val diagnostics: MeshDiagnostics,
) {
    fun ingest(packet: MeshPacket, fromNeighbor: UserId?, incomingTransport: TransportKind?): RouteResult {
        diagnostics.packetsReceived.incrementAndGet()
        if (duplicates.remember(packet.messageId)) {
            diagnostics.duplicates.incrementAndGet()
            return RouteResult(duplicate = true)
        }
        if (packet.ttl <= 0) {
            diagnostics.packetsDropped.incrementAndGet()
            return RouteResult()
        }
        fromNeighbor?.let { neighbor ->
            table.upsert(
                RouteEntry(
                    peerId = neighbor,
                    nextHop = neighbor,
                    hopCount = 1,
                    transport = incomingTransport ?: TransportKind.NONE,
                    lastSeenMs = System.currentTimeMillis(),
                    connectionState = "connected",
                ),
            )
            if (packet.source != neighbor) {
                val hops = (Protocol.DEFAULT_TTL - packet.ttl + 1).coerceAtLeast(2)
                table.upsert(
                    RouteEntry(
                        peerId = packet.source,
                        nextHop = neighbor,
                        hopCount = hops,
                        transport = incomingTransport ?: TransportKind.NONE,
                        lastSeenMs = System.currentTimeMillis(),
                        connectionState = "relayed",
                    ),
                )
            }
        }

        val local = localUserId()
        val remaining = packet.ttl - 1
        val forwarded = if (remaining > 0) packet.withTtl(remaining) else null
        val forUs = packet.destination == local || packet.isGroupDestination

        if (packet.destination == local && !packet.isGroupDestination) {
            return RouteResult(deliverLocal = true)
        }
        if (packet.isGroupDestination) {
            return RouteResult(
                deliverLocal = true,
                forwardPacket = forwarded,
                flood = forwarded != null,
                exceptNeighbor = fromNeighbor,
            )
        }
        if (packet.destination != local) {
            if (forwarded == null) {
                diagnostics.packetsDropped.incrementAndGet()
                return RouteResult()
            }
            val route = table.lookup(packet.destination)
            return if (route != null && route.nextHop != fromNeighbor) {
                RouteResult(forwardPacket = forwarded, nextHop = route.nextHop)
            } else {
                RouteResult(
                    forwardPacket = forwarded,
                    flood = true,
                    exceptNeighbor = fromNeighbor,
                )
            }
        }
        return RouteResult(deliverLocal = forUs)
    }

    fun markOutgoing(messageId: ByteArray) {
        duplicates.remember(messageId)
    }

    fun noteDirect(peer: UserId, transport: TransportKind) {
        table.upsert(
            RouteEntry(
                peerId = peer,
                nextHop = peer,
                hopCount = 1,
                transport = transport,
                lastSeenMs = System.currentTimeMillis(),
                connectionState = "connected",
            ),
        )
    }

    fun lostDirect(peer: UserId) {
        table.remove(peer)
    }
}
