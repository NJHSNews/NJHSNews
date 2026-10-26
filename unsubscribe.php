<?php
require __DIR__ . '/lib.php';
$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$s = jload('subscribers.json', []);
$done = $t && isset($s[$t]);
if ($done) { unset($s[$t]); jsave('subscribers.json', $s); }
?><!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<body style="font-family:sans-serif;background:#1a0c08;color:#fff3e0;text-align:center;padding:60px 20px">
<h1><?= $done ? 'You’re unsubscribed.' : 'Link already used or invalid.' ?></h1><p>🍂 Thanks for reading.</p>
