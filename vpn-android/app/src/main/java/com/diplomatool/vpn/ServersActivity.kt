package com.diplomatool.vpn

import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.isVisible
import androidx.core.widget.doAfterTextChanged
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.repeatOnLifecycle
import androidx.recyclerview.widget.LinearLayoutManager
import com.diplomatool.vpn.databinding.ActivityServersBinding
import com.diplomatool.vpn.databinding.DialogAddServerBinding
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.snackbar.Snackbar
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import com.wireguard.config.Config
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayInputStream

class ServersActivity : AppCompatActivity() {

    private lateinit var binding: ActivityServersBinding
    private val adapter = ServerAdapter(::onServerClicked, ::onServerLongClicked)
    private val pings = mutableMapOf<String, Int>()
    private val pinging = mutableSetOf<String>()
    private var query = ""

    private val scanQr = registerForActivityResult(ScanContract()) { result ->
        result.contents?.let { showAddDialog(config = it, name = "") }
    }

    private val importFile = registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        uri?.let(::importFromUri)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityServersBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.toolbar.setNavigationOnClickListener { finish() }
        binding.list.layoutManager = LinearLayoutManager(this)
        binding.list.adapter = adapter
        binding.searchInput.doAfterTextChanged {
            query = it?.toString().orEmpty().trim()
            render()
        }
        binding.swipeRefresh.setOnRefreshListener {
            lifecycleScope.launch {
                if (!ServerRepository.refresh()) snack(getString(R.string.refresh_failed))
                pings.clear()
                pinging.clear()
                measurePings(ServerRepository.servers.value)
                binding.swipeRefresh.isRefreshing = false
            }
        }
        binding.addButton.setOnClickListener { showAddOptions() }

        lifecycleScope.launch {
            repeatOnLifecycle(Lifecycle.State.STARTED) {
                ServerRepository.servers.collect { servers ->
                    render()
                    measurePings(servers)
                }
            }
        }
    }

    private fun render() {
        val selectedId = ServerRepository.selected()?.id
        val matches = ServerRepository.servers.value.filter {
            query.isEmpty() || it.name.contains(query, ignoreCase = true) ||
                it.city.contains(query, ignoreCase = true) || it.country.equals(query, ignoreCase = true)
        }
        val (custom, managed) = matches.partition { it.custom }
        val items = buildList {
            if (managed.isNotEmpty()) {
                add(ServerItem.Header(getString(R.string.all_locations)))
                managed.sortedBy { it.name }.forEach { add(ServerItem.Row(it, it.id == selectedId, pings[it.id])) }
            }
            if (custom.isNotEmpty()) {
                add(ServerItem.Header(getString(R.string.my_servers)))
                custom.forEach { add(ServerItem.Row(it, it.id == selectedId, null)) }
            }
        }
        adapter.submitList(items)
        binding.emptyText.isVisible = items.isEmpty()
    }

    private fun measurePings(servers: List<Server>) {
        servers.filter { it.api != null && pinging.add(it.id) }.forEach { server ->
            lifecycleScope.launch {
                pings[server.id] = withContext(Dispatchers.IO) { Ping.measure(server.api!!) }
                render()
            }
        }
    }

    private fun onServerClicked(server: Server) {
        ServerRepository.select(server.id)
        if (VpnManager.status.value == VpnManager.Status.CONNECTED && VpnManager.connectedServerId != server.id) {
            VpnManager.connectAsync(server)
        }
        finish()
    }

    private fun onServerLongClicked(server: Server) {
        if (!server.custom) return
        MaterialAlertDialogBuilder(this)
            .setTitle(getString(R.string.delete_server_title, server.name))
            .setPositiveButton(R.string.delete) { _, _ -> ServerRepository.removeCustom(server.id) }
            .setNegativeButton(R.string.cancel, null)
            .show()
    }

    private fun showAddOptions() {
        val options = arrayOf(getString(R.string.scan_qr), getString(R.string.import_file), getString(R.string.paste_config))
        MaterialAlertDialogBuilder(this)
            .setTitle(R.string.add_server)
            .setItems(options) { _, which ->
                when (which) {
                    0 -> scanQr.launch(
                        ScanOptions()
                            .setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                            .setPrompt(getString(R.string.scan_prompt))
                            .setBeepEnabled(false)
                    )
                    1 -> importFile.launch(arrayOf("*/*"))
                    else -> showAddDialog(config = "", name = "")
                }
            }
            .show()
    }

    private fun importFromUri(uri: Uri) {
        val text = runCatching {
            contentResolver.openInputStream(uri)?.bufferedReader()?.use { it.readText() }
        }.getOrNull()
        if (text.isNullOrBlank()) {
            snack(getString(R.string.error_import))
            return
        }
        showAddDialog(config = text.trim(), name = displayName(uri).orEmpty().removeSuffix(".conf"))
    }

    private fun displayName(uri: Uri): String? = runCatching {
        contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
            if (cursor.moveToFirst()) cursor.getString(0) else null
        }
    }.getOrNull()

    private fun showAddDialog(config: String, name: String) {
        val dialogBinding = DialogAddServerBinding.inflate(layoutInflater)
        dialogBinding.nameInput.setText(name)
        dialogBinding.configInput.setText(config)
        val dialog = MaterialAlertDialogBuilder(this)
            .setTitle(R.string.add_server)
            .setView(dialogBinding.root)
            .setPositiveButton(R.string.save, null)
            .setNegativeButton(R.string.cancel, null)
            .create()
        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                val text = dialogBinding.configInput.text?.toString().orEmpty().trim()
                val error = runCatching { Config.parse(ByteArrayInputStream(text.toByteArray())) }.exceptionOrNull()
                if (text.isEmpty() || error != null) {
                    dialogBinding.configLayout.error =
                        getString(R.string.error_bad_config, error?.message ?: getString(R.string.config_hint))
                    return@setOnClickListener
                }
                val serverName = dialogBinding.nameInput.text?.toString().orEmpty().trim()
                    .ifEmpty { getString(R.string.my_server_default) }
                val server = ServerRepository.addCustom(serverName, text)
                ServerRepository.select(server.id)
                dialog.dismiss()
                snack(getString(R.string.server_added))
            }
        }
        dialog.show()
    }

    private fun snack(message: String) {
        Snackbar.make(binding.root, message, Snackbar.LENGTH_LONG).show()
    }
}
