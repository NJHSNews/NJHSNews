<?php
require __DIR__ . '/config.php';
if (!is_dir(DATA_DIR . '/backups')) {
    mkdir(DATA_DIR . '/backups', 0750, true);
    file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
}
function jload($f, $d) {
    $p = DATA_DIR . "/$f";
    return is_file($p) ? (json_decode(file_get_contents($p), true) ?? $d) : $d;
}
function jsave($f, $v, $backup = true) {
    $p = DATA_DIR . "/$f";
    $fp = fopen($p, 'c+'); flock($fp, LOCK_EX);
    ftruncate($fp, 0); fwrite($fp, json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    if ($backup) {                                   // timestamped backup on every change
        copy($p, DATA_DIR . '/backups/' . date('Ymd-His') . "-$f");
        $all = glob(DATA_DIR . "/backups/*-$f"); rsort($all);
        foreach (array_slice($all, BACKUP_KEEP) as $old) unlink($old);
    }
}
