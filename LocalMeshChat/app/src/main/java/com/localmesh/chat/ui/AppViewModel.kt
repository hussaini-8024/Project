package com.localmesh.chat.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.localmesh.chat.LocalMeshApp
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.storage.AppSettings
import com.localmesh.chat.data.repositories.InMemorySessionStore
import com.localmesh.chat.domain.connections.LocalCapabilities
import com.localmesh.chat.domain.messaging.ChatMessage
import com.localmesh.chat.domain.messaging.ChatRequest
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import com.localmesh.chat.domain.users.Peer
import com.localmesh.chat.services.MeshForegroundService
import com.localmesh.chat.services.routing.MeshEngine
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

@OptIn(ExperimentalCoroutinesApi::class)
class AppViewModel(application: Application) : AndroidViewModel(application) {
    private val app = application as LocalMeshApp
    private val container = app.container

    private val _identity = MutableStateFlow<LocalIdentity?>(null)
    val identity: StateFlow<LocalIdentity?> = _identity

    private val _bootstrapped = MutableStateFlow(false)
    val bootstrapped: StateFlow<Boolean> = _bootstrapped

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error

    val settings: StateFlow<AppSettings> = container.settings.settings
        .stateIn(viewModelScope, SharingStarted.Eagerly, AppSettings())

    val engine: StateFlow<MeshEngine?> = container.engine

    val capabilities: StateFlow<LocalCapabilities> = engine.flatMapLatest { e ->
        e?.capabilities ?: flowOf(
            LocalCapabilities(true, true, false, false, true, false),
        )
    }.stateIn(
        viewModelScope,
        SharingStarted.Eagerly,
        LocalCapabilities(true, true, false, false, true, false),
    )

    val groupMessages: StateFlow<List<ChatMessage>> = container.session.groupMessages
    val peers: StateFlow<List<Peer>> = container.session.nearbyPeers
    val requests: StateFlow<List<ChatRequest>> = container.session.chatRequests

    val incomingRequest: StateFlow<ChatRequest?> = requests.map { list ->
        list.lastOrNull { it.status == ChatRequestStatus.INCOMING }
    }.stateIn(viewModelScope, SharingStarted.Eagerly, null)

    init {
        viewModelScope.launch {
            _identity.value = container.identityStore.load()
            _bootstrapped.value = true
        }
    }

    fun createAccount(name: String) {
        viewModelScope.launch {
            try {
                val created = container.identityStore.create(name)
                _identity.value = created
                _error.value = null
            } catch (e: Exception) {
                _error.value = e.message ?: "Could not create account"
            }
        }
    }

    fun startMeshIfPossible() {
        val id = _identity.value ?: return
        viewModelScope.launch {
            container.ensureEngine(id)
            MeshForegroundService.start(getApplication())
        }
    }

    fun sendGroup(text: String) {
        viewModelScope.launch {
            container.engine.value?.sendGroupMessage(text)
        }
    }

    fun sendPrivate(peer: UserId, text: String) {
        viewModelScope.launch {
            container.engine.value?.sendPrivateMessage(peer, text)
        }
    }

    fun requestChat(peer: UserId) {
        viewModelScope.launch {
            container.engine.value?.sendChatRequest(peer)
        }
    }

    fun respond(request: ChatRequest, accept: Boolean) {
        viewModelScope.launch {
            container.engine.value?.respondToRequest(request, accept)
        }
    }

    fun privateMessages(peer: UserId): StateFlow<List<ChatMessage>> = container.session.privateMessages(peer)

    fun chatStatus(peer: UserId): ChatRequestStatus {
        val local = _identity.value?.userId ?: return ChatRequestStatus.NONE
        return container.session.statusFor(local, peer)
    }

    fun isPrivateOpen(peer: UserId): Boolean {
        val local = _identity.value?.userId ?: return false
        return container.session.isPrivateOpen(local, peer)
    }

    fun updateName(name: String) {
        viewModelScope.launch {
            _identity.value = container.identityStore.updateDisplayName(name)
        }
    }

    fun updateSettings(transform: (AppSettings) -> AppSettings) {
        viewModelScope.launch { container.settings.update(transform) }
    }

    fun clearSession() {
        container.engine.value?.clearSessionData() ?: container.session.clearMessages()
    }

    fun deleteAccount() {
        viewModelScope.launch {
            MeshForegroundService.stop(getApplication())
            container.deleteAccount()
            _identity.value = null
        }
    }

    fun store(): InMemorySessionStore = container.session
}
