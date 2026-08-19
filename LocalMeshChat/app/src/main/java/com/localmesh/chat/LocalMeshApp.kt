package com.localmesh.chat

import android.app.Application
import com.localmesh.chat.core.crypto.AndroidIdentityStore
import com.localmesh.chat.core.crypto.GroupCrypto
import com.localmesh.chat.core.crypto.IdentityStore
import com.localmesh.chat.core.crypto.LocalIdentity
import com.localmesh.chat.core.crypto.PrivateSessionManager
import com.localmesh.chat.core.routing.MeshDiagnostics
import com.localmesh.chat.core.storage.SettingsRepository
import com.localmesh.chat.data.repositories.InMemorySessionStore
import com.localmesh.chat.services.routing.MeshEngine
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

class LocalMeshApp : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
    }
}

class AppContainer(private val app: LocalMeshApp) {
    val identityStore: IdentityStore = AndroidIdentityStore(app)
    val settings = SettingsRepository(app)
    val session = InMemorySessionStore()
    val groupCrypto = GroupCrypto()
    val diagnostics = MeshDiagnostics()
    val privateSessions = PrivateSessionManager(identityStore)

    private val mutex = Mutex()
    private val _engine = MutableStateFlow<MeshEngine?>(null)
    val engine: StateFlow<MeshEngine?> = _engine

    suspend fun ensureEngine(identity: LocalIdentity): MeshEngine = mutex.withLock {
        _engine.value?.let { return it }
        val created = MeshEngine(
            context = app,
            identityStore = identityStore,
            identity = identity,
            store = session,
            groupCrypto = groupCrypto,
            privateSessions = privateSessions,
            diagnostics = diagnostics,
        )
        _engine.value = created
        created
    }

    suspend fun shutdownMesh() {
        mutex.withLock {
            _engine.value?.stop()
            _engine.value = null
        }
    }

    suspend fun deleteAccount() {
        shutdownMesh()
        session.clearAll()
        groupCrypto.clear()
        privateSessions.clear()
        diagnostics.reset()
        identityStore.delete()
        settings.clear()
    }
}
