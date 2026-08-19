package com.localmesh.chat.ui.profile

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.toHex
import com.localmesh.chat.domain.connections.LocalCapabilities

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(
    identity: LocalIdentity,
    caps: LocalCapabilities,
    onSaveName: (String) -> Unit,
) {
    var name by rememberSaveable(identity.displayName) { mutableStateOf(identity.displayName) }
    Column(Modifier.fillMaxSize()) {
        TopAppBar(title = { Text("Profile") })
        Column(Modifier.padding(16.dp).verticalScroll(rememberScrollState())) {
            OutlinedTextField(
                value = name,
                onValueChange = { if (it.length <= 64) name = it },
                label = { Text("Name") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))
            Button(onClick = { onSaveName(name) }, enabled = name.trim().length >= 2) {
                Text("Save name")
            }
            Spacer(Modifier.height(24.dp))
            Labeled("User ID", identity.userId.hex)
            Labeled("Public identity key", identity.identityPublicKey.toHex())
            Labeled("Key-agreement public key", identity.keyAgreementPublicKey.toHex())
            Labeled("Connection", caps.transport.label())
            Labeled("Bridge", if (caps.actingAsBridge) "Active" else if (caps.bridgeEnabled) "Ready" else "Disabled")
            Spacer(Modifier.height(12.dp))
            Text(
                "The cryptographic identity cannot be changed without deleting the account.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.outline,
            )
        }
    }
}

@Composable
private fun Labeled(label: String, value: String) {
    Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
    Text(value, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(bottom = 12.dp, top = 4.dp))
}
