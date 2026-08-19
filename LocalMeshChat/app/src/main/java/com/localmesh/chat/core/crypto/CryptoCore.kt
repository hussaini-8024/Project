package com.localmesh.chat.core.crypto

import java.math.BigInteger
import java.security.KeyFactory
import java.security.KeyPair
import java.security.KeyPairGenerator
import java.security.PrivateKey
import java.security.PublicKey
import java.security.Signature
import java.security.interfaces.ECPublicKey
import java.security.spec.ECGenParameterSpec
import java.security.spec.ECPoint
import java.security.spec.ECPublicKeySpec
import java.security.spec.PKCS8EncodedKeySpec
import java.security.spec.X509EncodedKeySpec
import javax.crypto.Cipher
import javax.crypto.KeyAgreement
import javax.crypto.Mac
import javax.crypto.spec.GCMParameterSpec
import javax.crypto.spec.SecretKeySpec

/**
 * Standard JCA cryptography used by LocalMesh.
 *
 * Identity signatures: ECDSA P-256 + SHA-256 (DER).
 * Key agreement: ephemeral/static ECDH P-256.
 * Messages: AES-256-GCM (96-bit nonce, 128-bit tag).
 * KDF: HKDF-SHA256.
 */
object CryptoCore {
    const val AES_GCM_IV_BYTES = 12
    const val AES_GCM_TAG_BITS = 128
    const val AES_KEY_BYTES = 32
    const val UNCOMPRESSED_EC_BYTES = 65

    fun generateEcKeyPair(): KeyPair {
        val gen = KeyPairGenerator.getInstance("EC")
        gen.initialize(ECGenParameterSpec("secp256r1"))
        return gen.generateKeyPair()
    }

    fun encodeUncompressed(publicKey: PublicKey): ByteArray {
        val ec = publicKey as ECPublicKey
        val x = toUnsigned(ec.w.affineX, 32)
        val y = toUnsigned(ec.w.affineY, 32)
        return byteArrayOf(0x04) + x + y
    }

    fun decodeUncompressed(bytes: ByteArray): PublicKey {
        require(bytes.size == UNCOMPRESSED_EC_BYTES && bytes[0] == 0x04.toByte()) {
            "expected uncompressed P-256 point"
        }
        val x = BigInteger(1, bytes.copyOfRange(1, 33))
        val y = BigInteger(1, bytes.copyOfRange(33, 65))
        val params = (generateEcKeyPair().public as ECPublicKey).params
        val spec = ECPublicKeySpec(ECPoint(x, y), params)
        return KeyFactory.getInstance("EC").generatePublic(spec)
    }

    fun encodePrivatePkcs8(privateKey: PrivateKey): ByteArray = privateKey.encoded

    fun decodePrivatePkcs8(bytes: ByteArray): PrivateKey =
        KeyFactory.getInstance("EC").generatePrivate(PKCS8EncodedKeySpec(bytes))

    fun encodePublicX509(publicKey: PublicKey): ByteArray = publicKey.encoded

    fun decodePublicX509(bytes: ByteArray): PublicKey =
        KeyFactory.getInstance("EC").generatePublic(X509EncodedKeySpec(bytes))

    fun sign(privateKey: PrivateKey, data: ByteArray): ByteArray {
        val sig = Signature.getInstance("SHA256withECDSA")
        sig.initSign(privateKey)
        sig.update(data)
        return sig.sign()
    }

    fun verify(publicKey: PublicKey, data: ByteArray, signature: ByteArray): Boolean {
        return try {
            val sig = Signature.getInstance("SHA256withECDSA")
            sig.initVerify(publicKey)
            sig.update(data)
            sig.verify(signature)
        } catch (_: Exception) {
            false
        }
    }

    fun ecdh(privateKey: PrivateKey, peerPublic: PublicKey): ByteArray {
        val ka = KeyAgreement.getInstance("ECDH")
        ka.init(privateKey)
        ka.doPhase(peerPublic, true)
        return ka.generateSecret()
    }

    fun hkdf(ikm: ByteArray, salt: ByteArray, info: ByteArray, length: Int): ByteArray {
        val prk = hmacSha256(if (salt.isEmpty()) ByteArray(32) else salt, ikm)
        val result = ByteArray(length)
        var t = ByteArray(0)
        var offset = 0
        var counter = 1
        while (offset < length) {
            val mac = Mac.getInstance("HmacSHA256")
            mac.init(SecretKeySpec(prk, "HmacSHA256"))
            mac.update(t)
            mac.update(info)
            mac.update(counter.toByte())
            t = mac.doFinal()
            val copy = minOf(t.size, length - offset)
            System.arraycopy(t, 0, result, offset, copy)
            offset += copy
            counter++
        }
        return result
    }

    fun hmacSha256(key: ByteArray, data: ByteArray): ByteArray {
        val mac = Mac.getInstance("HmacSHA256")
        mac.init(SecretKeySpec(key, "HmacSHA256"))
        return mac.doFinal(data)
    }

    fun aesGcmEncrypt(key: ByteArray, plaintext: ByteArray, aad: ByteArray = ByteArray(0)): Pair<ByteArray, ByteArray> {
        require(key.size == AES_KEY_BYTES)
        val iv = randomBytes(AES_GCM_IV_BYTES)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, SecretKeySpec(key, "AES"), GCMParameterSpec(AES_GCM_TAG_BITS, iv))
        if (aad.isNotEmpty()) cipher.updateAAD(aad)
        return iv to cipher.doFinal(plaintext)
    }

    fun aesGcmDecrypt(key: ByteArray, iv: ByteArray, ciphertext: ByteArray, aad: ByteArray = ByteArray(0)): ByteArray {
        require(key.size == AES_KEY_BYTES)
        require(iv.size == AES_GCM_IV_BYTES)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, SecretKeySpec(key, "AES"), GCMParameterSpec(AES_GCM_TAG_BITS, iv))
        if (aad.isNotEmpty()) cipher.updateAAD(aad)
        return cipher.doFinal(ciphertext)
    }

    fun deriveLinkKeys(sharedSecret: ByteArray, transcript: ByteArray): LinkKeys {
        val okm = hkdf(sharedSecret, transcript, "localmesh-link-v1".toByteArray(), 64)
        return LinkKeys(
            sendKey = okm.copyOfRange(0, 32),
            recvKey = okm.copyOfRange(32, 64),
        )
    }

    fun derivePrivateSessionKey(sharedSecret: ByteArray, userA: UserId, userB: UserId): ByteArray {
        val ordered = listOf(userA.hex, userB.hex).sorted().joinToString("|")
        return hkdf(sharedSecret, ordered.toByteArray(), "localmesh-private-v1".toByteArray(), 32)
    }

    fun deriveGroupWrapKey(sharedSecret: ByteArray): ByteArray =
        hkdf(sharedSecret, "localmesh-gkw".toByteArray(), "wrap".toByteArray(), 32)

    private fun toUnsigned(value: BigInteger, size: Int): ByteArray {
        val raw = value.toByteArray()
        val unsigned = if (raw.isNotEmpty() && raw[0] == 0.toByte()) raw.copyOfRange(1, raw.size) else raw
        if (unsigned.size == size) return unsigned
        if (unsigned.size > size) return unsigned.copyOfRange(unsigned.size - size, unsigned.size)
        val out = ByteArray(size)
        System.arraycopy(unsigned, 0, out, size - unsigned.size, unsigned.size)
        return out
    }

    data class LinkKeys(val sendKey: ByteArray, val recvKey: ByteArray)
}
