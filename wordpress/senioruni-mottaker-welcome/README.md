# SeniorUni – Mottaker-velkomst

Standalone WordPress plugin for senioruni.no. On a **Familie** (two-person) MemberPress purchase, it sends the second person (the Mottaker, a Corporate Accounts sub-account) their welcome email **only after they have set their password**.

It does not edit MemberPress, MemberPress Corporate Accounts or the SeniorUni Trygg (Vipps) plugin.

## Flow

| # | Who | Email | Sent by |
|---|---|---|---|
| 1 | Purchaser | Welcome | MemberPress (unchanged) |
| 2 | Mottaker | Set your password, from a MemberPress template (default: Sub Account Welcome Email) | Auto MPCA Code Snippet, email swapped by **this plugin** |
| 3 | Mottaker | Welcome, once, after the password is set | **this plugin** |

Also, for every new member who pays by card (one-person, buyer and Mottaker on Familie): added to Mailchimp and a welcome SMS with the Vipps login link (**this plugin**, via SeniorUni Trygg's Mailchimp and Pling settings).

## How it works

- `retrieve_password_key`: when the set-password link is generated, the user is flagged as pending.
- `after_password_reset` (core `wp-login.php?action=rp`) triggers the send. `wp_set_password` and `profile_update` also trigger it, which covers MemberPress's own reset form. Those two only act if the pending flag was set in an earlier request, so a password set while the account is being created never triggers the email.
- `pre_wp_mail`: the Auto MPCA Code Snippet sends a hardcoded English "[SeniorUni] Set your password" email. The plugin stops it and sends the MemberPress template chosen under *1. Set-password email* instead. The template gets `{$corporate_name}` (the purchaser's name) and the password link as `{$reset_password_link}` (also `reset_password_url`, `password_reset_link`, `password_reset_url`, `set_password_link`, `set_password_url`, `reset_link`). The plugin also reads the template and fills any `{$...}` variable whose name contains reset/password/passord, including the URL-encoded form `%7B$...%7D` the editor can leave inside a link. If the template has no such variable, or can't be sent, the English email goes out instead, always with a working link.
- Phone numbers: when `mepr_mobilnummer` is saved (purchaser at checkout, Mottaker by the snippet), it is copied as `0047XXXXXXXX` to `suit_mobile`, the field SeniorUni Trygg's Vipps and SMS login look users up by. It isn't copied if another user already has that number, or if the user already has a different number there (e.g. from a Vipps purchase). *Copy phone numbers for existing members* does the same for everyone already registered.
- New members (`mepr-event-transaction-completed`): once per member, in the background (WP-Cron, started at the end of the request, same as SeniorUni Trygg): added to the Mailchimp audience set in SeniorUni Trygg (no tags; someone who unsubscribed stays unsubscribed) and sent a welcome SMS through SeniorUni Trygg's Pling account. Separate texts for the buyer and the Mottaker (`{navn}`, `{kjøper}`, `{lenke}`), editable on the settings page. Skipped: renewals and members with an earlier transaction, Vipps purchases (Trygg handles those), manual/admin transactions, admins, memberships not ticked in the settings. The Mottaker gets no SMS if they gave the buyer's number.
- It sends only if the user has `mpca_corporate_account_id` user meta, which marks a sub-account. The purchaser and one-person members never match.
- The `_senioruni_mw_sent` user meta flag means the welcome email is sent once. Later logins and password changes don't send it again.
- The email body is taken from a **MemberPress template**, so the wording is edited in *MemberPress → Settings → Emails*. The template is picked under *Settings → Mottaker-velkomst*. The welcome email defaults to the membership-specific welcome (the Familie welcome), never to the Sub Account Welcome Email, which asks for a new password.

## Install

1. Upload `senioruni-mottaker-welcome.zip` under *Plugins → Add New → Upload*, then activate it.
2. Open *Settings → Mottaker-velkomst*.
   - Check which template is selected.
   - Click **Send test** with an existing Mottaker's address and your own address as recipient.
   - Make sure the test is a Norwegian **welcome** email, not the "Set your password" email. If it's the wrong one, pick another template and test again.

## Verify (fresh Familie purchase, two new `+alias` addresses)

1. The purchaser gets the welcome email immediately.
2. The Mottaker gets the set-password email immediately and **no** welcome email yet.
3. The Mottaker sets a password, then the welcome email arrives. It also shows under *Recent activity* on the settings page.
4. The Mottaker logs out and in again, or resets the password again. No second welcome email is sent.

If step 3 shows `failed: …` under *Recent activity*, the message says why, for example that the template is disabled or wasn't found.

## Notes

- Sub-accounts that already existed before activation have no "sent" flag. They get the welcome email once, the next time they reset their password.
- The plugin doesn't translate the English "[SeniorUni] Set your password" email. That's a separate task.
- Filters: `senioruni_mw_is_sub_account` (bool, user_id) and `senioruni_mw_email_params` (params, user_id, txn, template).
