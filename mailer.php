<?php
// ── SMTP Configuration (o2switch) ──
$SMTP_HOST = 'mail.ztrace.fr';
$SMTP_USER = 'contact@ztrace.fr';
$SMTP_PASS = 'TON_MOT_DE_PASSE';           // ← mot de passe du compte email
$SMTP_PORT = 465;
$SMTP_FROM_NAME = 'IkaShop';

function smtpSend($to, $subject, $htmlBody) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_NAME;

    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);

    if ($SMTP_PORT === 465) {
        $socket = @stream_socket_client(
            "ssl://{$SMTP_HOST}:{$SMTP_PORT}",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT, $context
        );
    } else {
        $socket = @stream_socket_client(
            "tcp://{$SMTP_HOST}:{$SMTP_PORT}",
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT, $context
        );
    }

    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }

    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '220') { fclose($socket); return false; }

    smtpWrite($socket, "EHLO localhost");
    smtpReadMulti($socket);

    // STARTTLS only for port 587
    if ($SMTP_PORT === 587) {
        smtpWrite($socket, "STARTTLS");
        $resp = smtpRead($socket);
        if (substr($resp, 0, 3) !== '220') { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            error_log("SMTP TLS handshake failed");
            fclose($socket);
            return false;
        }
        smtpWrite($socket, "EHLO localhost");
        smtpReadMulti($socket);
    }

    smtpWrite($socket, "AUTH LOGIN");
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '334') { fclose($socket); return false; }

    smtpWrite($socket, base64_encode($SMTP_USER));
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '334') { fclose($socket); return false; }

    smtpWrite($socket, base64_encode($SMTP_PASS));
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '235') {
        error_log("SMTP auth failed: $resp");
        fclose($socket);
        return false;
    }

    smtpWrite($socket, "MAIL FROM:<{$SMTP_USER}>");
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '250') { fclose($socket); return false; }

    smtpWrite($socket, "RCPT TO:<{$to}>");
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '250') { fclose($socket); return false; }

    smtpWrite($socket, "DATA");
    $resp = smtpRead($socket);
    if (substr($resp, 0, 3) !== '354') { fclose($socket); return false; }

    $msg  = "From: {$SMTP_FROM_NAME} <{$SMTP_USER}>\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: <" . uniqid() . "@ikashop.fr>\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.\r\n";

    fwrite($socket, $msg);
    $resp = smtpRead($socket);

    smtpWrite($socket, "QUIT");
    @smtpRead($socket);
    fclose($socket);

    $ok = substr($resp, 0, 3) === '250';
    if (!$ok) error_log("SMTP send failed: $resp");
    return $ok;
}

function smtpWrite($socket, $data) {
    fwrite($socket, $data . "\r\n");
}

function smtpRead($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ' || strlen($line) < 4) break;
    }
    return trim($response);
}

function smtpReadMulti($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return trim($response);
}
