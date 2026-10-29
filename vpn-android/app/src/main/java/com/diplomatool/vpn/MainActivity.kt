package com.diplomatool.vpn

import android.app.Activity
import android.content.res.ColorStateList
import android.net.Uri
import android.net.VpnService
import android.os.Bundle
import android.text.format.Formatter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.repeatOnLifecycle
import com.diplomatool.vpn.databinding.ActivityMainBinding
import com.wireguard.android.backend.Tunnel
import com.wireguard.config.Config
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.ByteArrayInputStream

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var store: ConfigStore
    private var busy = false

    private val vpnPermission =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == Activity.RESULT_OK) {
                startTunnel()
            } else {
                toast(R.string.permission_denied)
            }
        }

    private val importFile =
        registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
            if (uri != null) importConfig(uri)
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        store = ConfigStore(this)

        binding.configInput.setText(store.configText)

        binding.importButton.setOnClickListener {
            importFile.launch(arrayOf("*/*"))
        }
        binding.saveButton.setOnClickListener {
            if (parseConfig() != null) {
                store.configText = currentConfigText()
                toast(R.string.config_saved)
            }
        }
        binding.connectButton.setOnClickListener { onConnectClicked() }

        lifecycleScope.launch {
            repeatOnLifecycle(Lifecycle.State.STARTED) {
                VpnManager.refresh(this@MainActivity)
                launch { VpnManager.state.collect { render(it) } }
                launch {
                    while (true) {
                        renderTraffic(VpnManager.traffic(this@MainActivity))
                        delay(1000)
                    }
                }
            }
        }
    }

    private fun onConnectClicked() {
        if (busy) return
        if (VpnManager.state.value == Tunnel.State.UP) {
            runBusy { VpnManager.disconnect(this) }
            return
        }
        if (parseConfig() == null) return
        val permissionIntent = VpnService.prepare(this)
        if (permissionIntent != null) {
            vpnPermission.launch(permissionIntent)
        } else {
            startTunnel()
        }
    }

    private fun startTunnel() {
        val config = parseConfig() ?: return
        store.configText = currentConfigText()
        runBusy { VpnManager.connect(this, config) }
    }

    private fun runBusy(action: suspend () -> Unit) {
        busy = true
        binding.connectButton.isEnabled = false
        binding.statusText.setText(R.string.status_working)
        lifecycleScope.launch {
            try {
                action()
            } catch (e: Exception) {
                showError(e.message ?: e.javaClass.simpleName)
            } finally {
                busy = false
                binding.connectButton.isEnabled = true
                render(VpnManager.state.value)
            }
        }
    }

    private fun currentConfigText(): String = binding.configInput.text?.toString().orEmpty().trim()

    private fun parseConfig(): Config? {
        val text = currentConfigText()
        if (text.isEmpty()) {
            showError(getString(R.string.error_empty_config))
            return null
        }
        return try {
            Config.parse(ByteArrayInputStream(text.toByteArray())).also {
                binding.configLayout.error = null
            }
        } catch (e: Exception) {
            showError(getString(R.string.error_bad_config, e.message ?: e.javaClass.simpleName))
            null
        }
    }

    private fun importConfig(uri: Uri) {
        val text = runCatching {
            contentResolver.openInputStream(uri)?.bufferedReader()?.use { it.readText() }
        }.getOrNull()
        if (text.isNullOrBlank()) {
            toast(R.string.error_import)
            return
        }
        binding.configInput.setText(text.trim())
        if (parseConfig() != null) {
            store.configText = currentConfigText()
            toast(R.string.config_imported)
        }
    }

    private fun render(state: Tunnel.State) {
        if (busy) return
        val connected = state == Tunnel.State.UP
        binding.statusText.setText(if (connected) R.string.status_connected else R.string.status_disconnected)
        binding.connectButton.setText(if (connected) R.string.disconnect else R.string.connect)
        val color = ContextCompat.getColor(this, if (connected) R.color.connected else R.color.disconnected)
        binding.connectButton.backgroundTintList = ColorStateList.valueOf(color)
        binding.statusDot.backgroundTintList = ColorStateList.valueOf(color)
        binding.configInput.isEnabled = !connected
        binding.importButton.isEnabled = !connected
        binding.saveButton.isEnabled = !connected
        binding.serverText.text = serverLabel()
        if (!connected) renderTraffic(null)
    }

    private fun serverLabel(): String {
        val endpoint = runCatching {
            Config.parse(ByteArrayInputStream(currentConfigText().toByteArray()))
                .peers.firstOrNull()?.endpoint?.orElse(null)?.host
        }.getOrNull()
        return getString(R.string.server_label, endpoint ?: getString(R.string.server_none))
    }

    private fun renderTraffic(traffic: Pair<Long, Long>?) {
        binding.trafficText.text = if (traffic == null) {
            ""
        } else {
            getString(
                R.string.traffic,
                Formatter.formatShortFileSize(this, traffic.first),
                Formatter.formatShortFileSize(this, traffic.second),
            )
        }
    }

    private fun showError(message: String) {
        binding.configLayout.error = message
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
    }

    private fun toast(resId: Int) {
        Toast.makeText(this, resId, Toast.LENGTH_SHORT).show()
    }
}
