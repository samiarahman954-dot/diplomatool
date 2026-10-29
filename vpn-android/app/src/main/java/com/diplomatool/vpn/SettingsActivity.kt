package com.diplomatool.vpn

import android.content.ActivityNotFoundException
import android.content.Intent
import android.os.Bundle
import android.provider.Settings
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.diplomatool.vpn.databinding.ActivitySettingsBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.launch

class SettingsActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySettingsBinding

    private val dnsOptions = listOf(
        R.string.dns_cloudflare to Prefs.DEFAULT_DNS,
        R.string.dns_google to "8.8.8.8, 8.8.4.4",
        R.string.dns_quad9 to "9.9.9.9, 149.112.112.112",
        R.string.dns_adguard to "94.140.14.14, 94.140.15.15",
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySettingsBinding.inflate(layoutInflater)
        setContentView(binding.root)
        binding.toolbar.setNavigationOnClickListener { finish() }

        binding.autoConnectSwitch.isChecked = Prefs.autoConnect
        binding.autoConnectSwitch.setOnCheckedChangeListener { _, checked -> Prefs.autoConnect = checked }
        binding.autoConnectRow.setOnClickListener { binding.autoConnectSwitch.toggle() }

        renderDns()
        binding.dnsRow.setOnClickListener { chooseDns() }

        binding.killSwitchRow.setOnClickListener {
            try {
                startActivity(Intent(Settings.ACTION_VPN_SETTINGS))
            } catch (e: ActivityNotFoundException) {
                Toast.makeText(this, R.string.kill_switch_unavailable, Toast.LENGTH_LONG).show()
            }
        }

        binding.refreshRow.setOnClickListener {
            lifecycleScope.launch {
                val ok = ServerRepository.refresh()
                Toast.makeText(
                    this@SettingsActivity,
                    if (ok) R.string.servers_updated else R.string.refresh_failed,
                    Toast.LENGTH_SHORT,
                ).show()
            }
        }

        binding.versionText.text = BuildConfig.VERSION_NAME
    }

    private fun chooseDns() {
        val current = dnsOptions.indexOfFirst { it.second == Prefs.dns }
        MaterialAlertDialogBuilder(this)
            .setTitle(R.string.dns)
            .setSingleChoiceItems(dnsOptions.map { getString(it.first) }.toTypedArray(), current) { dialog, which ->
                Prefs.dns = dnsOptions[which].second
                renderDns()
                dialog.dismiss()
            }
            .show()
    }

    private fun renderDns() {
        val option = dnsOptions.find { it.second == Prefs.dns }
        binding.dnsSummary.text = option?.let { getString(it.first) } ?: Prefs.dns
    }
}
