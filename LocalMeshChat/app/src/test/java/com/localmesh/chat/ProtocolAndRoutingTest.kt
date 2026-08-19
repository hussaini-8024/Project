package com.localmesh.chat

import com.google.common.truth.Truth.assertThat
import com.localmesh.chat.core.crypto.CryptoCore
import com.localmesh.chat.core.crypto.GroupCrypto
import com.localmesh.chat.core.crypto.SoftwareIdentityStore
import com.localmesh.chat.core.crypto.UserId
import com.localmesh.chat.core.crypto.randomBytes
import com.localmesh.chat.core.protocol.MeshPacket
import com.localmesh.chat.core.protocol.PacketCodec
import com.localmesh.chat.core.protocol.PacketFactory
import com.localmesh.chat.core.protocol.PacketType
import com.localmesh.chat.core.protocol.Protocol
import com.localmesh.chat.core.routing.DuplicateCache
import com.localmesh.chat.core.routing.MeshDiagnostics
import com.localmesh.chat.core.routing.MeshRouter
import com.localmesh.chat.core.routing.RoutingTable
import com.localmesh.chat.core.security.PacketValidator
import com.localmesh.chat.core.security.ValidationResult
import com.localmesh.chat.data.repositories.InMemorySessionStore
import com.localmesh.chat.domain.connections.TransportKind
import com.localmesh.chat.domain.messaging.ChatRequest
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import kotlinx.coroutines.runBlocking
import org.junit.Test

class PacketCodecTest {
    @Test
    fun roundTripSignedPacket() = runBlocking {
        val store = SoftwareIdentityStore()
        val identity = store.create("Ali")
        val factory = PacketFactory(store) { identity.userId }
        val packet = factory.build(
            type = PacketType.GROUP_MSG,
            destination = UserId.GROUP,
            payload = "hello".toByteArray(),
        )
        val encoded = PacketCodec.encodeUnsigned(packet)
        val decoded = PacketCodec.decode(encoded)
        assertThat(decoded.type).isEqualTo(PacketType.GROUP_MSG)
        assertThat(decoded.source).isEqualTo(identity.userId)
        assertThat(decoded.payload.toString(Charsets.UTF_8)).isEqualTo("hello")
        assertThat(
            CryptoCore.verify(
                CryptoCore.decodeUncompressed(identity.identityPublicKey),
                PacketCodec.signedRegion(decoded),
                decoded.signature,
            ),
        ).isTrue()
    }

    @Test
    fun rejectsBadMagic() {
        val junk = ByteArray(120) { 7 }
        try {
            PacketCodec.decode(junk)
            throw AssertionError("expected failure")
        } catch (e: PacketCodec.CodecException) {
            assertThat(e.message).contains("magic")
        }
    }

    @Test
    fun rejectsOversizedPayloadDeclaration() {
        try {
            PacketCodec.decode(ByteArray(Protocol.MAX_PACKET_BYTES + 1))
            throw AssertionError("expected failure")
        } catch (e: PacketCodec.CodecException) {
            assertThat(e.message).isNotEmpty()
        }
    }
}

class CryptoTest {
    @Test
    fun aesGcmRoundTrip() {
        val key = randomBytes(32)
        val (iv, ct) = CryptoCore.aesGcmEncrypt(key, "secret".toByteArray(), "aad".toByteArray())
        val plain = CryptoCore.aesGcmDecrypt(key, iv, ct, "aad".toByteArray())
        assertThat(plain.toString(Charsets.UTF_8)).isEqualTo("secret")
    }

    @Test
    fun ecdhIsSymmetric() {
        val a = CryptoCore.generateEcKeyPair()
        val b = CryptoCore.generateEcKeyPair()
        val ab = CryptoCore.ecdh(a.private, b.public)
        val ba = CryptoCore.ecdh(b.private, a.public)
        assertThat(ab).isEqualTo(ba)
    }

    @Test
    fun groupKeyWrapIsEndToEnd() = runBlocking {
        val alice = SoftwareIdentityStore().also { it.create("Alice") }
        val bob = SoftwareIdentityStore().also { it.create("Bob") }
        val group = GroupCrypto()
        group.ensureLocalKey(alice.load()!!.userId)
        val packed = group.wrapFor(bob.keyAgreementPublic(), alice.load()!!.userId).second
        val other = GroupCrypto()
        val unwrapped = other.unwrap(packed, bob.keyAgreementPrivate(), alice.load()!!.userId)
        assertThat(unwrapped).isNotNull()
        assertThat(unwrapped!!.key).isEqualTo(group.current()!!.key)
    }
}

