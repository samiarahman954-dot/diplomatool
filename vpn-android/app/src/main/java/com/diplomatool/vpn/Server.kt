package com.diplomatool.vpn

import org.json.JSONObject

/**
 * A VPN location. Managed servers (from the server list) carry the server's public key,
 * endpoint and provisioning API; custom servers carry a full WireGuard config instead.
 */
data class Server(
    val id: String,
    val name: String,
    val city: String,
    val country: String,
    val endpoint: String? = null,
    val publicKey: String? = null,
    val api: String? = null,
    val token: String? = null,
    val config: String? = null,
    val custom: Boolean = false,
) {
    val flag: String get() = countryFlag(country)

    fun toJson(): JSONObject = JSONObject()
        .put("id", id)
        .put("name", name)
        .put("city", city)
        .put("country", country)
        .put("config", config)

    companion object {
        fun fromJson(o: JSONObject, custom: Boolean): Server? {
            val id = o.optString("id").ifBlank { return null }
            val config = o.optString("config").ifBlank { null }
            val endpoint = o.optString("endpoint").ifBlank { null }
            val publicKey = o.optString("publicKey").ifBlank { null }
            val api = o.optString("api").ifBlank { null }
            if (config == null && (endpoint == null || publicKey == null || api == null)) return null
            return Server(
                id = id,
                name = o.optString("name").ifBlank { id },
                city = o.optString("city"),
                country = o.optString("country"),
                endpoint = endpoint,
                publicKey = publicKey,
                api = api,
                token = o.optString("token").ifBlank { null },
                config = config,
                custom = custom,
            )
        }
    }
}

fun countryFlag(code: String): String {
    val upper = code.uppercase()
    if (upper.length != 2 || !upper.all { it in 'A'..'Z' }) return "🌐"
    return upper.map { String(Character.toChars(0x1F1E6 + (it - 'A'))) }.joinToString("")
}
