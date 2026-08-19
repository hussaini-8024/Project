package com.localmesh.chat

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Chat
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.localmesh.chat.ui.AppViewModel
import com.localmesh.chat.ui.debug.DebugScreen
import com.localmesh.chat.ui.groupchat.GroupChatScreen
import com.localmesh.chat.ui.members.MembersScreen
import com.localmesh.chat.ui.onboarding.OnboardingScreen
import com.localmesh.chat.ui.permissions.MeshPermissions
import com.localmesh.chat.ui.privatechat.PrivateChatScreen
import com.localmesh.chat.ui.profile.ProfileScreen
import com.localmesh.chat.ui.settings.SettingsScreen
import com.localmesh.chat.ui.theme.LocalMeshTheme

class MainActivity : ComponentActivity() {
    private val viewModel: AppViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            LocalMeshTheme {
                val identity by viewModel.identity.collectAsStateWithLifecycle()
                val bootstrapped by viewModel.bootstrapped.collectAsStateWithLifecycle()
                val error by viewModel.error.collectAsStateWithLifecycle()
                if (!bootstrapped) return@LocalMeshTheme
                if (identity == null) {
                    OnboardingScreen(error = error, onCreate = viewModel::createAccount)
                } else {
                    MeshApp(viewModel)
                }
            }
        }
    }
}

@Composable
private fun MeshApp(viewModel: AppViewModel) {
    val context = LocalContext.current
    var showPermissionInfo by remember { mutableStateOf(true) }
    var permissionDenied by remember { mutableStateOf(false) }
    val launcher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { result ->
        permissionDenied = result.values.any { granted -> !granted }
        viewModel.startMeshIfPossible()
    }
    LaunchedEffect(Unit) {
        if (MeshPermissions.granted(context, MeshPermissions.allRuntime())) {
            showPermissionInfo = false
            viewModel.startMeshIfPossible()
        }
    }

    if (showPermissionInfo && !MeshPermissions.granted(context, MeshPermissions.allRuntime())) {
        AlertDialog(
            onDismissRequest = { },
            title = { Text("Nearby communication") },
            text = {
                Text(
                    "LocalMesh uses Wi-Fi and Bluetooth to find people next to you. " +
                        "Nothing is sent to the internet. You can deny a permission; that transport will stay off.",
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    showPermissionInfo = false
                    launcher.launch(MeshPermissions.allRuntime())
                }) { Text("Continue") }
            },
        )
    }

    val nav = rememberNavController()
    var tab by remember { mutableIntStateOf(0) }
    val identity by viewModel.identity.collectAsStateWithLifecycle()
    val caps by viewModel.capabilities.collectAsStateWithLifecycle()
    val messages by viewModel.groupMessages.collectAsStateWithLifecycle()
    val peers by viewModel.peers.collectAsStateWithLifecycle()
    val incoming by viewModel.incomingRequest.collectAsStateWithLifecycle()
    val settings by viewModel.settings.collectAsStateWithLifecycle()
    val engine by viewModel.engine.collectAsStateWithLifecycle()
    val local = identity ?: return

    Scaffold(
        modifier = Modifier.fillMaxSize(),
        bottomBar = {
            NavigationBar {
                NavigationBarItem(
                    selected = tab == 0,
                    onClick = {
                        tab = 0
                        nav.navigate("chat") { launchSingleTop = true }
                    },
                    icon = { Icon(Icons.AutoMirrored.Outlined.Chat, contentDescription = "Chat") },
                    label = { Text("Chat") },
                )
                NavigationBarItem(
                    selected = tab == 1,
                    onClick = {
                        tab = 1
                        nav.navigate("members") { launchSingleTop = true }
                    },
                    icon = { Icon(Icons.Outlined.Group, contentDescription = "Members") },
                    label = { Text("Members") },
                )
                NavigationBarItem(
                    selected = tab == 2,
                    onClick = {
                        tab = 2
                        nav.navigate("profile") { launchSingleTop = true }
                    },
                    icon = { Icon(Icons.Outlined.Person, contentDescription = "Profile") },
                    label = { Text("Profile") },
                )
                NavigationBarItem(
                    selected = tab == 3,
                    onClick = {
                        tab = 3
                        nav.navigate("settings") { launchSingleTop = true }
                    },
                    icon = { Icon(Icons.Outlined.Settings, contentDescription = "Settings") },
                    label = { Text("Settings") },
                )
            }
        },
    ) { padding ->
        Column(Modifier.padding(padding)) {
            if (permissionDenied) {
                Text(
                    "A permission was denied. Wi-Fi or Bluetooth discovery may be limited. Enable it in system settings to recover.",
                    modifier = Modifier.padding(12.dp),
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodySmall,
                )
            }
            NavHost(navController = nav, startDestination = "chat") {
                composable("chat") {
                    GroupChatScreen(
                        messages = messages,
                        caps = caps,
                        incoming = incoming,
                        onSend = viewModel::sendGroup,
                        onAccept = { viewModel.respond(it, true) },
                        onReject = { viewModel.respond(it, false) },
                    )
                }
                composable("members") {
                    MembersScreen(
                        peers = peers,
                        statusFor = viewModel::chatStatus,
                        onRequest = viewModel::requestChat,
                        onOpen = { peer -> nav.navigate("private/${peer.userId.hex}") },
                    )
                }
                composable("profile") {
                    ProfileScreen(local, caps, viewModel::updateName)
                }
                composable("settings") {
                    SettingsScreen(
                        settings = settings,
                        onChange = viewModel::updateSettings,
                        onClearSession = viewModel::clearSession,
                        onDeleteAccount = viewModel::deleteAccount,
                        onOpenDebug = { nav.navigate("debug") },
                    )
                }
                composable("debug") {
                    DebugScreen(local, caps, engine, peers, onBack = { nav.popBackStack() })
                }
                composable(
                    "private/{userId}",
                    arguments = listOf(navArgument("userId") { type = NavType.StringType }),
                ) { entry ->
                    val hex = entry.arguments?.getString("userId") ?: return@composable
                    val peer = peers.firstOrNull { it.userId.hex == hex }
                    if (peer == null || !viewModel.isPrivateOpen(peer.userId)) {
                        Text(
                            "Private chat is not available. Send a request from Members.",
                            modifier = Modifier.padding(16.dp),
                        )
                        return@composable
                    }
                    val privateMsgs by viewModel.privateMessages(peer.userId).collectAsState()
                    PrivateChatScreen(
                        peer = peer,
                        messages = privateMsgs,
                        onBack = { nav.popBackStack() },
                        onSend = { viewModel.sendPrivate(peer.userId, it) },
                    )
                }
            }
        }
    }
}
