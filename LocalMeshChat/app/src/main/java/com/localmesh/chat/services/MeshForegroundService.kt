package com.localmesh.chat.services

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import com.localmesh.chat.LocalMeshApp
import com.localmesh.chat.MainActivity
import com.localmesh.chat.R
import com.localmesh.chat.core.storage.AppSettings
import com.localmesh.chat.domain.messaging.ChatRequestStatus
import com.localmesh.chat.util.SecureLog
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.launch

class MeshForegroundService : Service() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Default)
    private var jobs: List<Job> = emptyList()
    private var lastGroupId: String? = null
    private var lastRequestId: String? = null
    private var lastPrivateId: String? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        createChannel()
        val notification = statusNotification("Discovering nearby LocalMesh devices")
        if (Build.VERSION.SDK_INT >= 34) {
            ServiceCompat.startForeground(
                this,
                NOTIFY_STATUS,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_CONNECTED_DEVICE,
            )
        } else {
            startForeground(NOTIFY_STATUS, notification)
        }
        observe()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int = START_STICKY

    override fun onDestroy() {
        jobs.forEach { it.cancel() }
        scope.cancel()
        super.onDestroy()
    }

    private fun observe() {
        val app = application as LocalMeshApp
        val container = app.container
        jobs = listOf(
            scope.launch {
                combine(container.engine, container.settings.settings) { engine, settings ->
                    engine to settings
                }.collectLatest { (engine, settings) ->
                    engine ?: return@collectLatest
                    engine.applySettings(settings)
                    val wifi = settings.wifiEnabled
                    val bt = settings.bluetoothEnabled
                    engine.start(wifi, bt)
                    engine.capabilities.collect { caps ->
                        val text = buildString {
                            append("LocalMesh ")
                            if (caps.actingAsBridge) append("· BRIDGE ACTIVE")
                            else if (caps.wifiEnabled && caps.bluetoothEnabled) append("· Wi-Fi + Bluetooth")
                            else if (caps.wifiEnabled) append("· Wi-Fi")
                            else if (caps.bluetoothEnabled) append("· Bluetooth")
                            else append("· Connecting")
                        }
                        notifyStatus(text)
                    }
                }
            },
            scope.launch {
                combine(container.session.groupMessages, container.settings.settings) { messages, settings ->
                    messages to settings
                }.collect { (messages, settings) ->
                    val last = messages.lastOrNull() ?: return@collect
                    if (last.id == lastGroupId || last.fromSelf) return@collect
                    lastGroupId = last.id
                    notifyIncoming(settings, "New LocalMesh message")
                }
            },
            scope.launch {
                combine(container.session.chatRequests, container.settings.settings) { reqs, settings ->
                    reqs to settings
                }.collect { (reqs, settings) ->
                    val incoming = reqs.lastOrNull { it.status == ChatRequestStatus.INCOMING } ?: return@collect
                    if (incoming.requestId == lastRequestId) return@collect
                    lastRequestId = incoming.requestId
                    val body = if (settings.notificationPreview) {
                        "${incoming.fromName} wants to start a private chat."
                    } else {
                        "New LocalMesh message"
                    }
                    notifyIncoming(settings, body)
                }
            },
        )
    }

    private fun notifyStatus(text: String) {
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(NOTIFY_STATUS, statusNotification(text))
    }

    private fun notifyIncoming(settings: AppSettings, text: String) {
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        val content = if (settings.notificationPreview) text else "New LocalMesh message"
        val notification = NotificationCompat.Builder(this, CHANNEL_INCOMING)
            .setSmallIcon(R.drawable.ic_mesh)
            .setContentTitle("LocalMesh Chat")
            .setContentText(content)
            .setContentIntent(openApp())
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
        manager.notify(NOTIFY_INCOMING, notification)
    }

    private fun statusNotification(text: String): Notification =
        NotificationCompat.Builder(this, CHANNEL_STATUS)
            .setSmallIcon(R.drawable.ic_mesh)
            .setContentTitle("LocalMesh Chat")
            .setContentText(text)
            .setContentIntent(openApp())
            .setOngoing(true)
            .setSilent(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()

    private fun openApp(): PendingIntent {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        return PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
    }

    private fun createChannel() {
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(
            NotificationChannel(CHANNEL_STATUS, "Mesh connection", NotificationManager.IMPORTANCE_LOW),
        )
        manager.createNotificationChannel(
            NotificationChannel(CHANNEL_INCOMING, "Incoming chats", NotificationManager.IMPORTANCE_DEFAULT),
        )
    }

    companion object {
        private const val CHANNEL_STATUS = "localmesh_status"
        private const val CHANNEL_INCOMING = "localmesh_incoming"
        private const val NOTIFY_STATUS = 41
        private const val NOTIFY_INCOMING = 42

        fun start(context: Context) {
            val intent = Intent(context, MeshForegroundService::class.java)
            try {
                if (Build.VERSION.SDK_INT >= 26) {
                    context.startForegroundService(intent)
                } else {
                    context.startService(intent)
                }
            } catch (e: Exception) {
                SecureLog.w("unable to start mesh service")
            }
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, MeshForegroundService::class.java))
        }
    }
}
