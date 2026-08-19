package com.localmesh.chat.ui.common

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.localmesh.chat.domain.connections.LocalCapabilities
import com.localmesh.chat.domain.connections.TransportKind
import com.localmesh.chat.domain.users.Peer

@Composable
fun StatusDot(kind: TransportKind, modifier: Modifier = Modifier) {
    val color = when (kind) {
        TransportKind.WIFI, TransportKind.BOTH -> Color(0xFF22C55E)
        TransportKind.BLUETOOTH -> Color(0xFF3B82F6)
        TransportKind.NONE -> Color(0xFF9CA3AF)
    }
    Box(
        modifier = modifier
            .size(10.dp)
            .clip(CircleShape)
            .background(color),
    )
}

@Composable
fun ConnectionBanner(caps: LocalCapabilities, modifier: Modifier = Modifier) {
    val (label, color) = when {
        caps.actingAsBridge -> "BRIDGE ACTIVE" to Color(0xFF0F8F86)
        caps.wifiEnabled && caps.bluetoothEnabled -> "Wi-Fi + Bluetooth" to Color(0xFF22C55E)
        caps.wifiEnabled -> "Wi-Fi" to Color(0xFF22C55E)
        caps.bluetoothEnabled -> "Bluetooth" to Color(0xFF3B82F6)
        else -> "Searching nearby…" to Color(0xFF9CA3AF)
    }
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 1.dp,
    ) {
        Row(
            Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(color))
            Spacer(Modifier.width(8.dp))
            Text(label, style = MaterialTheme.typography.labelLarge)
            if (caps.actingAsBridge) {
                Spacer(Modifier.width(12.dp))
                Text("Wi-Fi ✓   Bluetooth ✓", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
fun PeerRow(peer: Peer, trailing: @Composable () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StatusDot(peer.transports)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(peer.displayName, style = MaterialTheme.typography.titleMedium)
            Text(peer.transportLabel(), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.outline)
        }
        trailing()
    }
}

@Composable
fun EmptyState(title: String, body: String) {
    Column(
        Modifier
            .fillMaxWidth()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(title, style = MaterialTheme.typography.titleLarge)
        Spacer(Modifier.height(8.dp))
        Text(body, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.outline)
    }
}

@Composable
fun MessageBubble(
    name: String,
    body: String,
    mine: Boolean,
    time: String,
) {
    val bg = if (mine) com.localmesh.chat.ui.theme.MeshBubbleMine else com.localmesh.chat.ui.theme.MeshBubbleTheirs
    val fg = if (mine) Color.White else MaterialTheme.colorScheme.onSurface
    Column(
        Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp),
        horizontalAlignment = if (mine) Alignment.End else Alignment.Start,
    ) {
        if (!mine) {
            Text(name, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        }
        Box(
            Modifier
                .clip(RoundedCornerShape(18.dp))
                .background(bg)
                .padding(horizontal = 14.dp, vertical = 10.dp),
        ) {
            Text(body, color = fg, style = MaterialTheme.typography.bodyLarge)
        }
        Text(time, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.outline)
    }
}
