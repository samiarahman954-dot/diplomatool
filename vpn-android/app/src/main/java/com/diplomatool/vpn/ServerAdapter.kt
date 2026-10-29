package com.diplomatool.vpn

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.core.view.isVisible
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.diplomatool.vpn.databinding.ItemHeaderBinding
import com.diplomatool.vpn.databinding.ItemServerBinding

sealed interface ServerItem {
    data class Header(val title: String) : ServerItem
    data class Row(val server: Server, val selected: Boolean, val pingMs: Int?) : ServerItem
}

class ServerAdapter(
    private val onClick: (Server) -> Unit,
    private val onLongClick: (Server) -> Unit,
) : ListAdapter<ServerItem, RecyclerView.ViewHolder>(Diff) {

    override fun getItemViewType(position: Int) =
        if (getItem(position) is ServerItem.Header) TYPE_HEADER else TYPE_ROW

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): RecyclerView.ViewHolder {
        val inflater = LayoutInflater.from(parent.context)
        return if (viewType == TYPE_HEADER) {
            HeaderHolder(ItemHeaderBinding.inflate(inflater, parent, false))
        } else {
            RowHolder(ItemServerBinding.inflate(inflater, parent, false))
        }
    }

    override fun onBindViewHolder(holder: RecyclerView.ViewHolder, position: Int) {
        when (val item = getItem(position)) {
            is ServerItem.Header -> (holder as HeaderHolder).binding.title.text = item.title
            is ServerItem.Row -> bindRow((holder as RowHolder).binding, item)
        }
    }

    private fun bindRow(b: ItemServerBinding, item: ServerItem.Row) {
        val context = b.root.context
        val server = item.server
        b.flag.text = server.flag
        b.name.text = server.name
        b.city.text = if (server.custom) {
            listOf(context.getString(R.string.custom_label), server.city).filter { it.isNotBlank() }.joinToString(" · ")
        } else {
            server.city
        }
        b.city.isVisible = b.city.text.isNotEmpty()
        b.check.isVisible = item.selected
        b.name.setTextColor(
            ContextCompat.getColor(context, if (item.selected) R.color.primary else R.color.text_primary)
        )

        val ping = item.pingMs
        b.ping.isVisible = ping != null
        if (ping != null) {
            b.ping.text = if (ping < 0) "—" else context.getString(R.string.ping_ms, ping)
            val color = when {
                ping < 0 -> R.color.text_secondary
                ping < 120 -> R.color.connected
                ping < 250 -> R.color.connecting
                else -> R.color.error
            }
            b.ping.setTextColor(ContextCompat.getColor(context, color))
        }

        b.root.setOnClickListener { onClick(server) }
        b.root.setOnLongClickListener {
            onLongClick(server)
            true
        }
    }

    private class HeaderHolder(val binding: ItemHeaderBinding) : RecyclerView.ViewHolder(binding.root)
    private class RowHolder(val binding: ItemServerBinding) : RecyclerView.ViewHolder(binding.root)

    private object Diff : DiffUtil.ItemCallback<ServerItem>() {
        override fun areItemsTheSame(a: ServerItem, b: ServerItem) = when {
            a is ServerItem.Header && b is ServerItem.Header -> a.title == b.title
            a is ServerItem.Row && b is ServerItem.Row -> a.server.id == b.server.id
            else -> false
        }

        override fun areContentsTheSame(a: ServerItem, b: ServerItem) = a == b
    }

    private companion object {
        const val TYPE_HEADER = 0
        const val TYPE_ROW = 1
    }
}
