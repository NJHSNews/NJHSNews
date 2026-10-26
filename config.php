<?php
// ===== EDIT THESE =====
// Make the hash with:  php -r "echo password_hash('YOUR-LONG-PASSWORD', PASSWORD_DEFAULT);"
const ADMIN_HASH   = 'PASTE_YOUR_HASH_HERE';
const SITE_NAME    = 'NPS School News';
const FROM_EMAIL   = 'news@yourdomain.com';
const NEWSLETTER_URL = 'https://yourdomain.com/newsletter';   // public URL of THIS folder, no trailing slash
const DATA_DIR     = __DIR__ . '/data';                        // best: move outside the web root, e.g. '/home/you/nps-data'
const BACKUP_KEEP  = 50;                                       // backups kept per data file
