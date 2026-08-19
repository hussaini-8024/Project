package com.localmesh.chat.ui.settings

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.localmesh.chat.BuildConfig
import com.localmesh.chat.core.storage.AppSettings

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    settings: AppSettings,
    onChange: ((AppSettings) -> AppSettings) -> Unit,
    onClearSession: () -> Unit,
    onDeleteAccount: () -> Unit,
    onOpenDebug: () -> Unit,
) {
    var confirmDelete by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxSize()) {
        TopAppBar(title = { Text("Settings") })
        Column(Modifier.verticalScroll(rememberScrollState())) {
            Section("Account")
            ListItem(
                headlineContent = { Text("One account per device") },
                supportingContent = { Text("Delete the local identity to create a new one. Cryptographic keys cannot be reused after deletion.") },
            )
            ListItem(
                headlineContent = { Text("Delete account") },
                supportingContent = { Text("Removes identity, keys, and in-memory chat state.") },
                modifier = Modifier.clickable { confirmDelete = true },
            )
            HorizontalDivider()
            Section("Privacy")
            Toggle("Show connection type", settings.showConnectionType) {
                onChange { it.copy(showConnectionType = it.showConnectionType.not()) }
            }
            ListItem(
                headlineContent = { Text("Chat history") },
                supportingContent = { Text("LocalMesh does not intentionally persist chat messages. Android may still retain traces outside the app.") },
            )
            ListItem(
                headlineContent = { Text("Clear temporary session data") },
                supportingContent = { Text("Clears in-memory messages, group key, and private sessions.") },
                modifier = Modifier.clickable { onClearSession() },
            )
            HorizontalDivider()
            Section("Notifications")
            Toggle("Notification preview", settings.notificationPreview) {
                onChange { it.copy(notificationPreview = !it.notificationPreview) }
            }
            ListItem(
                supportingContent = { Text("Off by default. Previews never include private message bodies.") },
                headlineContent = { Text("Default alert: “New LocalMesh message”") },
            )
            HorizontalDivider()
            Section("Network")
            Toggle("Wi-Fi / local network", settings.wifiEnabled) {
                onChange { it.copy(wifiEnabled = !it.wifiEnabled) }
            }
            Toggle("Participate as bridge", settings.bridgeEnabled) {
                onChange { it.copy(bridgeEnabled = !it.bridgeEnabled) }
            }
            HorizontalDivider()
            Section("Bluetooth")
            Toggle("Bluetooth mesh", settings.bluetoothEnabled) {
                onChange { it.copy(bluetoothEnabled = !it.bluetoothEnabled) }
            }
            HorizontalDivider()
            Section("Security")
            ListItem(
                headlineContent = { Text("Identity") },
                supportingContent = { Text("ECDSA P-256 in Android Keystore. Private key is never transmitted.") },
            )
            ListItem(
                headlineContent = { Text("Messages") },
                supportingContent = { Text("AES-256-GCM with signed packets. Bridges forward ciphertext only.") },
            )
            HorizontalDivider()
            Section("About")
            ListItem(
                headlineContent = { Text("LocalMesh Chat") },
                supportingContent = { Text("Version ${BuildConfig.VERSION_NAME}  ·  Serverless local mesh") },
            )
            if (BuildConfig.SHOW_DEBUG_CONSOLE || settings.developerMode) {
                ListItem(
                    headlineContent = { Text("Network diagnostics") },
                    modifier = Modifier.clickable { onOpenDebug() },
                )
            }
            Toggle("Developer mode", settings.developerMode) {
                onChange { it.copy(developerMode = !it.developerMode) }
            }
            androidx.compose.foundation.layout.Spacer(Modifier.padding(24.dp))
        }
    }
    if (confirmDelete) {
        AlertDialog(
            onDismissRequest = { confirmDelete = false },
            title = { Text("Delete account?") },
            text = { Text("This device’s LocalMesh identity and keys will be removed. You can create a new account afterwards.") },
            confirmButton = {
                TextButton(onClick = { confirmDelete = false; onDeleteAccount() }) { Text("Delete") }
            },
            dismissButton = { TextButton(onClick = { confirmDelete = false }) { Text("Cancel") } },
        )
    }
}

@Composable
private fun Section(title: String) {
    Text(
        title,
        style = MaterialTheme.typography.titleMedium,
        color = MaterialTheme.colorScheme.primary,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
    )
}

@Composable
private fun Toggle(title: String, checked: Boolean, onToggle: () -> Unit) {
    ListItem(
        headlineContent = { Text(title) },
        trailingContent = { Switch(checked = checked, onCheckedChange = { onToggle() }) },
    )
}
