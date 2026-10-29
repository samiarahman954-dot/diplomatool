package com.diplomatool.vpn

import android.app.Application

class App : Application() {
    override fun onCreate() {
        super.onCreate()
        Prefs.init(this)
        VpnManager.init(this)
        ServerRepository.init(this)
    }
}
