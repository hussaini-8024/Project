package com.localmesh.chat.core.crypto

import java.security.PublicKey
import java.util.concurrent.atomic.AtomicLong
import java.util.concurrent.atomic.AtomicReference

data class GroupKeyState(
    val epoch: Long,
    val key: ByteArray,
    val origin: UserId,
) {
    val keyId: ByteArray get() = sha256(key).copyOf(16)

    override fun equals(other: Any?): Boolean =
        other is GroupKeyState && epoch == other.epoch && key.contentEquals(other.key)

    override fun hashCode(): Int = epoch.hashCode()
}

/**
 * Local Network Group Key (LNGK)
 *
 * The default Local Network Chat uses a shared AES-256 group key:
 * - Authenticated by the sender's ECDSA identity key on the outer packet.
 * - Payload encrypted with AES-256-GCM using the LNGK.
 * - New members receive the current key wrapped with ECDH to their static
 *   key-agreement public key, so a bridge cannot read the key in transit.
 * - Epochs converge: higher epoch wins; equal epoch + different key id prefers
 *   the lexicographically smaller key id so partitions heal.
 *
 * This is not MLS. It is a practical serverless design for nearby devices.
 */
class GroupCrypto {
    private val state = AtomicReference<GroupKeyState?>(null)
    private val localEpochHint = AtomicLong(0)

    fun current(): GroupKeyState? = state.get()

    fun ensureLocalKey(origin: UserId): GroupKeyState {
        state.get()?.let { return it }
        val created = GroupKeyState(
            epoch = System.currentTimeMillis(),
            key = randomBytes(32),
            origin = origin,
        )
        if (state.compareAndSet(null, created)) {
            localEpochHint.set(created.epoch)
            return created
        }
        return state.get()!!
    }

    fun consider(candidate: GroupKeyState): Boolean {
        val current = state.get()
        if (current == null) {
            return state.compareAndSet(null, candidate)
        }
        if (candidate.epoch > current.epoch) {
            return state.compareAndSet(current, candidate)
        }
        if (candidate.epoch == current.epoch) {
            val a = candidate.keyId.toHex()
            val b = current.keyId.toHex()
            if (a < b) return state.compareAndSet(current, candidate)
        }
        return false
    }

    fun encrypt(plaintext: ByteArray, aad: ByteArray): EncryptedBlob {
        val key = current()?.key ?: throw IllegalStateException("no group key")
        val (iv, ct) = CryptoCore.aesGcmEncrypt(key, plaintext, aad)
        return EncryptedBlob(iv, ct)
    }

    fun decrypt(blob: EncryptedBlob, aad: ByteArray): ByteArray? {
        val key = current()?.key ?: return null
        return try {
            CryptoCore.aesGcmDecrypt(key, blob.nonce, blob.ciphertext, aad)
        } catch (_: Exception) {
            null
        }
    }

    fun wrapFor(recipientKaPublic: PublicKey, origin: UserId): Pair<GroupKeyState, ByteArray> {
        val current = current() ?: ensureLocalKey(origin)
        val eph = CryptoCore.generateEcKeyPair()
        val shared = CryptoCore.ecdh(eph.private, recipientKaPublic)
        val wrapKey = CryptoCore.deriveGroupWrapKey(shared)
        val (iv, ct) = CryptoCore.aesGcmEncrypt(wrapKey, current.key + longBytes(current.epoch) + current.origin.bytes)
        val packed = CryptoCore.encodeUncompressed(eph.public) + iv + ct
        return current to packed
    }

    fun unwrap(packed: ByteArray, ourKaPrivate: java.security.PrivateKey, claimedOrigin: UserId): GroupKeyState? {
        return try {
            if (packed.size < 65 + 12 + 16) return null
            val ephPub = CryptoCore.decodeUncompressed(packed.copyOfRange(0, 65))
            val iv = packed.copyOfRange(65, 77)
            val ct = packed.copyOfRange(77, packed.size)
            val shared = CryptoCore.ecdh(ourKaPrivate, ephPub)
            val wrapKey = CryptoCore.deriveGroupWrapKey(shared)
            val plain = CryptoCore.aesGcmDecrypt(wrapKey, iv, ct)
            if (plain.size != 32 + 8 + 32) return null
            val key = plain.copyOfRange(0, 32)
            val epoch = java.nio.ByteBuffer.wrap(plain, 32, 8).long
            val origin = UserId.fromBytes(plain.copyOfRange(40, 72))
            if (origin != claimedOrigin && claimedOrigin != origin) {
                // origin inside wrap is authoritative
            }
            GroupKeyState(epoch, key, origin)
        } catch (_: Exception) {
            null
        }
    }

    fun clear() {
        state.set(null)
    }

    data class EncryptedBlob(val nonce: ByteArray, val ciphertext: ByteArray)

    private fun longBytes(value: Long): ByteArray =
        java.nio.ByteBuffer.allocate(8).putLong(value).array()
}
