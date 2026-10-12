# Email Notifications

Email notifications use PHPMailer with SMTP. Configure these environment
variables in the environment used by Apache/PHP before expecting email delivery:

- `SMTP_HOST`
- `SMTP_PORT` (usually `587` for TLS or `465` for SSL)
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_ENCRYPTION` (`tls`, `ssl`, or `none`)
- `MAIL_FROM`
- `MAIL_FROM_NAME` (optional; defaults to `RiRa Cakes`)
- `ADMIN_NOTIFICATION_EMAIL`

Never commit SMTP passwords or provider app passwords to the project. For
XAMPP/Apache on Windows, set the variables for the Apache service/process and
restart Apache. Verify the values are available to PHP with a temporary local
diagnostic, then remove that diagnostic.

Install the declared dependency from the project root with `composer install`.
If SMTP is not configured or a provider is temporarily unavailable, the order
is still saved; the notification failure is recorded in the PHP error log.

## Password Reset Verification

The `forgot-password.html` page sends a six-digit, single-use verification code
by email or WhatsApp. Codes expire after 10 minutes; reset requests are limited
to three per account identifier and ten per IP address every 15 minutes, and
each code allows at most five verification attempts. A successful reset
requires a new password of at least eight characters.

Email verification requires `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`, and
`MAIL_FROM`. WhatsApp verification requires `WHATSAPP_PROVIDER` (`fonnte` or
`wablas`) and `WHATSAPP_API_TOKEN`, or equivalent provider/token settings in
the active `whatsapp_config` database row. The existing `wa_link` fallback is
not used for reset codes because a manually composed WhatsApp message cannot
verify delivery securely.

The `password_reset_tokens` table is created by the project SQL schema. For an
existing database, run the matching `CREATE TABLE IF NOT EXISTS
password_reset_tokens` statement from `database/manis_legit_db.sql`.

## WhatsApp Notifications

New standard orders, custom cake pre-orders, and reviews include a notification
link addressed to the admin WhatsApp number `081219868390`. Without a gateway,
the customer can open the link and tap **Send** in WhatsApp; a `wa.me` link
cannot send a message silently or automatically.

For automatic delivery, configure `WHATSAPP_PROVIDER` as `fonnte` or `wablas`
and set `WHATSAPP_API_TOKEN` in the Apache/PHP environment. The admin number
can be overridden with `WHATSAPP_ADMIN_PHONE` in international format (for
example, `6281219868390`). Alternatively, set the provider, token, and admin
phone in the active `whatsapp_config` database row. Never commit gateway
tokens to source control. Without a configured gateway, the manual `wa.me`
link remains available.
