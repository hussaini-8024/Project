package com.localmesh.chat.ui.members

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import com.localmesh.chat.domain.users.Peer
import com.localmesh.chat.ui.common.EmptyState
import com.localmesh.chat.ui.common.PeerRow

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MembersScreen(
    peers: List<Peer>,
    statusFor: (UserId) -> ChatRequestStatus,
    onRequest: (UserId) -> Unit,
    onOpen: (Peer) -> Unit,
) {
    Column(Modifier.fillMaxSize()) {
        TopAppBar(title = { Text("Members") })
        if (peers.isEmpty()) {
            EmptyState(
                "No nearby users",
                "Keep LocalMesh open on the same Wi-Fi or enable Bluetooth. Discovery is automatic.",
            )
        } else {
            LazyColumn {
                items(peers, key = { it.userId.hex }) { peer ->
                    PeerRow(peer) {
                        when (statusFor(peer.userId)) {
                            ChatRequestStatus.NONE, ChatRequestStatus.REJECTED ->
                                Button(onClick = { onRequest(peer.userId) }) { Text("Send Request") }
                            ChatRequestStatus.OUTGOING ->
                                TextButton(onClick = { }, enabled = false) { Text("Request Pending") }
                            ChatRequestStatus.INCOMING ->
                                TextButton(onClick = { }, enabled = false) { Text("Incoming request") }
                            ChatRequestStatus.ACCEPTED ->
                                Button(onClick = { onOpen(peer) }) { Text("Open Chat") }
                        }
                    }
                }
            }
        }
    }
}
