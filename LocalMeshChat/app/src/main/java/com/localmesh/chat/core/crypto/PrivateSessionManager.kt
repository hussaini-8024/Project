package com.localmesh.chat.core.crypto

import java.security.PublicKey
import java.util.concurrent.ConcurrentHashMap

/**
 * Pairwise private-chat sessions. Keys are derived with ECDH between our static
 * key-agreement private key and the peer's advertised key-agreement public key,
 * then HKDF. The bridge sees only AES-GCM ciphertext of the chat body.
 */
class PrivateSessionManager(
    private val identityStore: IdentityStore,
) {
    private val keys = ConcurrentHashMap<String, ByteArray>()

    fun hasSession(peer: UserId): Boolean = keys.containsKey(peer.hex)

    fun establish(peer: UserId, peerKaPublic: PublicKey) {
        val shared = CryptoCore.ecdh(identityStore.keyAgreementPrivate(), peerKaPublic)
        val local = identityStore.loadBlockingUserId()
        val key = CryptoCore.derivePrivateSessionKey(shared, local, peer)
        keys[peer.hex] = key
    }

    fun drop(peer: UserId) {
        keys.remove(peer.hex)
    }

    fun clear() {
        keys.clear()
    }

    fun encrypt(peer: UserId, plaintext: ByteArray, aad: ByteArray): Pair<ByteArray, ByteArray> {
        val key = keys[peer.hex] ?: throw IllegalStateException("no private session")
        return CryptoCore.aesGcmEncrypt(key, plaintext, aad)
    }

    fun decrypt(peer: UserId, nonce: ByteArray, ciphertext: ByteArray, aad: ByteArray): ByteArray? {
        val key = keys[peer.hex] ?: return null
        return try {
            CryptoCore.aesGcmDecrypt(key, nonce, ciphertext, aad)
        } catch (_: Exception) {
            null
        }
    }

    private fun IdentityStore.loadBlockingUserId(): UserId {
        // Identity is always loaded before mesh starts; public key is stable.
        return UserId.fromIdentityPublicKey(CryptoCore.encodeUncompressed(identityPublic()))
    }
}
