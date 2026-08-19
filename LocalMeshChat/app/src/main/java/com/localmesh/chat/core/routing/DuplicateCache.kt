package com.localmesh.chat.core.routing

import com.localmesh.chat.core.crypto.toHex
import com.localmesh.chat.core.protocol.Protocol

/**
 * Sliding cache of seen message IDs used for duplicate suppression and loop prevention.
 */
class DuplicateCache(
    private val ttlMs: Long = Protocol.DUPLICATE_TTL_MS,
    private val maxEntries: Int = 4096,
    private val clock: () -> Long = { System.currentTimeMillis() },
) {
    private data class Entry(val seenAt: Long)

    private val seen = LinkedHashMap<String, Entry>(maxEntries, 0.75f, true)

    @Synchronized
    fun isDuplicate(messageId: ByteArray): Boolean {
        evict()
        return seen.containsKey(messageId.toHex())
    }

    @Synchronized
    fun remember(messageId: ByteArray): Boolean {
        evict()
        val key = messageId.toHex()
        if (seen.containsKey(key)) return true
        if (seen.size >= maxEntries) {
            val first = seen.keys.first()
            seen.remove(first)
        }
        seen[key] = Entry(clock())
        return false
    }

    @Synchronized
    fun size(): Int = seen.size

    private fun evict() {
        val now = clock()
        val iterator = seen.entries.iterator()
        while (iterator.hasNext()) {
            val entry = iterator.next()
            if (now - entry.value.seenAt > ttlMs) iterator.remove() else break
        }
    }
}
