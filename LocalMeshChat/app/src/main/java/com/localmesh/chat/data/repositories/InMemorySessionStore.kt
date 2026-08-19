package com.localmesh.chat.data.repositories

import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.domain.messaging.ChatMessage
import com.localmesh.chat.domain.messaging.ChatRequest
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import com.localmesh.chat.domain.messaging.MessageScope
import com.localmesh.chat.domain.users.Peer
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import java.util.concurrent.ConcurrentHashMap

/**
 * In-memory chat and peer state. Messages are intentionally not persisted.
 */
class InMemorySessionStore {
    private val group = MutableStateFlow<List<ChatMessage>>(emptyList())
    private val privateChats = ConcurrentHashMap<String, MutableStateFlow<List<ChatMessage>>>()
    private val requests = MutableStateFlow<List<ChatRequest>>(emptyList())
    private val peers = MutableStateFlow<List<Peer>>(emptyList())
    private val accepted = ConcurrentHashMap<String, Boolean>()

    val groupMessages: StateFlow<List<ChatMessage>> = group.asStateFlow()
    val chatRequests: StateFlow<List<ChatRequest>> = requests.asStateFlow()
    val nearbyPeers: StateFlow<List<Peer>> = peers.asStateFlow()

    fun privateMessages(peerId: UserId): StateFlow<List<ChatMessage>> =
        privateChats.getOrPut(peerId.hex) { MutableStateFlow(emptyList()) }.asStateFlow()

    fun addGroup(message: ChatMessage) {
        group.update { trim((it + message).distinctBy { m -> m.id }) }
    }

    fun addPrivate(peerId: UserId, message: ChatMessage) {
        val flow = privateChats.getOrPut(peerId.hex) { MutableStateFlow(emptyList()) }
        flow.update { trim((it + message).distinctBy { m -> m.id }) }
    }

    fun upsertPeer(peer: Peer) {
        peers.update { current ->
            val without = current.filterNot { it.userId == peer.userId }
            (without + peer).sortedBy { it.displayName.lowercase() }
        }
    }

    fun removePeer(userId: UserId) {
        peers.update { it.filterNot { p -> p.userId == userId } }
    }

    fun peer(userId: UserId): Peer? = peers.value.firstOrNull { it.userId == userId }

    fun upsertRequest(request: ChatRequest) {
        requests.update { current ->
            val without = current.filterNot { it.requestId == request.requestId }
            without + request
        }
        if (request.status == ChatRequestStatus.ACCEPTED) {
            accepted[conversationKey(request.from, request.to)] = true
        }
        if (request.status == ChatRequestStatus.REJECTED) {
            accepted.remove(conversationKey(request.from, request.to))
        }
    }

    fun requestBetween(a: UserId, b: UserId): ChatRequest? =
        requests.value.lastOrNull {
            (it.from == a && it.to == b) || (it.from == b && it.to == a)
        }

    fun isPrivateOpen(a: UserId, b: UserId): Boolean =
        accepted[conversationKey(a, b)] == true ||
            requestBetween(a, b)?.status == ChatRequestStatus.ACCEPTED

    fun statusFor(local: UserId, peer: UserId): ChatRequestStatus {
        val req = requestBetween(local, peer) ?: return ChatRequestStatus.NONE
        return req.status
    }

    fun clearMessages() {
        group.value = emptyList()
        privateChats.values.forEach { it.value = emptyList() }
    }

    fun clearAll() {
        clearMessages()
        requests.value = emptyList()
        peers.value = emptyList()
        accepted.clear()
        privateChats.clear()
    }

    private fun trim(list: List<ChatMessage>): List<ChatMessage> =
        if (list.size > MAX_IN_MEMORY) list.takeLast(MAX_IN_MEMORY) else list

    companion object {
        const val MAX_IN_MEMORY = 400
        fun conversationKey(a: UserId, b: UserId): String =
            listOf(a.hex, b.hex).sorted().joinToString(":")
        const val GROUP_CONVERSATION = Protocol.LOCAL_GROUP_ID
    }
}
