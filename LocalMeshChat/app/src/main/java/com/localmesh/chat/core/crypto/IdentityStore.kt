package com.localmesh.chat.core.crypto

import java.security.PrivateKey
import java.security.PublicKey

data class LocalIdentity(
    val userId: UserId,
    val displayName: String,
    val identityPublicKey: ByteArray,
    val keyAgreementPublicKey: ByteArray,
    val createdAtMs: Long,
) {
    override fun equals(other: Any?): Boolean = other is LocalIdentity && userId == other.userId
    override fun hashCode(): Int = userId.hashCode()
}

interface IdentityStore {
    suspend fun load(): LocalIdentity?
    suspend fun create(displayName: String): LocalIdentity
    suspend fun updateDisplayName(name: String): LocalIdentity
    suspend fun delete()
    fun sign(data: ByteArray): ByteArray
    fun identityPublic(): PublicKey
    fun keyAgreementPrivate(): PrivateKey
    fun keyAgreementPublic(): PublicKey
}

class IdentityException(message: String, cause: Throwable? = null) : Exception(message, cause)
