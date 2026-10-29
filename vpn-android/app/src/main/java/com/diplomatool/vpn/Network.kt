package com.diplomatool.vpn

import android.os.SystemClock
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.InetSocketAddress
import java.net.Socket
import java.net.URL

/** Talks to the provisioning API that runs next to WireGuard on each managed server. */
object Provisioner {
    /** Registers this device's public key and returns the tunnel address assigned to it. */
    fun register(server: Server, publicKey: String): String {
        val conn = URL(server.api!!.trimEnd('/') + "/register").openConnection() as HttpURLConnection
        try {
            conn.requestMethod = "POST"
            conn.connectTimeout = 8_000
            conn.readTimeout = 8_000
            conn.doOutput = true
            conn.setRequestProperty("Content-Type", "application/json")
            val body = JSONObject().put("public_key", publicKey)
            server.token?.let { body.put("token", it) }
            conn.outputStream.use { it.write(body.toString().toByteArray()) }

            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val text = stream?.bufferedReader()?.use { it.readText() }.orEmpty()
            if (code !in 200..299) {
                val reason = runCatching { JSONObject(text).getString("error") }.getOrDefault("HTTP $code")
                throw IOException(reason)
            }
            return JSONObject(text).getString("address")
        } finally {
            conn.disconnect()
        }
    }
}

object Ping {
    /** TCP connect time to the server's API in milliseconds, or -1 when unreachable. */
    fun measure(api: String): Int = try {
        val url = URL(api)
        val address = InetSocketAddress(url.host, if (url.port != -1) url.port else url.defaultPort)
        Socket().use { socket ->
            val start = SystemClock.elapsedRealtime()
            socket.connect(address, 3_000)
            (SystemClock.elapsedRealtime() - start).toInt()
        }
    } catch (e: Exception) {
        -1
    }
}
