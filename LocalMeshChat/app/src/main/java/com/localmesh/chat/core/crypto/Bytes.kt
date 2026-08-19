package com.localmesh.chat.core.crypto

import java.security.MessageDigest
import java.security.SecureRandom

private val HEX = "0123456789abcdef".toCharArray()

fun ByteArray.toHex(): String {
    val out = CharArray(size * 2)
    for (i in indices) {
        val v = this[i].toInt() and 0xFF
        out[i * 2] = HEX[v ushr 4]
        out[i * 2 + 1] = HEX[v and 0x0F]
    }
    return String(out)
}

fun String.hexToBytes(): ByteArray {
    val clean = lowercase().replace(Regex("[^0-9a-f]"), "")
    require(clean.length % 2 == 0) { "hex length" }
    return ByteArray(clean.length / 2) { i ->
        clean.substring(i * 2, i * 2 + 2).toInt(16).toByte()
    }
}

fun sha256(data: ByteArray): ByteArray =
    MessageDigest.getInstance("SHA-256").digest(data)

fun randomBytes(size: Int): ByteArray {
    val out = ByteArray(size)
    SecureRandom().nextBytes(out)
    return out
}

fun constantTimeEquals(a: ByteArray, b: ByteArray): Boolean {
    if (a.size != b.size) return false
    var r = 0
    for (i in a.indices) r = r or (a[i].toInt() xor b[i].toInt())
    return r == 0
}

@JvmInline
value class UserId(val hex: String) {
    val bytes: ByteArray get() = hex.hexToBytes()

    fun shortLabel(): String = if (hex.length >= 8) hex.substring(0, 8) else hex

    override fun toString(): String = hex

    companion object {
        val GROUP: UserId = UserId(ByteArray(32) { 0xFF.toByte() }.toHex())
        val EMPTY: UserId = UserId(ByteArray(32).toHex())

        fun fromBytes(raw: ByteArray): UserId {
            require(raw.size == 32) { "user id must be 32 bytes" }
            return UserId(raw.toHex())
        }

        fun fromIdentityPublicKey(uncompressed: ByteArray): UserId =
            UserId(sha256(uncompressed).toHex())
    }
}
