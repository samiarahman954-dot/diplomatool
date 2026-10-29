package com.diplomatool.vpn

import android.content.Context
import androidx.core.content.edit

/** Persists the raw WireGuard config text the user entered or imported. */
class ConfigStore(context: Context) {
    private val prefs = context.getSharedPreferences("vpn", Context.MODE_PRIVATE)

    var configText: String
        get() = prefs.getString(KEY_CONFIG, "") ?: ""
        set(value) = prefs.edit { putString(KEY_CONFIG, value) }

    private companion object {
        const val KEY_CONFIG = "config"
    }
}
