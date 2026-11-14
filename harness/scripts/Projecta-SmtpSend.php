<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http' || MAIL_SMTP_HOSTNAME!=='localhost' || MAIL_SMTP_PORT!==12587) { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
if (! $container['emailClient']->ready()) { throw new RuntimeException('SMTP fixture not configured'); }
$container['emailClient']->send('recipient@example.invalid','Synthetic recipient','Projecta SMTP synthetic','<p>Synthetic Projecta email.</p>');
echo "PASS mail job executed against loopback SMTP only\n";
