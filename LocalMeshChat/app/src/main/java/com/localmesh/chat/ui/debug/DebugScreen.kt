package com.localmesh.chat.ui.debug

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.routing.DiagnosticsSnapshot
import com.localmesh.chat.core.routing.RouteEntry
import com.localmesh.chat.domain.connections.LocalCapabilities
import com.localmesh.chat.domain.users.Peer
import com.localmesh.chat.services.routing.MeshEngine
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DebugScreen(
    identity: LocalIdentity,
    caps: LocalCapabilities,
    engine: MeshEngine?,
    peers: List<Peer>,
    onBack: () -> Unit,
) {
    val routes = engine?.routingTable?.snapshot().orEmpty()
    val stats = engine?.stats?.snapshot() ?: DiagnosticsSnapshot(0, 0, 0, 0, 0, 0, 0)
    Column(Modifier.fillMaxSize()) {
        TopAppBar(
            title = { Text("Diagnostics") },
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                }
            },
        )
        Column(Modifier.padding(16.dp).verticalScroll(rememberScrollState())) {
            Line("Local user ID", identity.userId.hex)
            Line("Display name", identity.displayName)
            Line("Wi-Fi", if (caps.wifiEnabled) "enabled" else "off")
            Line("Bluetooth", if (caps.bluetoothEnabled) "enabled" else "off")
            Line("Bridge", if (caps.actingAsBridge) "ACTIVE" else if (caps.bridgeEnabled) "eligible" else "disabled")
            Line("Packets sent", stats.packetsSent.toString())
            Line("Packets received", stats.packetsReceived.toString())
            Line("Packets forwarded", stats.packetsForwarded.toString())
            Line("Dropped", stats.packetsDropped.toString())
            Line("Duplicates", stats.duplicates.toString())
            Line("Decrypt failures", stats.decryptFailures.toString())
            Text("Connected peers", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 16.dp))
            if (peers.isEmpty()) Text("None", color = MaterialTheme.colorScheme.outline)
            peers.forEach { peer ->
                Line(
                    peer.displayName,
                    "${peer.userId.shortLabel()} · ${peer.transportLabel()} · hops=${peer.hopCount} · ${peer.connectionState}",
                )
            }
            Text("Routing table", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(top = 16.dp))
            if (routes.isEmpty()) Text("Empty", color = MaterialTheme.colorScheme.outline)
            routes.forEach { route -> RouteLine(route) }
        }
    }
}

@Composable
private fun Line(label: String, value: String) {
    Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
    Text(value, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(bottom = 8.dp, top = 2.dp))
}

@Composable
private fun RouteLine(route: RouteEntry) {
    val seen = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date(route.lastSeenMs))
    Line(
        route.peerId.shortLabel(),
        "next=${route.nextHop.shortLabel()} hops=${route.hopCount} via=${route.transport.label()} seen=$seen ${route.connectionState}",
    )
}