class RoutingTest {
    @Test
    fun duplicatePacketIsDropped() {
        val id = UserId.fromBytes(ByteArray(32) { 1 })
        val router = MeshRouter({ id }, RoutingTable(), DuplicateCache(), MeshDiagnostics())
        val packet = samplePacket(id)
        val first = router.ingest(packet, UserId.fromBytes(ByteArray(32) { 2 }), TransportKind.WIFI)
        val second = router.ingest(packet, UserId.fromBytes(ByteArray(32) { 2 }), TransportKind.WIFI)
        assertThat(first.duplicate).isFalse()
        assertThat(first.deliverLocal).isTrue()
        assertThat(second.duplicate).isTrue()
        assertThat(second.deliverLocal).isFalse()
    }

    @Test
    fun ttlPreventsInfiniteForward() {
        val local = UserId.fromBytes(ByteArray(32) { 1 })
        val router = MeshRouter({ local }, RoutingTable(), DuplicateCache(), MeshDiagnostics())
        val dest = UserId.fromBytes(ByteArray(32) { 9 })
        val packet = samplePacket(local).copy(destination = dest, ttl = 1, source = UserId.fromBytes(ByteArray(32) { 3 }))
        val result = router.ingest(packet, UserId.fromBytes(ByteArray(32) { 3 }), TransportKind.WIFI)
        assertThat(result.forwardPacket).isNull()
        assertThat(result.deliverLocal).isFalse()
    }

    @Test
    fun groupMessageIsDeliveredAndFlooded() {
        val local = UserId.fromBytes(ByteArray(32) { 1 })
        val router = MeshRouter({ local }, RoutingTable(), DuplicateCache(), MeshDiagnostics())
        val packet = samplePacket(UserId.fromBytes(ByteArray(32) { 4 })).copy(destination = UserId.GROUP, ttl = 8)
        val result = router.ingest(packet, UserId.fromBytes(ByteArray(32) { 4 }), TransportKind.BLUETOOTH)
        assertThat(result.deliverLocal).isTrue()
        assertThat(result.flood).isTrue()
        assertThat(result.forwardPacket!!.ttl).isEqualTo(7)
    }

    private fun samplePacket(source: UserId) = MeshPacket(
        version = Protocol.VERSION,
        type = PacketType.GROUP_MSG,
        flags = 0,
        ttl = 8,
        messageId = randomBytes(16),
        timestampMs = System.currentTimeMillis(),
        source = source,
        destination = UserId.GROUP,
        payload = ByteArray(0),
        signature = ByteArray(8),
    )
}

class ValidatorTest {
    @Test
    fun rejectsExpiredPacket() {
        val validator = PacketValidator { 1_000_000L }
        val packet = MeshPacket(
            version = Protocol.VERSION,
            type = PacketType.PING,
            flags = 0,
            ttl = 4,
            messageId = randomBytes(16),
            timestampMs = 1L,
            source = UserId.fromBytes(ByteArray(32) { 1 }),
            destination = UserId.GROUP,
            payload = ByteArray(0),
            signature = ByteArray(8),
        )
        assertThat(validator.validateStructure(packet)).isInstanceOf(ValidationResult.Reject::class.java)
    }

    @Test
    fun rejectsUnsupportedVersion() {
        val validator = PacketValidator()
        val packet = MeshPacket(
            version = 99,
            type = PacketType.PING,
            flags = 0,
            ttl = 4,
            messageId = randomBytes(16),
            timestampMs = System.currentTimeMillis(),
            source = UserId.fromBytes(ByteArray(32) { 1 }),
            destination = UserId.GROUP,
            payload = ByteArray(0),
            signature = ByteArray(8),
        )
        assertThat(validator.validateStructure(packet)).isInstanceOf(ValidationResult.Reject::class.java)
    }
}

class ChatRequestStoreTest {
    @Test
    fun rejectDoesNotOpenPrivateChat() {
        val store = InMemorySessionStore()
        val ali = UserId.fromBytes(ByteArray(32) { 1 })
        val ahmed = UserId.fromBytes(ByteArray(32) { 2 })
        store.upsertRequest(
            ChatRequest("req1", ali, "Ali", ahmed, ChatRequestStatus.INCOMING, 1L),
        )
        store.upsertRequest(
            ChatRequest("req1", ali, "Ali", ahmed, ChatRequestStatus.REJECTED, 2L),
        )
        assertThat(store.isPrivateOpen(ali, ahmed)).isFalse()
    }

    @Test
    fun acceptOpensPrivateChat() {
        val store = InMemorySessionStore()
        val ali = UserId.fromBytes(ByteArray(32) { 1 })
        val ahmed = UserId.fromBytes(ByteArray(32) { 2 })
        store.upsertRequest(
            ChatRequest("req1", ali, "Ali", ahmed, ChatRequestStatus.ACCEPTED, 1L),
        )
        assertThat(store.isPrivateOpen(ali, ahmed)).isTrue()
    }
}

class DuplicateCacheTest {
    @Test
    fun remembersIds() {
        val cache = DuplicateCache()
        val id = randomBytes(16)
        assertThat(cache.remember(id)).isFalse()
        assertThat(cache.remember(id)).isTrue()
        assertThat(cache.isDuplicate(id)).isTrue()
    }
}
