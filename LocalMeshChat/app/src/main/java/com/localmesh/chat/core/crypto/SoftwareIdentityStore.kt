package com.localmesh.chat.core.crypto

import java.security.PrivateKey
import java.security.PublicKey

/** In-memory identity used by JVM unit tests. */
class SoftwareIdentityStore : IdentityStore {
    private var identity: LocalIdentity? = null
    private var signPrivate: PrivateKey? = null
    private var kaPrivate: PrivateKey? = null
    private var signPublic: PublicKey? = null
    private var kaPublic: PublicKey? = null

    override suspend fun load(): LocalIdentity? = identity

    override suspend fun create(displayName: String): LocalIdentity {
        check(identity == null) { "account exists" }
        val sign = CryptoCore.generateEcKeyPair()
        val ka = CryptoCore.generateEcKeyPair()
        signPrivate = sign.private
        signPublic = sign.public
        kaPrivate = ka.private
        kaPublic = ka.public
        val idPub = CryptoCore.encodeUncompressed(sign.public)
        val created = LocalIdentity(
            userId = UserId.fromIdentityPublicKey(idPub),
            displayName = displayName.trim(),
            identityPublicKey = idPub,
            keyAgreementPublicKey = CryptoCore.encodeUncompressed(ka.public),
            createdAtMs = System.currentTimeMillis(),
        )
        identity = created
        return created
    }

    override suspend fun updateDisplayName(name: String): LocalIdentity {
        val current = identity ?: error("no account")
        val updated = current.copy(displayName = name.trim())
        identity = updated
        return updated
    }

    override suspend fun delete() {
        identity = null
        signPrivate = null
        kaPrivate = null
        signPublic = null
        kaPublic = null
    }

    override fun sign(data: ByteArray): ByteArray = CryptoCore.sign(signPrivate!!, data)

    override fun identityPublic(): PublicKey = signPublic!!

    override fun keyAgreementPrivate(): PrivateKey = kaPrivate!!

    override fun keyAgreementPublic(): PublicKey = kaPublic!!
}
