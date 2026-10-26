<?php
require __DIR__ . '/lib.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$ip = $_SERVER['REMOTE_ADDR'] ?? '0'; $note = '';

function send_one($to, $tok, $m) {
    $un = NEWSLETTER_URL . '/unsubscribe.php?t=' . $tok;
    $hdr = ['From: ' . SITE_NAME . ' <' . FROM_EMAIL . '>', 'Content-Type: text/plain; charset=UTF-8', "List-Unsubscribe: <$un>"];
    return mail($to, mb_encode_mimeheader($m['subject'], 'UTF-8'), $m['body'] . "\n\n--\nUnsubscribe: $un", implode("\r\n", $hdr));
}

// ---- login (locks an IP for 15 min after 5 bad tries) ----
if (isset($_POST['password'])) {
    $t = jload('login.json', []); $r = $t[$ip] ?? ['n' => 0, 'until' => 0];
    if ($r['until'] > time()) $note = 'Too many attempts. Try again later.';
    elseif (password_verify($_POST['password'], ADMIN_HASH)) { session_regenerate_id(true); $_SESSION['ok'] = 1; unset($t[$ip]); jsave('login.json', $t, false); header('Location: admin.php'); exit; }
    else { $r['n']++; if ($r['n'] >= 5) { $r['until'] = time() + 900; $r['n'] = 0; } $t[$ip] = $r; jsave('login.json', $t, false); sleep(1); $note = 'Wrong password.'; }
}
$in = !empty($_SESSION['ok']);

// ---- downloads ----
if ($in && isset($_GET['dl'])) {
    if ($_GET['dl'] === 'csv') {
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="subscribers.csv"');
        echo "email,joined\n"; foreach (jload('subscribers.json', []) as $s) echo $s['email'] . ',' . $s['joined'] . "\n"; exit;
    }
    if ($_GET['dl'] === 'backup' && class_exists('ZipArchive')) {
        $z = new ZipArchive; $tmp = tempnam(sys_get_temp_dir(), 'bk'); $z->open($tmp, ZipArchive::OVERWRITE);
        foreach (glob(DATA_DIR . '/*.json') as $f) $z->addFile($f, basename($f));
        foreach (glob(DATA_DIR . '/backups/*') as $f) $z->addFile($f, 'backups/' . basename($f));
        $z->close(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="backup-' . date('Ymd') . '.zip"'); readfile($tmp); unlink($tmp); exit;
    }
}

// ---- actions ----
if ($in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['a'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) die('Bad token');
    $a = $_POST['a'];
    $m = ['subject' => trim($_POST['subject'] ?? ''), 'body' => trim($_POST['body'] ?? '')];
    if ($a === 'logout') { session_destroy(); header('Location: admin.php'); exit; }
    if ($a === 'del') { $s = jload('subscribers.json', []); unset($s[$_POST['id'] ?? '']); jsave('subscribers.json', $s); $note = 'Removed.'; }
    if ($a === 'save' || $a === 'test' || $a === 'send') { jsave('message.json', $m + ['updated' => date('c')]); $note = 'Saved (backup made).'; }
    if ($a === 'test') { $to = trim($_POST['to'] ?? ''); $note = filter_var($to, FILTER_VALIDATE_EMAIL) && send_one($to, 'test', $m) ? "Test sent to $to." : 'Test failed — check the address / server mail setup.'; }
    if ($a === 'send') {
        set_time_limit(0); $ok = 0; $bad = 0;
        foreach (jload('subscribers.json', []) as $tok => $s) { send_one($s['email'], $tok, $m) ? $ok++ : $bad++; usleep(250000); }
        $note = "Sent to $ok" . ($bad ? ", $bad failed" : '') . '.';
    }
}
$msg = jload('message.json', ['subject' => 'This week at NPS', 'body' => "Hi Raiders!\n\nHere’s what’s happening this week…"]);
$subs = jload('subscribers.json', []);
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Admin</title>
<style>body{font-family:system-ui,sans-serif;background:#1a0c08;color:#fff3e0;max-width:760px;margin:0 auto;padding:20px}
.c{background:#2a130c;border:1px solid #5a2e1a;border-radius:14px;padding:16px;margin:14px 0}
input,textarea{width:100%;background:#120704;color:#fff3e0;border:1px solid #5a2e1a;border-radius:8px;padding:10px;font:inherit;box-sizing:border-box;margin:6px 0}
button,.b{background:#ff8f1f;color:#220d02;border:0;border-radius:99px;padding:9px 16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block;font-size:14px}
.g{background:transparent;color:#d9b99b;border:1px solid #5a2e1a}.n{background:rgba(255,200,61,.15);border:1px solid #ffc83d;padding:10px;border-radius:8px}
table{width:100%;font-size:13px;border-collapse:collapse}td{padding:6px;border-bottom:1px solid #5a2e1a}</style></head><body>
<h1>🦃 Newsletter admin</h1>
<?php if ($note): ?><p class="n"><?= h($note) ?></p><?php endif; ?>
<?php if (!$in): ?>
<form method="post" class="c"><label>Password</label><input type="password" name="password" autofocus autocomplete="current-password"><button>Log in</button></form>
<?php else: $cs = h($_SESSION['csrf']); ?>
<form method="post" class="c"><input type="hidden" name="csrf" value="<?= $cs ?>">
<h3>Email message</h3><label>Subject</label><input name="subject" value="<?= h($msg['subject']) ?>">
<label>Body (plain text — an unsubscribe link is added automatically)</label><textarea name="body" rows="12"><?= h($msg['body']) ?></textarea>
<button name="a" value="save">Save</button>
<hr style="border-color:#5a2e1a;margin:14px 0">
<input name="to" type="email" placeholder="Send a test to…"><button name="a" value="test" class="g">Send test</button>
<button name="a" value="send" onclick="return confirm('Send to <?= count($subs) ?> subscribers now?')">Send to all (<?= count($subs) ?>)</button></form>
<div class="c"><h3>Subscribers (<?= count($subs) ?>)</h3><table>
<?php foreach ($subs as $id => $s): ?><tr><td><?= h($s['email']) ?></td><td><?= h(substr($s['joined'], 0, 10)) ?></td><td>
<form method="post"><input type="hidden" name="csrf" value="<?= $cs ?>"><input type="hidden" name="id" value="<?= h($id) ?>"><button name="a" value="del" class="g" style="padding:3px 10px" onclick="return confirm('Remove?')">✕</button></form></td></tr><?php endforeach; ?></table>
<p><a class="b g" href="?dl=csv">Export CSV</a> <a class="b g" href="?dl=backup">Download full backup (.zip)</a></p></div>
<form method="post"><input type="hidden" name="csrf" value="<?= $cs ?>"><button name="a" value="logout" class="g">Log out</button></form>
<?php endif; ?></body></html>
