<?php
require __DIR__ . '/lib.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$note = '';

function send_one($to, $tok, $m) {
    $un = NEWSLETTER_URL . '/unsubscribe.php?t=' . $tok;
    $hdr = ['From: ' . SITE_NAME . ' <' . FROM_EMAIL . '>', 'Content-Type: text/plain; charset=UTF-8', "List-Unsubscribe: <$un>"];
    return mail($to, mb_encode_mimeheader($m['subject'], 'UTF-8'), $m['body'] . "\n\n--\nUnsubscribe: $un", implode("\r\n", $hdr));
}

// ---- login (locks an IP for 15 min after 5 bad tries) ----
if (isset($_POST['password'])) {
    $t = jload('login.json', []);
    $r = $t[$ip] ?? ['n' => 0, 'until' => 0];
    if ($r['until'] > time()) {
        $note = 'Too many attempts. Try again later.';
    } elseif (password_verify($_POST['password'], ADMIN_HASH)) {
        session_regenerate_id(true);
        $_SESSION['ok'] = 1;
        unset($t[$ip]);
        jsave('login.json', $t, false);
        header('Location: admin.php');
        exit;
    } else {
        $r['n']++;
        if ($r['n'] >= 5) {
            $r['until'] = time() + 900;
            $r['n'] = 0;
        }
        $t[$ip] = $r;
        jsave('login.json', $t, false);
        sleep(1);
        $note = 'Wrong password.';
    }
}

$in = !empty($_SESSION['ok']);

