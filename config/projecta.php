<?php
// Loaded from private data/config.php before native defaults. No secrets in this file.
defined('PLUGIN_INSTALLER') or define('PLUGIN_INSTALLER', false);
defined('REMEMBER_ME_AUTH') or define('REMEMBER_ME_AUTH', false);
defined('LDAP_AUTH') or define('LDAP_AUTH', false);
defined('REVERSE_PROXY_AUTH') or define('REVERSE_PROXY_AUTH', false);
defined('TOTP_ISSUER') or define('TOTP_ISSUER', 'Projecta');
defined('MAIL_CONFIGURATION') or define('MAIL_CONFIGURATION', false);
defined('MAIL_TRANSPORT') or define('MAIL_TRANSPORT', 'smtp');
defined('MAIL_FROM') or define('MAIL_FROM', getenv('PROJECTA_SMTP_FROM') ?: '');
defined('MAIL_SMTP_HOSTNAME') or define('MAIL_SMTP_HOSTNAME', getenv('PROJECTA_SMTP_HOST') ?: '');
defined('MAIL_SMTP_PORT') or define('MAIL_SMTP_PORT', (int)(getenv('PROJECTA_SMTP_PORT') ?: 587));
defined('MAIL_SMTP_USERNAME') or define('MAIL_SMTP_USERNAME', getenv('PROJECTA_SMTP_USER') ?: '');
defined('MAIL_SMTP_PASSWORD') or define('MAIL_SMTP_PASSWORD', getenv('PROJECTA_SMTP_PASSWORD') ?: '');
defined('MAIL_SMTP_ENCRYPTION') or define('MAIL_SMTP_ENCRYPTION', getenv('PROJECTA_SMTP_ENCRYPTION') ?: 'tls');
