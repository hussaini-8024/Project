package com.localmesh.chat.core.protocol

object Protocol {
    const val MAGIC: Int = 0x4C4D5348 // "LMSH"
    const val VERSION: Int = 1
    const val TCP_PORT: Int = 47821
    const val UDP_BEACON_PORT: Int = 47822
    const val MAX_PACKET_BYTES: Int = 32 * 1024
    const val MAX_PAYLOAD_BYTES: Int = 12 * 1024
    const val MAX_DISPLAY_NAME: Int = 64
    const val DEFAULT_TTL: Int = 8
    const val MAX_TTL: Int = 16
    const val USER_ID_LEN: Int = 32
    const val MESSAGE_ID_LEN: Int = 16
    const val HEADER_BYTES: Int = 98
    const val CLOCK_SKEW_MS: Long = 10 * 60 * 1000L
    const val PEER_TIMEOUT_MS: Long = 45_000L
    const val ROUTE_TTL_MS: Long = 60_000L
    const val DUPLICATE_TTL_MS: Long = 120_000L
    const val BEACON_INTERVAL_MS: Long = 2_500L
    const val PING_INTERVAL_MS: Long = 8_000L
    const val HANDSHAKE_TIMEOUT_MS: Long = 8_000L
    const val LOCAL_GROUP_ID: String = "localmesh.local-network"
    const val NSD_SERVICE_TYPE: String = "_localmesh._tcp."
    const val NSD_SERVICE_NAME: String = "LocalMesh"
    const val MULTICAST_GROUP: String = "239.55.55.55"
    const val RFCOMM_UUID: String = "6e6c6d73-6801-41a4-8c70-6c6f63616c31"
    const val BLE_SERVICE_UUID: String = "6e6c6d73-6801-41a4-8c70-6c6f63616c32"

    val GROUP_BROADCAST: ByteArray = ByteArray(USER_ID_LEN) { 0xFF.toByte() }
}

enum class PacketType(val code: Int) {
    HELLO(0x01),
    HELLO_ACK(0x02),
    ROUTE_ADVERT(0x03),
    GROUP_MSG(0x04),
    PRIVATE_MSG(0x05),
    CHAT_REQUEST(0x06),
    CHAT_ACCEPT(0x07),
    CHAT_REJECT(0x08),
    ACK(0x09),
    PING(0x0A),
    PONG(0x0B),
    GROUP_KEY_WRAP(0x0C),
    PEER_GONE(0x0D);

    companion object {
        fun fromCode(code: Int): PacketType? = entries.firstOrNull { it.code == code }
    }
}

object PacketFlags {
    const val NEEDS_ACK: Int = 1 shl 0
    const val FORWARDED: Int = 1 shl 1
}

object CapabilityFlags {
    const val WIFI: Int = 1 shl 0
    const val BLUETOOTH: Int = 1 shl 1
    const val BRIDGE: Int = 1 shl 2
}
