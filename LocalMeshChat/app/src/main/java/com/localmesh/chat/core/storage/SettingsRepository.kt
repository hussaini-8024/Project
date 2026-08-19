package com.localmesh.chat.core.storage

import android.content.Context
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.settingsDataStore by preferencesDataStore("localmesh_settings")

data class AppSettings(
    val notificationPreview: Boolean = false,
    val bridgeEnabled: Boolean = true,
    val wifiEnabled: Boolean = true,
    val bluetoothEnabled: Boolean = true,
    val showConnectionType: Boolean = true,
    val developerMode: Boolean = false,
)

class SettingsRepository(private val context: Context) {
    private val preview = booleanPreferencesKey("notification_preview")
    private val bridge = booleanPreferencesKey("bridge_enabled")
    private val wifi = booleanPreferencesKey("wifi_enabled")
    private val bt = booleanPreferencesKey("bluetooth_enabled")
    private val showType = booleanPreferencesKey("show_connection_type")
    private val dev = booleanPreferencesKey("developer_mode")

    val settings: Flow<AppSettings> = context.settingsDataStore.data.map { prefs ->
        AppSettings(
            notificationPreview = prefs[preview] ?: false,
            bridgeEnabled = prefs[bridge] ?: true,
            wifiEnabled = prefs[wifi] ?: true,
            bluetoothEnabled = prefs[bt] ?: true,
            showConnectionType = prefs[showType] ?: true,
            developerMode = prefs[dev] ?: false,
        )
    }

    suspend fun update(transform: (AppSettings) -> AppSettings) {
        context.settingsDataStore.edit { prefs ->
            val current = AppSettings(
                notificationPreview = prefs[preview] ?: false,
                bridgeEnabled = prefs[bridge] ?: true,
                wifiEnabled = prefs[wifi] ?: true,
                bluetoothEnabled = prefs[bt] ?: true,
                showConnectionType = prefs[showType] ?: true,
                developerMode = prefs[dev] ?: false,
            )
            val next = transform(current)
            prefs[preview] = next.notificationPreview
            prefs[bridge] = next.bridgeEnabled
            prefs[wifi] = next.wifiEnabled
            prefs[bt] = next.bluetoothEnabled
            prefs[showType] = next.showConnectionType
            prefs[dev] = next.developerMode
        }
    }

    suspend fun clear() {
        context.settingsDataStore.edit { it.clear() }
    }
}
