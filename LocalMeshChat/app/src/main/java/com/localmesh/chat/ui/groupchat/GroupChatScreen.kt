package com.localmesh.chat.ui.groupchat

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.localmesh.chat.domain.connections.LocalCapabilities
import com.localmesh.chat.domain.messaging.ChatMessage
import com.localmesh.chat.domain.messaging.ChatRequest
import com.localmesh.chat.ui.common.ConnectionBanner
import com.localmesh.chat.ui.common.EmptyState
import com.localmesh.chat.ui.common.MessageBubble
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GroupChatScreen(
    messages: List<ChatMessage>,
    caps: LocalCapabilities,
    incoming: ChatRequest?,
    onSend: (String) -> Unit,
    onAccept: (ChatRequest) -> Unit,
    onReject: (ChatRequest) -> Unit,
) {
    var draft by rememberSaveable { mutableStateOf("") }
    val listState = rememberLazyListState()
    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) listState.animateScrollToItem(messages.lastIndex)
    }
    Column(Modifier.fillMaxSize()) {
        TopAppBar(title = {
            Column {
                Text("LOCALMESH")
                Text("Local Network Chat", style = MaterialTheme.typography.bodySmall)
            }
        })
        ConnectionBanner(caps)
        if (messages.isEmpty()) {
            EmptyState(
                "No messages yet",
                "Nearby LocalMesh devices join this group automatically. Send a message when you are ready.",
            )
        } else {
            LazyColumn(Modifier.weight(1f), state = listState) {
                items(messages, key = { it.id }) { msg ->
                    MessageBubble(msg.senderName, msg.body, msg.fromSelf, formatTime(msg.timestampMs))
                }
            }
        }
        if (messages.isEmpty()) {
            androidx.compose.foundation.layout.Spacer(Modifier.weight(1f))
        }
        Row(
            Modifier.fillMaxWidth().padding(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            OutlinedTextField(
                value = draft,
                onValueChange = { if (it.length <= 2000) draft = it },
                modifier = Modifier.weight(1f),
                placeholder = { Text("Message Local Network Chat") },
            )
            IconButton(
                onClick = {
                    onSend(draft)
                    draft = ""
                },
                enabled = draft.isNotBlank(),
            ) {
                Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Send")
            }
        }
    }
    if (incoming != null) {
        AlertDialog(
            onDismissRequest = { },
            title = { Text("Private chat request") },
            text = { Text("${incoming.fromName} wants to start a private chat.") },
            confirmButton = { TextButton(onClick = { onAccept(incoming) }) { Text("Accept") } },
            dismissButton = { TextButton(onClick = { onReject(incoming) }) { Text("Reject") } },
        )
    }
}

private fun formatTime(ms: Long): String =
    SimpleDateFormat("h:mm a", Locale.getDefault()).format(Date(ms))
