package com.localmesh.chat.domain.messaging

import com.localmesh.chat.core.crypto.UserId

enum class MessageScope { GROUP, PRIVATE }

enum class MessageDelivery { PENDING, SENT, DELIVERED, FAILED }

data class ChatMessage(
    val id: String,
    val scope: MessageScope,
    val senderId: UserId,
    val senderName: String,
    val conversationId: String,
    val body: String,
    val timestampMs: Long,
    val fromSelf: Boolean,
    val delivery: MessageDelivery = MessageDelivery.SENT,
)

enum class ChatRequestStatus { NONE, OUTGOING, INCOMING, ACCEPTED, REJECTED }

data class ChatRequest(
    val requestId: String,
    val from: UserId,
    val fromName: String,
    val to: UserId,
    val status: ChatRequestStatus,
    val timestampMs: Long,
)
