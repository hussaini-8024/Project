package com.localmesh.chat.core.crypto

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.localmesh.chat.core.protocol.Protocol
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import java.io.File
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.PrivateKey
import java.security.PublicKey
import java.security.spec.ECGenParameterSpec
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

private val Context.identityDataStore by preferencesDataStore(name = "localmesh_identity")

/**
 * One LocalMesh identity per installation.
 *
 * - ECDSA P-256 signing key lives in Android Keystore and never leaves the device.
 * - Static ECDH P-256 key is generated in software, wrapped with a Keystore AES key,
 *   and stored in the app-private directory. This is required because Keystore ECDH
 *   (KeyAgreement) is only available on API 31+.
 */
class AndroidIdentityStore(
    private val context: Context,
) : IdentityStore {
    private val mutex = Mutex()
    private val keyStore: KeyStore = KeyStore.getInstance(ANDROID_KEYSTORE).apply { load(null) }

    private val displayNameKey = stringPreferencesKey("display_name")
    private val userIdKey = stringPreferencesKey("user_id")
    private val identityPubKey = stringPreferencesKey("identity_pub")
    private val kaPubKey = stringPreferencesKey("ka_pub")
    private val createdAtKey = longPreferencesKey("created_at")

    @Volatile private var cachedKaPrivate: PrivateKey? = null
    @Volatile private var cachedIdentity: LocalIdentity? = null

    override suspend fun load(): LocalIdentity? = mutex.withLock {
        cachedIdentity ?: readIdentity()
    }

    override suspend fun create(displayName: String): LocalIdentity = mutex.withLock {
        val existing = cachedIdentity ?: readIdentity()
        if (existing != null) {
            throw IdentityException("An account already exists on this device")
        }
        val trimmed = displayName.trim()
        require(trimmed.isNotEmpty() && trimmed.length <= Protocol.MAX_DISPLAY_NAME) {
            "Display name required"
        }
        ensureSigningKey()
        val identityPub = identityPublic()
        val identityBytes = CryptoCore.encodeUncompressed(identityPub)
        val kaPair = CryptoCore.generateEcKeyPair()
        persistWrappedKaPrivate(kaPair.private)
        cachedKaPrivate = kaPair.private
        val kaPubBytes = CryptoCore.encodeUncompressed(kaPair.public)
        val userId = UserId.fromIdentityPublicKey(identityBytes)
        val created = System.currentTimeMillis()
        context.identityDataStore.edit { prefs ->
            prefs[displayNameKey] = trimmed
            prefs[userIdKey] = userId.hex
            prefs[identityPubKey] = identityBytes.toHex()
            prefs[kaPubKey] = kaPubBytes.toHex()
            prefs[createdAtKey] = created
        }
        val identity = LocalIdentity(userId, trimmed, identityBytes, kaPubBytes, created)
        cachedIdentity = identity
        identity
    }

    override suspend fun updateDisplayName(name: String): LocalIdentity = mutex.withLock {
        val current = cachedIdentity ?: readIdentity() ?: throw IdentityException("No account")
        val trimmed = name.trim()
        require(trimmed.isNotEmpty() && trimmed.length <= Protocol.MAX_DISPLAY_NAME)
        context.identityDataStore.edit { it[displayNameKey] = trimmed }
        val updated = current.copy(displayName = trimmed)
        cachedIdentity = updated
        updated
    }

    override suspend fun delete() {
        mutex.withLock {
            cachedIdentity = null
            cachedKaPrivate = null
            try {
                if (keyStore.containsAlias(SIGN_ALIAS)) keyStore.deleteEntry(SIGN_ALIAS)
            } catch (_: Exception) {
            }
            try {
                if (keyStore.containsAlias(WRAP_ALIAS)) keyStore.deleteEntry(WRAP_ALIAS)
            } catch (_: Exception) {
            }
            wrapFile().delete()
            context.identityDataStore.edit { it.clear() }
        }
    }

    override fun sign(data: ByteArray): ByteArray {
        val private = signingPrivate()
        return CryptoCore.sign(private, data)
    }

    override fun identityPublic(): PublicKey {
        val entry = keyStore.getEntry(SIGN_ALIAS, null) as KeyStore.PrivateKeyEntry
        return entry.certificate.publicKey
    }

    override fun keyAgreementPrivate(): PrivateKey {
        cachedKaPrivate?.let { return it }
        val loaded = unwrapKaPrivate()
        cachedKaPrivate = loaded
        return loaded
    }

    override fun keyAgreementPublic(): PublicKey {
        val identity = cachedIdentity ?: throw IdentityException("No account")
        return CryptoCore.decodeUncompressed(identity.keyAgreementPublicKey)
    }

    private suspend fun readIdentity(): LocalIdentity? {
        val prefs = context.identityDataStore.data.first()
        val userId = prefs[userIdKey] ?: return null
        val name = prefs[displayNameKey] ?: return null
        val idPub = prefs[identityPubKey] ?: return null
        val kaPub = prefs[kaPubKey] ?: return null
        val created = prefs[createdAtKey] ?: 0L
        if (!keyStore.containsAlias(SIGN_ALIAS) || !wrapFile().exists()) {
            return null
        }
        val identity = LocalIdentity(
            userId = UserId(userId),
            displayName = name,
            identityPublicKey = idPub.hexToBytes(),
            keyAgreementPublicKey = kaPub.hexToBytes(),
            createdAtMs = created,
        )
        cachedIdentity = identity
        return identity
    }

    private fun ensureSigningKey() {
        if (keyStore.containsAlias(SIGN_ALIAS)) return
        val gen = KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, ANDROID_KEYSTORE)
        val spec = KeyGenParameterSpec.Builder(
            SIGN_ALIAS,
            KeyProperties.PURPOSE_SIGN or KeyProperties.PURPOSE_VERIFY,
        )
            .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
            .setDigests(KeyProperties.DIGEST_SHA256)
            .setUserAuthenticationRequired(false)
            .build()
        gen.initialize(spec)
        gen.generateKeyPair()
    }

    private fun signingPrivate(): PrivateKey {
        val entry = keyStore.getEntry(SIGN_ALIAS, null) as KeyStore.PrivateKeyEntry
        return entry.privateKey
    }

    private fun wrapKey(): SecretKey {
        if (!keyStore.containsAlias(WRAP_ALIAS)) {
            val gen = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, ANDROID_KEYSTORE)
            val spec = KeyGenParameterSpec.Builder(
                WRAP_ALIAS,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
            )
                .setKeySize(256)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setUserAuthenticationRequired(false)
                .build()
            gen.init(spec)
            gen.generateKey()
        }
        return (keyStore.getEntry(WRAP_ALIAS, null) as KeyStore.SecretKeyEntry).secretKey
    }

    private fun persistWrappedKaPrivate(privateKey: PrivateKey) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, wrapKey())
        val iv = cipher.iv
        val ct = cipher.doFinal(privateKey.encoded)
        val packed = ByteArray(1 + iv.size + ct.size)
        packed[0] = iv.size.toByte()
        System.arraycopy(iv, 0, packed, 1, iv.size)
        System.arraycopy(ct, 0, packed, 1 + iv.size, ct.size)
        wrapFile().writeBytes(packed)
    }

    private fun unwrapKaPrivate(): PrivateKey {
        val packed = wrapFile().readBytes()
        val ivLen = packed[0].toInt() and 0xFF
        val iv = packed.copyOfRange(1, 1 + ivLen)
        val ct = packed.copyOfRange(1 + ivLen, packed.size)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, wrapKey(), GCMParameterSpec(128, iv))
        val pkcs8 = cipher.doFinal(ct)
        return CryptoCore.decodePrivatePkcs8(pkcs8)
    }

    private fun wrapFile(): File = File(context.filesDir, "ka_private.wrap")

    companion object {
        private const val ANDROID_KEYSTORE = "AndroidKeyStore"
        private const val SIGN_ALIAS = "localmesh_identity_ecdsa"
        private const val WRAP_ALIAS = "localmesh_ka_wrap_aes"
    }
}
