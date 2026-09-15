# SmartGate security setup

The application now reads secrets from the web-server environment instead of
storing them in PHP source files.

Configure these variables outside the project directory, for example in the
Apache configuration used by XAMPP, then restart Apache:

```apache
SetEnv SMARTGATE_DB_HOST "localhost"
SetEnv SMARTGATE_DB_USER "smartgate_app"
SetEnv SMARTGATE_DB_PASSWORD "replace-with-database-password"
SetEnv SMARTGATE_DB_NAME "fcapstone"
SetEnv SMARTGATE_SMTP_USERNAME "replace-with-smtp-account"
SetEnv SMARTGATE_SMTP_PASSWORD "replace-with-smtp-app-password"
SetEnv SMARTGATE_SMTP_HOST "smtp.gmail.com"
SetEnv SMARTGATE_SMTP_PORT "587"
SetEnv SMARTGATE_SMTP_ENCRYPTION "tls"
SetEnv SMARTGATE_SMTP_FROM_EMAIL "smartgate@your-verified-domain.example"
SetEnv SMARTGATE_SMTP_FROM_NAME "SmartGate"
SetEnv SMARTGATE_DEVICE_KEY "replace-with-at-least-32-random-characters"
```

For Gmail SMTP, use the full Gmail address as the username and a newly generated
Gmail App Password as the SMTP password. The sender address should be the same
Gmail address. Do not use the regular Gmail account password.

The ESP32 must send the device key in the `X-Device-Key` header when calling
`scan.php` or `send_notification.php`. Those endpoints now accept POST only.

Do not commit real credentials. Rotate any credentials that were previously
stored in `mail_config.php`.
