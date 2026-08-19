package com.localmesh.chat.domain.connections

enum class TransportKind {
    WIFI,
    BLUETOOTH,
    BOTH,
    NONE,
    ;

    fun label(): String = when (this) {
        WIFI -> "Wi-Fi"
        BLUETOOTH -> "Bluetooth"
        BOTH -> "Wi-Fi + Bluetooth"
        NONE -> "Offline"
    }

    fun includesWifi(): Boolean = this == WIFI || this == BOTH
    fun includesBluetooth(): Boolean = this == BLUETOOTH || this == BOTH

    companion object {
        fun fromFlags(wifi: Boolean, bluetooth: Boolean): TransportKind = when {
            wifi && bluetooth -> BOTH
            wifi -> WIFI
            bluetooth -> BLUETOOTH
            else -> NONE
        }
    }
}

enum class ConnectionState {
    DISCONNECTED,
    DISCOVERED,
    CONNECTING,
    CONNECTED,
    FAILED,
}

data class LocalCapabilities(
    val wifiAvailable: Boolean,
    val bluetoothAvailable: Boolean,
    val wifiEnabled: Boolean,
    val bluetoothEnabled: Boolean,
    val bridgeEnabled: Boolean,
    val actingAsBridge: Boolean,
) {
    val transport: TransportKind
        get() = TransportKind.fromFlags(wifiEnabled && wifiAvailable, bluetoothEnabled && bluetoothAvailable)
}
