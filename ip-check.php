<?php
$BLOCKED_IPS_FILE = __DIR__ . '/blocked_ips.json';

function _getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$_ip = _getClientIp();
$_blocked = file_exists($BLOCKED_IPS_FILE) ? (json_decode(file_get_contents($BLOCKED_IPS_FILE), true) ?: []) : [];
foreach ($_blocked as $_b) {
    if ($_b['ip'] === $_ip) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>ERREUR 404</title></head><body style="margin:0;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:monospace;text-align:center"><div><h1 style="font-size:48px;margin:0 0 20px">ERREUR 404</h1><p style="font-size:24px;color:#ff4444">Votre appareil est suspect</p></div></body></html>';
        exit;
    }
}
