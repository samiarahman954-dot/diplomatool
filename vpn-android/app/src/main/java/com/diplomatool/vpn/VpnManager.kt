package com.diplomatool.vpn

import android.content.Context
import com.wireguard.android.backend.GoBackend
import com.wireguard.android.backend.Tunnel
import com.wireguard.config.Config
import com.wireguard.crypto.Key
import com.wireguard.crypto.KeyPair
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.io.ByteArrayInputStream
import java.io.IOException

/** Process-wide owner of the WireGuard backend and the single tunnel this app manages. */
object VpnManager {
    enum class Status { DISCONNECTED, CONNECTING, CONNECTED }

    private const val TUNNEL_NAME = "securevpn"

    private lateinit var app: Context
    private val backend by lazy { GoBackend(app) }
    private val mutex = Mutex()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    // True while we drive the tunnel ourselves, so backend callbacks don't fight our own state updates.
    @Volatile
    private var busy = false

    private val _status = MutableStateFlow(Status.DISCONNECTED)
    val status: StateFlow<Status> = _status.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    val connectedAt: Long get() = Prefs.connectedAt
    val connectedServerId: String? get() = Prefs.connectedServerId

    private val tunnel = object : Tunnel {
        override fun getName(): String = TUNNEL_NAME
        override fun onStateChange(newState: Tunnel.State) {
            // Fires when the system or another VPN app tears our tunnel down.
            if (!busy && newState == Tunnel.State.DOWN) markDown()
        }
    }

    fun init(context: Context) {
        app = context.applicationContext
    }

    fun connectAsync(server: Server) {
        scope.launch {
            try {
                connect(server)
            } catch (e: Exception) {
                _error.value = app.getString(R.string.error_connect, e.message ?: e.javaClass.simpleName)
            }
        }
    }

    fun disconnectAsync() {
        scope.launch { runCatching { disconnect() } }
    }

    fun clearError() {
        _error.value = null
    }

    suspend fun connect(server: Server) = withContext(Dispatchers.IO) {
        mutex.withLock {
            busy = true
            _status.value = Status.CONNECTING
            try {
                if (backend.getState(tunnel) == Tunnel.State.UP) {
                    backend.setState(tunnel, Tunnel.State.DOWN, null)
                }
                backend.setState(tunnel, Tunnel.State.UP, buildConfig(server))
                Prefs.connectedAt = System.currentTimeMillis()
                Prefs.connectedServerId = server.id
                _status.value = Status.CONNECTED
                VpnNotifier.show(app, server, Prefs.connectedAt)
            } catch (e: Exception) {
                runCatching { backend.setState(tunnel, Tunnel.State.DOWN, null) }
                markDown()
                throw e
            } finally {
                busy = false
            }
        }
    }

    suspend fun disconnect() = withContext(Dispatchers.IO) {
        mutex.withLock {
            busy = true
            try {
                backend.setState(tunnel, Tunnel.State.DOWN, null)
            } finally {
                busy = false
                markDown()
            }
        }
    }

    suspend fun refresh() = withContext(Dispatchers.IO) {
        mutex.withLock {
            if (backend.getState(tunnel) == Tunnel.State.UP) {
                if (Prefs.connectedAt == 0L) Prefs.connectedAt = System.currentTimeMillis()
                _status.value = Status.CONNECTED
            } else {
                markDown()
            }
        }
    }

    /** Returns total (received, sent) bytes, or null when the tunnel is down. */
    suspend fun traffic(): Pair<Long, Long>? = withContext(Dispatchers.IO) {
        if (_status.value != Status.CONNECTED) return@withContext null
        runCatching {
            val stats = backend.getStatistics(tunnel)
            stats.totalRx() to stats.totalTx()
        }.getOrNull()
    }

    private fun markDown() {
        _status.value = Status.DISCONNECTED
        Prefs.connectedAt = 0L
        Prefs.connectedServerId = null
        VpnNotifier.cancel(app)
    }

    private fun buildConfig(server: Server): Config {
        val text = server.config ?: managedConfig(server)
        return Config.parse(ByteArrayInputStream(text.toByteArray()))
    }

    private fun managedConfig(server: Server): String {
        val keys = deviceKeyPair()
        val address = try {
            Provisioner.register(server, keys.publicKey.toBase64()).also { Prefs.setAddress(server.id, it) }
        } catch (e: IOException) {
            // Fall back to the address this server gave us before, if any.
            Prefs.address(server.id) ?: throw e
        }
        return """
            [Interface]
            PrivateKey = ${keys.privateKey.toBase64()}
            Address = $address
            DNS = ${Prefs.dns}

            [Peer]
            PublicKey = ${server.publicKey}
            Endpoint = ${server.endpoint}
            AllowedIPs = 0.0.0.0/0, ::/0
            PersistentKeepalive = 25
        """.trimIndent()
    }

    private fun deviceKeyPair(): KeyPair {
        Prefs.devicePrivateKey?.let { saved ->
            runCatching { return KeyPair(Key.fromBase64(saved)) }
        }
        return KeyPair().also { Prefs.devicePrivateKey = it.privateKey.toBase64() }
    }
}
