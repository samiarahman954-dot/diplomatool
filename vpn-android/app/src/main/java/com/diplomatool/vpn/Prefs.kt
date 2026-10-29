package com.diplomatool.vpn

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit

object Prefs {
    const val DEFAULT_DNS = "1.1.1.1, 1.0.0.1"

    private lateinit var sp: SharedPreferences

    fun init(context: Context) {
        sp = context.getSharedPreferences("vpn", Context.MODE_PRIVATE)
    }

    var selectedServerId: String?
        get() = sp.getString("selected_server", null)
        set(value) = sp.edit { putString("selected_server", value) }

    var connectedServerId: String?
        get() = sp.getString("connected_server", null)
        set(value) = sp.edit { putString("connected_server", value) }

    var connectedAt: Long
        get() = sp.getLong("connected_at", 0L)
        set(value) = sp.edit { putLong("connected_at", value) }

    var devicePrivateKey: String?
        get() = sp.getString("device_private_key", null)
        set(value) = sp.edit { putString("device_private_key", value) }

    var cachedServers: String?
        get() = sp.getString("cached_servers", null)
        set(value) = sp.edit { putString("cached_servers", value) }

    var customServers: String?
        get() = sp.getString("custom_servers", null)
        set(value) = sp.edit { putString("custom_servers", value) }

    var dns: String
        get() = sp.getString("dns", DEFAULT_DNS) ?: DEFAULT_DNS
        set(value) = sp.edit { putString("dns", value) }

    var autoConnect: Boolean
        get() = sp.getBoolean("auto_connect", false)
        set(value) = sp.edit { putBoolean("auto_connect", value) }

    fun address(serverId: String): String? = sp.getString("address_$serverId", null)

    fun setAddress(serverId: String, address: String) = sp.edit { putString("address_$serverId", address) }
}
