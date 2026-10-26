# Newsletter backend (PHP 7.4+)
1. Upload this `newsletter/` folder to your PHP host (next to your site).
2. Make a password hash: `php -r "echo password_hash('YOUR-LONG-PASSWORD', PASSWORD_DEFAULT);"` and paste it into `config.php`, then fill in the other settings.
3. Best practice: set `DATA_DIR` to a folder OUTSIDE the web root (the code also drops an .htaccess in `data/` as a fallback — Apache only; on nginx you must block it yourself).
4. Visit `/newsletter/admin.php`. On the site, click the 🦃 logo 5 times fast to reveal the tiny lock button.
Backups: every change copies the data file into `data/backups/` (last 50 kept). The admin page also downloads a .zip. For real safety, add a cron job that copies DATA_DIR off the server.
Email: uses PHP mail(). For more than a few dozen people use real SMTP/an email service or messages will hit spam.
