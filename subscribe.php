<?php
require __DIR__ . '/lib.php';
header('Content-Type: application/json');
function out($ok, $m, $c = 200) { http_response_code($c); echo json_encode(['ok' => $ok, 'msg' => $m]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'POST only', 405);
$in = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);
if (!empty($in['website'])) out(true, 'Subscribed!');            // honeypot: bots fill this
$email = strtolower(trim($in['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) out(false, 'Enter a valid email.', 422);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0'; $now = time();
$rl = jload('rate.json', []);
$rl[$ip] = array_values(array_filter($rl[$ip] ?? [], fn($t) => $t > $now - 3600));
if (count($rl[$ip]) >= 5) out(false, 'Too many tries. Try again later.', 429);
$rl[$ip][] = $now; jsave('rate.json', $rl, false);

$subs = jload('subscribers.json', []);
foreach ($subs as $s) if ($s['email'] === $email) out(true, 'You’re already on the list!');
$subs[bin2hex(random_bytes(16))] = ['email' => $email, 'joined' => date('c')];
jsave('subscribers.json', $subs);
out(true, 'Subscribed! 🦃');
