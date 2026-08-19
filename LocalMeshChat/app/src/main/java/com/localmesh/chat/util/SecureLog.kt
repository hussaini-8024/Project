package com.localmesh.chat.util

import android.util.Log
import com.localmesh.chat.BuildConfig

/**
 * Logging that never accepts message bodies, keys, or session secrets.
 */
object SecureLog {
    private const val TAG = "LocalMesh"

    fun i(message: String) {
        if (BuildConfig.DEBUG) Log.i(TAG, sanitize(message))
    }

    fun w(message: String) {
        Log.w(TAG, sanitize(message))
    }

    fun e(message: String, error: Throwable? = null) {
        if (BuildConfig.DEBUG) {
            Log.e(TAG, sanitize(message), error)
        } else {
            Log.e(TAG, sanitize(message))
        }
    }

    private fun sanitize(message: String): String =
        message
            .replace(Regex("(?i)(key|secret|token|password)\\s*=\\s*\\S+"), "$1=*")
            .take(240)
}
