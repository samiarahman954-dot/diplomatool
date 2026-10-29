package com.diplomatool.vpn

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID

/** Server list (bundled, then refreshed from [BuildConfig.SERVER_LIST_URL]) plus the user's own servers. */
object ServerRepository {
    private lateinit var app: Context

    private val _servers = MutableStateFlow<List<Server>>(emptyList())
    val servers: StateFlow<List<Server>> = _servers.asStateFlow()

    private val selectedId = MutableStateFlow<String?>(null)
    val selected: Flow<Server?> = combine(_servers, selectedId) { list, id -> pick(list, id) }

    fun init(context: Context) {
        app = context.applicationContext
        selectedId.value = Prefs.selectedServerId
        reload()
    }

    fun selected(): Server? = pick(_servers.value, selectedId.value)

    fun select(id: String) {
        Prefs.selectedServerId = id
        selectedId.value = id
    }

    suspend fun refresh(): Boolean = withContext(Dispatchers.IO) {
        runCatching {
            val text = download(BuildConfig.SERVER_LIST_URL)
            JSONObject(text).getJSONArray("servers")
            Prefs.cachedServers = text
            reload()
        }.isSuccess
    }

    fun addCustom(name: String, config: String): Server {
        val server = Server(
            id = "custom-${UUID.randomUUID()}",
            name = name,
            city = endpointHost(config),
            country = "",
            config = config,
            custom = true,
        )
        saveCustom(customServers() + server)
        return server
    }

    fun removeCustom(id: String) {
        saveCustom(customServers().filterNot { it.id == id })
    }

    private fun pick(list: List<Server>, id: String?): Server? =
        list.find { it.id == id } ?: list.firstOrNull { !it.custom } ?: list.firstOrNull()

    private fun customServers() = _servers.value.filter { it.custom }

    private fun saveCustom(custom: List<Server>) {
        Prefs.customServers = JSONObject().put("servers", JSONArray(custom.map { it.toJson() })).toString()
        reload()
    }

    private fun reload() {
        val remote = parse(Prefs.cachedServers ?: bundled(), custom = false)
        val custom = parse(Prefs.customServers.orEmpty(), custom = true)
        _servers.value = remote + custom
    }

    private fun bundled(): String =
        runCatching { app.assets.open("servers.json").bufferedReader().use { it.readText() } }.getOrDefault("")

    private fun parse(json: String, custom: Boolean): List<Server> = runCatching {
        val array = JSONObject(json).getJSONArray("servers")
        (0 until array.length()).mapNotNull { Server.fromJson(array.getJSONObject(it), custom) }
    }.getOrDefault(emptyList())

    private fun download(url: String): String {
        val conn = URL(url).openConnection() as HttpURLConnection
        try {
            conn.connectTimeout = 10_000
            conn.readTimeout = 10_000
            conn.useCaches = false
            if (conn.responseCode != HttpURLConnection.HTTP_OK) throw IOException("HTTP ${conn.responseCode}")
            return conn.inputStream.bufferedReader().use { it.readText() }
        } finally {
            conn.disconnect()
        }
    }

    private fun endpointHost(config: String): String =
        Regex("""(?im)^\s*Endpoint\s*=\s*(\S+)""").find(config)?.groupValues?.get(1)?.substringBeforeLast(':').orEmpty()
}