// ---- downloads ----
if ($in && isset($_GET['dl'])) {
    if ($_GET['dl'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="subscribers.csv"');
        echo "email,joined\n";
        foreach (jload('subscribers.json', []) as $s) {
            echo $s['email'] . ',' . $s['joined'] . "\n";
        }
        exit;
    }
    if ($_GET['dl'] === 'backup' && class_exists('ZipArchive')) {
        $z = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        $z->open($tmp, ZipArchive::OVERWRITE);
        foreach (glob(DATA_DIR . '/*.json') as $f) {
            $z->addFile($f, basename($f));
        }
        foreach (glob(DATA_DIR . '/backups/*') as $f) {
            $z->addFile($f, 'backups/' . basename($f));
        }
        $z->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="backup-' . date('Ymd') . '.zip"');
        readfile($tmp);
        unlink($tmp);
        exit;
    }
}

// ---- actions ----
if ($in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['a'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Bad token');
    }

    $a = $_POST['a'];
    $m = ['subject' => trim($_POST['subject'] ?? ''), 'body' => trim($_POST['body'] ?? '')];

    if ($a === 'logout') {
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    if ($a === 'del') {
        $s = jload('subscribers.json', []);
        unset($s[$_POST['id'] ?? '']);
        jsave('subscribers.json', $s);
        $note = 'Removed.';
    }
    if ($a === 'save' || $a === 'test' || $a === 'send') {
        jsave('message.json', $m + ['updated' => date('c')]);
        $note = 'Saved (backup made).';
    }
    if ($a === 'test') {
        $to = trim($_POST['to'] ?? '');
        $note = filter_var($to, FILTER_VALIDATE_EMAIL) && send_one($to, 'test', $m)
            ? "Test sent to $to."
            : 'Test failed — check the address / mail setup.';
    }
    if ($a === 'send') {
        set_time_limit(0);
        $ok = 0;
        $bad = 0;
        foreach (jload('subscribers.json', []) as $tok => $s) {
            send_one($s['email'], $tok, $m) ? $ok++ : $bad++;
            usleep(250000);
        }
        $note = "Sent to $ok" . ($bad ? ", $bad failed" : '') . '.';
    }
}

$msg = jload('message.json', ['subject' => 'This week at NPS', 'body' => "Hi Raiders!\n\nHere’s what’s happening this week…"]);
$subs = jload('subscribers.json', []);
$subCount = count($subs);
$lastSaved = $msg['updated'] ?? 'Never';
$backupCount = is_dir(DATA_DIR . '/backups') ? count(glob(DATA_DIR . '/backups/*')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin Dashboard</title>
    <style>
        :root {
            --bg: #120b09;
            --panel: #1d110d;
            --panel-strong: #2a170f;
            --line: #5f3320;
            --text: #f5ebdf;
            --muted: #d7bfa7;
            --gold: #ffc83d;
            --orange: #ff8f1f;
            --orange-deep: #d96d16;
            --danger: #ffb0a3;
            --success: #bef7bf;
            --shadow: rgba(0, 0, 0, 0.28);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(circle at top, rgba(255, 143, 31, 0.12), transparent 30%),
                linear-gradient(180deg, #140b09 0%, #1b0d09 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .wrap {
            max-width: 1160px;
            margin: 0 auto;
            padding: 28px 18px 48px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 22px;
            padding: 12px 16px;
            background: rgba(26, 15, 11, 0.9);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 18px 42px var(--shadow);
        }

        .brand {
            font-size: clamp(1.3rem, 2vw, 2rem);
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .tiny {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .links {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn, button, .link-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, var(--orange) 0%, var(--orange-deep) 100%);
            color: #220d02;
            border-radius: 999px;
            font-weight: 800;
            text-decoration: none;
            font-size: 14px;
            line-height: 1;
            padding: 10px 16px;
            cursor: pointer;
            transition: transform 0.15s ease, filter 0.15s ease;
            display: inline-block;
        }

        .btn:hover, button:hover, .link-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        .btn.secondary, .link-btn.secondary {
            background: transparent;
            color: var(--muted);
        }

        .alert {
            background: rgba(255, 200, 61, 0.08);
            border: 1px solid rgba(255, 200, 61, 0.45);
            color: var(--gold);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat {
            background: rgba(42, 23, 15, 0.9);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px 18px 16px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
        }

        .stat .label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .stat strong {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 900;
            line-height: 1.1;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.3fr 0.9fr;
            gap: 18px;
        }

        .panel {
            background: rgba(29, 17, 13, 0.92);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 18px 42px var(--shadow);
        }

        h2, h3 {
            margin: 0 0 14px;
            letter-spacing: 0.01em;
        }

        label {
            display: block;
            margin-top: 12px;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        input, textarea {
            width: 100%;
            background: rgba(18, 11, 9, 0.92);
            color: var(--text);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            font: inherit;
            resize: vertical;
        }

        input:focus, textarea:focus {
            outline: 2px solid rgba(255, 200, 61, 0.4);
            border-color: rgba(255, 200, 61, 0.6);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .secondary {
            background: transparent;
            color: var(--muted);
        }

        .list {
            list-style: none;
            padding: 0;
            margin: 12px 0 0;
            display: grid;
            gap: 10px;
        }

        .list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            background: rgba(18, 11, 9, 0.7);
            border-radius: 12px;
        }

        .email {
            font-weight: 600;
            word-break: break-all;
        }

        .date {
            color: var(--muted);
            font-size: 12px;
        }

        .row-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .danger {
            background: rgba(255, 176, 163, 0.08);
            color: var(--danger);
            border-color: rgba(255, 176, 163, 0.4);
        }

        .meta {
            color: var(--muted);
            font-size: 12px;
            margin-top: 8px;
        }

        @media (max-width: 800px) {
            .stats, .layout {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="topbar">
            <div>
                <div class="tiny">NPS School News</div>
                <div class="brand">Admin Dashboard</div>
            </div>
            <div class="links">
                <a class="link-btn secondary" href="/">View site</a>
                <?php if ($in): ?>
                    <a class="link-btn secondary" href="?dl=csv">Export CSV</a>
                    <a class="link-btn secondary" href="?dl=backup">Backup</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($note): ?>
            <div class="alert"><?= h($note) ?></div>
        <?php endif; ?>

        <?php if (!$in): ?>
            <div class="panel" style="max-width: 520px; margin: 30px auto 0;">
                <h2>Log in</h2>
                <form method="post">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" autofocus>
                    <div class="actions">
                        <button type="submit">Log in</button>
                    </div>
                </form>
            </div>
        <?php else: $cs = h($_SESSION['csrf']); ?>
            <div class="stats">
                <div class="stat">
                    <span class="label">Subscribers</span>
                    <strong><?= $subCount ?></strong>
                </div>
                <div class="stat">
                    <span class="label">Saved message</span>
                    <strong><?= h(substr($lastSaved, 0, 10)) ?></strong>
                </div>
                <div class="stat">
                    <span class="label">Backups</span>
                    <strong><?= $backupCount ?></strong>
                </div>
            </div>

            <div class="layout">
                <section class="panel">
                    <h2>Newsletter draft</h2>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= $cs ?>">
                        <label>Subject</label>
                        <input name="subject" value="<?= h($msg['subject']) ?>" placeholder="This week at NPS">

                        <label>Body</label>
                        <textarea name="body" rows="12" placeholder="Hi Raiders!"><?= h($msg['body']) ?></textarea>

                        <div class="actions">
                            <button type="submit" name="a" value="save">Save draft</button>
                            <button type="submit" class="secondary" name="a" value="test">Send test</button>
                            <button type="submit" name="a" value="send" onclick="return confirm('Send to <?= $subCount ?> subscribers now?')">Send to all</button>
                        </div>

                        <div class="meta">Send a test to:</div>
                        <input type="email" name="to" placeholder="name@example.com" style="margin-top: 8px;">
                    </form>
                </section>

                <aside class="panel">
                    <h3>Subscribers</h3>
                    <div class="meta">Total: <?= $subCount ?></div>
                    <ul class="list">
                        <?php foreach ($subs as $id => $s): ?>
                            <li>
                                <div>
                                    <div class="email"><?= h($s['email']) ?></div>
                                    <div class="date"><?= h(substr($s['joined'], 0, 10)) ?></div>
                                </div>
                                <form method="post" class="row-form">
                                    <input type="hidden" name="csrf" value="<?= $cs ?>">
                                    <input type="hidden" name="id" value="<?= h($id) ?>">
                                    <button class="secondary danger" type="submit" name="a" value="del" onclick="return confirm('Remove this subscriber?')">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="actions" style="margin-top: 18px;">
                        <a class="link-btn secondary" href="?dl=csv">Export CSV</a>
                        <a class="link-btn secondary" href="?dl=backup">Download backup</a>
                    </div>

                    <form method="post" style="margin-top: 18px;">
                        <input type="hidden" name="csrf" value="<?= $cs ?>">
                        <button type="submit" class="secondary" name="a" value="logout">Log out</button>
                    </form>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
