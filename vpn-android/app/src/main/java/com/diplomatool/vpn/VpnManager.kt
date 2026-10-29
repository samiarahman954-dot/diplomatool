package com.diplomatool.vpn

import android.content.Context
import com.wireguard.android.backend.GoBackend
import com.wireguard.android.backend.Tunnel
import com.wireguard.config.Config
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext

/** Process-wide owner of the WireGuard backend and the single tunnel this app manages. */
object VpnManager {
    private const val TUNNEL_NAME = "securevpn"

    private val mutex = Mutex()
    private var backend: GoBackend? = null

    private val _state = MutableStateFlow(Tunnel.State.DOWN)
    val state: StateFlow<Tunnel.State> = _state.asStateFlow()

    private val tunnel = object : Tunnel {
        override fun getName(): String = TUNNEL_NAME
        override fun onStateChange(newState: Tunnel.State) {
            _state.value = newState
        }
    }

    private fun backend(context: Context): GoBackend =
        backend ?: GoBackend(context.applicationContext).also { backend = it }

    suspend fun connect(context: Context, config: Config) = withContext(Dispatchers.IO) {
        mutex.withLock {
            _state.value = backend(context).setState(tunnel, Tunnel.State.UP, config)
        }
    }

    suspend fun disconnect(context: Context) = withContext(Dispatchers.IO) {
        mutex.withLock {
            _state.value = backend(context).setState(tunnel, Tunnel.State.DOWN, null)
        }
    }

    suspend fun refresh(context: Context) = withContext(Dispatchers.IO) {
        mutex.withLock {
            _state.value = backend(context).getState(tunnel)
        }
    }

    /** Returns total (received, sent) bytes, or null when the tunnel is down. */
    suspend fun traffic(context: Context): Pair<Long, Long>? = withContext(Dispatchers.IO) {
        if (_state.value != Tunnel.State.UP) return@withContext null
        runCatching {
            val stats = backend(context).getStatistics(tunnel)
            stats.totalRx() to stats.totalTx()
        }.getOrNull()
    }
}
