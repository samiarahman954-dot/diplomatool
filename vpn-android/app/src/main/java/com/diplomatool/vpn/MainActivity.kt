package com.diplomatool.vpn

import android.Manifest
import android.animation.AnimatorSet
import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.content.Intent
import android.content.pm.PackageManager
import android.content.res.ColorStateList
import android.net.VpnService
import android.os.Build
import android.os.Bundle
import android.text.format.DateUtils
import android.text.format.Formatter
import android.view.View
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.repeatOnLifecycle
import com.diplomatool.vpn.VpnManager.Status
import com.diplomatool.vpn.databinding.ActivityMainBinding
import com.google.android.material.snackbar.Snackbar
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private var pulse: AnimatorSet? = null
    private var lastRx = -1L
    private var lastTx = -1L

    private val vpnPermission =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == RESULT_OK) connectSelected() else snack(getString(R.string.permission_denied))
        }

    private val notificationPermission =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { }

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.settingsButton.setOnClickListener { startActivity(Intent(this, SettingsActivity::class.java)) }
        binding.serverCard.setOnClickListener { startActivity(Intent(this, ServersActivity::class.java)) }
        binding.connectButton.setOnClickListener { onConnectClicked() }

        lifecycleScope.launch {
            repeatOnLifecycle(Lifecycle.State.STARTED) {
                VpnManager.refresh()
                launch { VpnManager.status.collect { renderStatus(it) } }
                launch { ServerRepository.selected.collect { renderServer(it) } }
                launch {
                    VpnManager.error.collect { message ->
                        if (message != null) {
                            snack(message)
                            VpnManager.clearError()
                        }
                    }
                }
                launch {
                    while (true) {
                        tick()
                        delay(1000)
                    }
                }
            }
        }

        if (savedInstanceState == null) {
            askNotificationPermission()
            lifecycleScope.launch {
                ServerRepository.refresh()
                VpnManager.refresh()
                if (Prefs.autoConnect && VpnManager.status.value == Status.DISCONNECTED &&
                    VpnService.prepare(this@MainActivity) == null
                ) connectSelected()
            }
        }
    }

    private fun onConnectClicked() {
        when (VpnManager.status.value) {
            Status.CONNECTED -> VpnManager.disconnectAsync()
            Status.CONNECTING -> Unit
            Status.DISCONNECTED -> {
                if (ServerRepository.selected() == null) {
                    snack(getString(R.string.no_server_selected))
                    startActivity(Intent(this, ServersActivity::class.java))
                    return
                }
                val permissionIntent = VpnService.prepare(this)
                if (permissionIntent != null) vpnPermission.launch(permissionIntent) else connectSelected()
            }
        }
    }

    private fun connectSelected() {
        ServerRepository.selected()?.let { VpnManager.connectAsync(it) }
    }

    private fun renderStatus(status: Status) {
        val colorRes = when (status) {
            Status.CONNECTED -> R.color.connected
            Status.CONNECTING -> R.color.connecting
            Status.DISCONNECTED -> R.color.primary
        }
        val tint = ColorStateList.valueOf(ContextCompat.getColor(this, colorRes))
        binding.connectButton.backgroundTintList = tint
        binding.glowRing.backgroundTintList = tint
        binding.pulseRing.backgroundTintList = tint
        binding.glowRing.alpha = if (status == Status.CONNECTED) 0.25f else 0.12f
        binding.statusTitle.setText(
            when (status) {
                Status.CONNECTED -> R.string.status_connected
                Status.CONNECTING -> R.string.status_connecting
                Status.DISCONNECTED -> R.string.status_disconnected
            }
        )
        binding.statusTitle.setTextColor(tint)
        if (status != Status.CONNECTED) {
            binding.statusSubtitle.setText(
                if (status == Status.CONNECTING) R.string.status_wait else R.string.tap_to_connect
            )
            resetTraffic()
        }
        setPulsing(status == Status.CONNECTING)
    }

    private fun renderServer(server: Server?) {
        if (server == null) {
            binding.serverFlag.text = countryFlag("")
            binding.serverName.setText(R.string.select_server)
            binding.serverCity.text = ""
        } else {
            binding.serverFlag.text = server.flag
            binding.serverName.text = server.name
            binding.serverCity.text = server.city
        }
    }

    private suspend fun tick() {
        if (VpnManager.status.value != Status.CONNECTED) return
        val elapsed = (System.currentTimeMillis() - VpnManager.connectedAt).coerceAtLeast(0) / 1000
        binding.statusSubtitle.text = DateUtils.formatElapsedTime(elapsed)
        val (rx, tx) = VpnManager.traffic() ?: return
        if (lastRx >= 0) {
            binding.downloadSpeed.text = speed(rx - lastRx)
            binding.uploadSpeed.text = speed(tx - lastTx)
        }
        lastRx = rx
        lastTx = tx
        binding.downloadTotal.text = getString(R.string.total, Formatter.formatShortFileSize(this, rx))
        binding.uploadTotal.text = getString(R.string.total, Formatter.formatShortFileSize(this, tx))
    }

    private fun resetTraffic() {
        lastRx = -1
        lastTx = -1
        binding.downloadSpeed.text = speed(0)
        binding.uploadSpeed.text = speed(0)
        binding.downloadTotal.text = getString(R.string.total, Formatter.formatShortFileSize(this, 0))
        binding.uploadTotal.text = getString(R.string.total, Formatter.formatShortFileSize(this, 0))
    }

    private fun speed(bytesPerSecond: Long) =
        getString(R.string.speed, Formatter.formatShortFileSize(this, bytesPerSecond.coerceAtLeast(0)))

    private fun setPulsing(on: Boolean) {
        if (!on) {
            pulse?.cancel()
            pulse = null
            binding.pulseRing.alpha = 0f
            return
        }
        if (pulse != null) return
        val ring = binding.pulseRing
        fun repeating(animator: ObjectAnimator) = animator.apply {
            repeatCount = ValueAnimator.INFINITE
            repeatMode = ValueAnimator.RESTART
        }
        pulse = AnimatorSet().apply {
            playTogether(
                repeating(ObjectAnimator.ofFloat(ring, View.SCALE_X, 0.7f, 1f)),
                repeating(ObjectAnimator.ofFloat(ring, View.SCALE_Y, 0.7f, 1f)),
                repeating(ObjectAnimator.ofFloat(ring, View.ALPHA, 0.5f, 0f)),
            )
            duration = 1400
            start()
        }
    }

    private fun askNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) !=
            PackageManager.PERMISSION_GRANTED
        ) notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
    }

    private fun snack(message: String) {
        Snackbar.make(binding.root, message, Snackbar.LENGTH_LONG).show()
    }
}
