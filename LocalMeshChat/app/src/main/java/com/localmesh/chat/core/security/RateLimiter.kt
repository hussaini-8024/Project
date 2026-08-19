package com.localmesh.chat.core.security

import com.localmesh.chat.core.crypto.UserId

/**
 * Token-bucket rate limiter per peer. Protects against flood/DoS from a single neighbor.
 */
class RateLimiter(
    private val capacity: Int = 40,
    private val refillPerSecond: Double = 20.0,
    private val clock: () -> Long = { System.currentTimeMillis() },
) {
    private data class Bucket(var tokens: Double, var lastMs: Long)

    private val buckets = LinkedHashMap<String, Bucket>(64, 0.75f, true)

    @Synchronized
    fun allow(peer: UserId): Boolean {
        val now = clock()
        val bucket = buckets.getOrPut(peer.hex) { Bucket(capacity.toDouble(), now) }
        val elapsed = (now - bucket.lastMs).coerceAtLeast(0) / 1000.0
        bucket.tokens = (bucket.tokens + elapsed * refillPerSecond).coerceAtMost(capacity.toDouble())
        bucket.lastMs = now
        if (bucket.tokens < 1.0) return false
        bucket.tokens -= 1.0
        while (buckets.size > 256) {
            val oldest = buckets.entries.first().key
            buckets.remove(oldest)
        }
        return true
    }
}
