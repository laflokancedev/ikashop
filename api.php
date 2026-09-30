<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$mailerFile = __DIR__ . '/mailer.php';
if (file_exists($mailerFile)) require_once $mailerFile;

$DATA_FILE = __DIR__ . '/shipments.json';
$ORDERS_FILE = __DIR__ . '/orders.json';
$ADMIN_PWD = 'Forcia06';
$STRIPE_SK = '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';

$PROMOS_FILE = __DIR__ . '/promos.json';
$IP_LOGS_FILE = __DIR__ . '/ip_logs.json';
$BLOCKED_IPS_FILE = __DIR__ . '/blocked_ips.json';

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isBlockedIp() {
    global $BLOCKED_IPS_FILE;
    $ip = getClientIp();
    $blocked = readJson($BLOCKED_IPS_FILE);
    foreach ($blocked as $entry) {
        if ($entry['ip'] === $ip) return true;
    }
    return false;
}

function logIpTracking($trackingNumber) {
    global $IP_LOGS_FILE;
    $ip = getClientIp();
    $logs = readJson($IP_LOGS_FILE);
    $logs[] = [
        'ip' => $ip,
        'trackingNumber' => $trackingNumber,
        'timestamp' => date('c'),
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    if (count($logs) > 5000) $logs = array_slice($logs, -5000);
    file_put_contents($IP_LOGS_FILE, json_encode($logs, JSON_PRETTY_PRINT));
}

function getPromos() {
    global $PROMOS_FILE;
    return readJson($PROMOS_FILE);
}

function writePromos($data) {
    global $PROMOS_FILE;
    file_put_contents($PROMOS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getPromo($code) {
    foreach (getPromos() as $p) {
        if ($p['code'] === $code) return $p;
    }
    return null;
}

function validatePromo($code) {
    $promo = getPromo($code);
    if (!$promo) return ['valid' => false, 'reason' => 'Invalid promo code'];
    if ($promo['maxUses'] > 0 && $promo['usedCount'] >= $promo['maxUses']) {
        return ['valid' => false, 'reason' => 'This code has reached its maximum uses'];
    }
    if (!empty($promo['expiresAt']) && time() > strtotime($promo['expiresAt'])) {
        return ['valid' => false, 'reason' => 'This code has expired'];
    }
    return ['valid' => true, 'type' => $promo['type'], 'value' => intval($promo['value'])];
}

function incrementPromoUse($code) {
    if (!$code) return;
    $promos = getPromos();
    foreach ($promos as &$p) {
        if ($p['code'] === $code) {
            $p['usedCount'] = ($p['usedCount'] ?? 0) + 1;
            break;
        }
    }
    unset($p);
    writePromos($promos);
}

function readJson($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

function writeJson($file, $data) {
    file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function checkAuth() {
    global $ADMIN_PWD;
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $pwd = $headers['x-admin-password'] ?? '';
    if ($pwd !== $ADMIN_PWD) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function sendOrderEmail($order) {
    $email = $order['email'];
    $name = htmlspecialchars($order['name']);
    $orderId = htmlspecialchars($order['id']);
    $price = number_format(floatval($order['price']), 2, '.', '');
    $subtotal = number_format(floatval($order['subtotal']), 2, '.', '');
    $shipping = number_format(floatval($order['shipping']), 2, '.', '');
    $vat = number_format(floatval($order['vat']), 2, '.', '');
    $discount = floatval($order['discount']);
    $promoDiscount = floatval($order['promoDiscount']);

    $productsHtml = '';
    $products = $order['products'] ?? [];
    foreach ($products as $p) {
        $pName = htmlspecialchars($p['name'] ?? '');
        $pMat = htmlspecialchars($p['material'] ?? '');
        $pPrice = number_format(floatval($p['price'] ?? 0), 2, '.', '');
        $productsHtml .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;font-size:14px">' . $pName . '<br><span style="color:#888;font-size:12px">' . $pMat . '</span></td><td style="padding:12px 16px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:600;font-size:14px">' . $pPrice . ' &euro;</td></tr>';
    }

    $discountRows = '';
    if ($discount > 0) {
        $discountRows .= '<tr><td style="padding:6px 16px;color:#16a34a;font-size:14px">3rd wheel -50%</td><td style="padding:6px 16px;text-align:right;color:#16a34a;font-weight:600;font-size:14px">-' . number_format($discount, 2, '.', '') . ' &euro;</td></tr>';
    }
    if ($promoDiscount > 0) {
        $discountRows .= '<tr><td style="padding:6px 16px;color:#16a34a;font-size:14px">Promo code</td><td style="padding:6px 16px;text-align:right;color:#16a34a;font-weight:600;font-size:14px">-' . number_format($promoDiscount, 2, '.', '') . ' &euro;</td></tr>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff">'
        . '<div style="background:#0a0a0a;padding:32px 24px;text-align:center">'
        . '<h1 style="color:#fff;font-size:28px;letter-spacing:6px;margin:0;font-weight:300">IKASHOP</h1>'
        . '</div>'
        . '<div style="padding:40px 32px;text-align:center">'
        . '<div style="width:56px;height:56px;border-radius:50%;background:#dbeafe;margin:0 auto 16px;line-height:56px;font-size:24px">&#128230;</div>'
        . '<h2 style="margin:0 0 8px;font-size:22px;color:#111">Order Created</h2>'
        . '<p style="color:#666;font-size:14px;margin:0 0 24px">Thank you ' . $name . ', your order has been registered.</p>'
        . '<div style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:24px">'
        . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#888;margin-bottom:4px">Order Number</div>'
        . '<div style="font-size:24px;font-weight:700;letter-spacing:2px;color:#111">' . $orderId . '</div>'
        . '</div>'
        . '</div>'
        . '<div style="padding:0 32px 32px">'
        . '<h3 style="font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#888;margin:0 0 12px;font-weight:600">Your items</h3>'
        . '<table style="width:100%;border-collapse:collapse;background:#fafafa;border-radius:8px;overflow:hidden">'
        . $productsHtml
        . '</table>'
        . '</div>'
        . '<div style="padding:0 32px 32px">'
        . '<h3 style="font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#888;margin:0 0 12px;font-weight:600">Price breakdown</h3>'
        . '<table style="width:100%;border-collapse:collapse">'
        . '<tr><td style="padding:6px 16px;font-size:14px;color:#444">Subtotal</td><td style="padding:6px 16px;text-align:right;font-size:14px">' . $subtotal . ' &euro;</td></tr>'
        . '<tr><td style="padding:6px 16px;font-size:14px;color:#444">Shipping</td><td style="padding:6px 16px;text-align:right;font-size:14px">' . $shipping . ' &euro;</td></tr>'
        . '<tr><td style="padding:6px 16px;font-size:14px;color:#444">VAT included</td><td style="padding:6px 16px;text-align:right;font-size:14px">' . $vat . ' &euro;</td></tr>'
        . $discountRows
        . '<tr><td style="padding:12px 16px;font-size:18px;font-weight:700;border-top:2px solid #e2e8f0">Total</td><td style="padding:12px 16px;text-align:right;font-size:18px;font-weight:700;border-top:2px solid #e2e8f0;color:#0070ba">' . $price . ' &euro;</td></tr>'
        . '</table>'
        . '</div>'
        . '<div style="padding:0 32px 32px;text-align:center">'
        . '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:16px;margin-bottom:24px">'
        . '<p style="margin:0;font-size:13px;color:#9a3412"><strong>Payment pending</strong> — Please complete your payment via PayPal. Your order will be confirmed once the payment is received.</p>'
        . '</div>'
        . '</div>'
        . '<div style="background:#f8fafc;padding:24px 32px;text-align:center;border-top:1px solid #e2e8f0">'
        . '<p style="margin:0;font-size:12px;color:#888">IKASHOP &copy; 2026 — Premium Steering Wheels</p>'
        . '<p style="margin:4px 0 0;font-size:12px;color:#aaa">Contact us on Telegram: @ikaagent</p>'
        . '</div>'
        . '</div>'
        . '</body></html>';

    $subject = 'IkaShop — Order ' . $order['id'] . ' created';
    smtpSend($email, $subject, $html);
}

function sendConfirmationEmail($order) {
    $email = $order['email'] ?? '';
    if (!$email) return;
    $name = htmlspecialchars($order['name']);
    $orderId = htmlspecialchars($order['id']);
    $tn = htmlspecialchars($order['trackingNumber']);
    $price = number_format(floatval($order['price']), 2, '.', '');

    $productsHtml = '';
    $products = $order['products'] ?? [];
    foreach ($products as $p) {
        $pName = htmlspecialchars($p['name'] ?? '');
        $productsHtml .= '<li style="padding:4px 0;font-size:14px;color:#444">' . $pName . '</li>';
    }
    if (!$productsHtml && !empty($order['productName'])) {
        $productsHtml = '<li style="padding:4px 0;font-size:14px;color:#444">' . htmlspecialchars($order['productName']) . '</li>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff">'
        . '<div style="background:#0a0a0a;padding:32px 24px;text-align:center">'
        . '<h1 style="color:#fff;font-size:28px;letter-spacing:6px;margin:0;font-weight:300">IKASHOP</h1>'
        . '</div>'
        . '<div style="padding:40px 32px;text-align:center">'
        . '<div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;margin:0 auto 16px;line-height:56px;font-size:24px">&#9989;</div>'
        . '<h2 style="margin:0 0 8px;font-size:22px;color:#111">Order Confirmed!</h2>'
        . '<p style="color:#666;font-size:14px;margin:0 0 24px">Hi ' . $name . ', your payment has been received and your order is confirmed.</p>'
        . '<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:16px">'
        . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#888;margin-bottom:4px">Order Number</div>'
        . '<div style="font-size:20px;font-weight:700;letter-spacing:1px;color:#111">' . $orderId . '</div>'
        . '</div>'
        . '<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:16px">'
        . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#888;margin-bottom:4px">Tracking Number</div>'
        . '<div style="font-size:20px;font-weight:700;letter-spacing:2px;color:#16a34a">' . $tn . '</div>'
        . '</div>'
        . '<div style="background:#f8fafc;border-radius:8px;padding:16px;margin-bottom:24px;text-align:left">'
        . '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#111">Items ordered:</p>'
        . '<ul style="margin:0;padding-left:20px">' . $productsHtml . '</ul>'
        . '<p style="margin:12px 0 0;font-size:14px"><strong>Total paid:</strong> ' . $price . ' &euro;</p>'
        . '</div>'
        . '<p style="color:#666;font-size:13px;margin:0 0 24px">Your order is being prepared and will be shipped via <strong>Wochen</strong>. Estimated delivery: <strong>2-3 weeks</strong>. You will receive tracking updates on Telegram.</p>'
        . '</div>'
        . '<div style="background:#f8fafc;padding:24px 32px;text-align:center;border-top:1px solid #e2e8f0">'
        . '<p style="margin:0;font-size:12px;color:#888">IKASHOP &copy; 2026 — Premium Steering Wheels</p>'
        . '<p style="margin:4px 0 0;font-size:12px;color:#aaa">Contact us on Telegram: @ikaagent</p>'
        . '</div>'
        . '</div>'
        . '</body></html>';

    $subject = 'IkaShop — Order ' . $order['id'] . ' confirmed!';
    smtpSend($email, $subject, $html);
}

function generateTN() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $nums = '0123456789';
    $tn = 'WCH';
    for ($i = 0; $i < 2; $i++) $tn .= $chars[random_int(0, strlen($chars) - 1)];
    for ($i = 0; $i < 8; $i++) $tn .= $nums[random_int(0, strlen($nums) - 1)];
    return $tn;
}

function stripeRequest($endpoint, $data) {
    global $STRIPE_SK;
    $ch = curl_init('https://api.stripe.com/v1/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, $STRIPE_SK . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function stripeGet($endpoint) {
    global $STRIPE_SK;
    $ch = curl_init('https://api.stripe.com/v1/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $STRIPE_SK . ':');
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function calcServerPrice($input) {
    $subtotal = floatval($input['price'] ?? 0);
    $promoCode = $input['promoCode'] ?? '';

    $v = validatePromo($promoCode);
    if ($v['valid']) {
        if ($v['type'] === 'fixed') {
            return intval($v['value']);
        } else if ($v['type'] === 'percent') {
            return intval(round($subtotal * 100 * (100 - $v['value']) / 100));
        }
    }
    return intval(round($subtotal * 100));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Block banned IPs on public endpoints
if (!in_array($action, ['shipments', 'orders', 'promos', 'confirm-order', 'ip-logs', 'blocked-ips']) && $method !== 'OPTIONS') {
    if (isBlockedIp()) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>ERREUR 404</title></head><body style="margin:0;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:monospace;text-align:center"><div><h1 style="font-size:48px;margin:0 0 20px">ERREUR 404</h1><p style="font-size:24px;color:#ff4444">Votre appareil est suspect</p></div></body></html>';
        exit;
    }
}

// ── GET all shipments (admin) ──
if ($method === 'GET' && $action === 'shipments') {
    echo json_encode(readJson($DATA_FILE));
    exit;
}

// ── GET track a single shipment (public) ──
if ($method === 'GET' && $action === 'track') {
    $tn = strtoupper(trim($_GET['tn'] ?? ''));
    if (!$tn) { http_response_code(400); echo json_encode(['error' => 'Missing tracking number']); exit; }
    logIpTracking($tn);
    foreach (readJson($DATA_FILE) as $s) {
        if (strtoupper($s['trackingNumber']) === $tn) { echo json_encode($s); exit; }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// ── POST create shipment (admin) ──
if ($method === 'POST' && $action === 'shipments') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['recipientName']) || empty($input['recipientAddress']) || empty($input['recipientCity']) || empty($input['estimatedDelivery'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    $all = readJson($DATA_FILE);
    $tn = strtoupper(trim($input['trackingNumber'] ?? ''));
    if (!$tn) $tn = generateTN();
    foreach ($all as $s) {
        if ($s['trackingNumber'] === $tn) {
            http_response_code(409);
            echo json_encode(['error' => 'Tracking number already exists']);
            exit;
        }
    }
    $shipment = [
        'trackingNumber' => $tn,
        'recipientName' => $input['recipientName'],
        'recipientAddress' => $input['recipientAddress'],
        'recipientPostal' => $input['recipientPostal'] ?? '',
        'recipientCity' => $input['recipientCity'],
        'estimatedDelivery' => $input['estimatedDelivery'],
        'status' => $input['status'] ?? 'registered',
        'createdAt' => date('c')
    ];
    $all[] = $shipment;
    writeJson($DATA_FILE, $all);
    echo json_encode($shipment);
    exit;
}

// ── PUT update shipment (admin) ──
if ($method === 'PUT' && $action === 'shipments') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $tn = $input['trackingNumber'] ?? '';
    if (!$tn) { http_response_code(400); echo json_encode(['error' => 'Missing tracking number']); exit; }
    $all = readJson($DATA_FILE);
    $found = false;
    foreach ($all as &$s) {
        if ($s['trackingNumber'] === $tn) {
            if (isset($input['recipientName'])) $s['recipientName'] = $input['recipientName'];
            if (isset($input['recipientAddress'])) $s['recipientAddress'] = $input['recipientAddress'];
            if (isset($input['recipientPostal'])) $s['recipientPostal'] = $input['recipientPostal'];
            if (isset($input['recipientCity'])) $s['recipientCity'] = $input['recipientCity'];
            if (isset($input['estimatedDelivery'])) $s['estimatedDelivery'] = $input['estimatedDelivery'];
            if (isset($input['status'])) $s['status'] = $input['status'];
            $found = $s;
            break;
        }
    }
    unset($s);
    if (!$found) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writeJson($DATA_FILE, $all);
    echo json_encode($found);
    exit;
}

// ── DELETE shipment (admin) ──
if ($method === 'DELETE' && $action === 'shipments') {
    checkAuth();
    $tn = $_GET['tn'] ?? '';
    if (!$tn) { http_response_code(400); echo json_encode(['error' => 'Missing tracking number']); exit; }
    $all = readJson($DATA_FILE);
    $newAll = array_filter($all, function($s) use ($tn) { return $s['trackingNumber'] !== $tn; });
    if (count($newAll) === count($all)) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writeJson($DATA_FILE, $newAll);
    echo json_encode(['success' => true]);
    exit;
}

// ── POST create Stripe Checkout Session ──
if ($method === 'POST' && $action === 'create-checkout') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Please fill in all required fields']);
        exit;
    }

    $promoCode = $input['promoCode'] ?? '';
    $totalPrice = floatval($input['price'] ?? 0);
    $amountCents = intval(round($totalPrice * 100));

    $v = validatePromo($promoCode);
    if ($v['valid']) {
        if ($v['type'] === 'fixed') {
            $amountCents = intval($v['value']);
        } else if ($v['type'] === 'percent') {
            $amountCents = intval(round($amountCents * (100 - $v['value']) / 100));
        }
    }

    if ($amountCents < 50) $amountCents = 50;

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $baseUrl = 'https://ztrace.fr';
    } else {
        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $baseUrl = $proto . '://' . $host . $dir;
    }

    $orderData = json_encode([
        'email' => $input['email'],
        'name' => $input['name'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'postal' => $input['postal'] ?? '',
        'city' => $input['city'] ?? '',
        'country' => $input['country'] ?? 'FR',
        'productId' => $input['productId'] ?? '',
        'productName' => $input['productName'] ?? '',
        'promoCode' => $promoCode
    ]);

    $sessionData = [
        'mode' => 'payment',
        'customer_email' => $input['email'],
        'managed_payments[enabled]' => 'false',
        'line_items[0][price_data][currency]' => 'eur',
        'line_items[0][price_data][product_data][name]' => 'IkaShop — ' . ($input['productName'] ?? 'Steering Wheels'),
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][quantity]' => 1,
        'metadata[order_data]' => $orderData,
        'metadata[amount]' => $amountCents,
        'success_url' => $baseUrl . '/checkout?success=1&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/checkout?canceled=1'
    ];

    $result = stripeRequest('checkout/sessions', $sessionData);

    if (isset($result['error'])) {
        http_response_code(400);
        echo json_encode(['error' => $result['error']['message'] ?? 'Stripe error']);
        exit;
    }

    echo json_encode(['url' => $result['url'], 'sessionId' => $result['id']]);
    exit;
}

// ── GET confirm order after Stripe payment ──
if ($method === 'GET' && $action === 'confirm-order') {
    $sessionId = $_GET['session_id'] ?? '';
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing session ID']);
        exit;
    }

    $session = stripeGet('checkout/sessions/' . $sessionId);
    if (isset($session['error'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session']);
        exit;
    }

    if ($session['payment_status'] !== 'paid') {
        http_response_code(400);
        echo json_encode(['error' => 'Payment not completed']);
        exit;
    }

    $orders = readJson($ORDERS_FILE);
    foreach ($orders as $o) {
        if (isset($o['stripeSession']) && $o['stripeSession'] === $sessionId) {
            echo json_encode(['success' => true, 'trackingNumber' => $o['trackingNumber'], 'orderId' => $o['id']]);
            exit;
        }
    }

    $orderMeta = json_decode($session['metadata']['order_data'] ?? '{}', true);
    $tn = generateTN();
    $deliveryDate = date('Y-m-d', strtotime('+21 days'));
    $amountPaid = ($session['amount_total'] ?? 0) / 100;

    $shipments = readJson($DATA_FILE);
    $shipment = [
        'trackingNumber' => $tn,
        'recipientName' => $orderMeta['name'] ?? '',
        'recipientAddress' => $orderMeta['address'] ?? '',
        'recipientPostal' => $orderMeta['postal'] ?? '',
        'recipientCity' => $orderMeta['city'] ?? '',
        'estimatedDelivery' => $deliveryDate,
        'status' => 'registered',
        'createdAt' => date('c')
    ];
    $shipments[] = $shipment;
    writeJson($DATA_FILE, $shipments);

    $order = [
        'id' => uniqid('ORD-'),
        'trackingNumber' => $tn,
        'stripeSession' => $sessionId,
        'email' => $orderMeta['email'] ?? '',
        'name' => $orderMeta['name'] ?? '',
        'phone' => $orderMeta['phone'] ?? '',
        'address' => $orderMeta['address'] ?? '',
        'postal' => $orderMeta['postal'] ?? '',
        'city' => $orderMeta['city'] ?? '',
        'country' => $orderMeta['country'] ?? 'FR',
        'productId' => $orderMeta['productId'] ?? '',
        'productName' => $orderMeta['productName'] ?? '',
        'promoCode' => $orderMeta['promoCode'] ?? '',
        'price' => $amountPaid,
        'status' => 'paid',
        'createdAt' => date('c')
    ];
    $orders[] = $order;
    writeJson($ORDERS_FILE, $orders);

    incrementPromoUse($orderMeta['promoCode'] ?? '');

    echo json_encode(['success' => true, 'trackingNumber' => $tn, 'orderId' => $order['id']]);
    exit;
}

// ── POST new order (legacy fallback) ──
if ($method === 'POST' && $action === 'order') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['email']) || empty($input['address']) || empty($input['city']) || empty($input['postal'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Please fill in all required fields']);
        exit;
    }

    $tn = generateTN();
    $deliveryDate = date('Y-m-d', strtotime('+21 days'));

    $shipments = readJson($DATA_FILE);
    $shipment = [
        'trackingNumber' => $tn,
        'recipientName' => $input['name'],
        'recipientAddress' => $input['address'],
        'recipientPostal' => $input['postal'],
        'recipientCity' => $input['city'],
        'estimatedDelivery' => $deliveryDate,
        'status' => 'registered',
        'createdAt' => date('c')
    ];
    $shipments[] = $shipment;
    writeJson($DATA_FILE, $shipments);

    $orders = readJson($ORDERS_FILE);
    $order = [
        'id' => uniqid('ORD-'),
        'trackingNumber' => $tn,
        'email' => $input['email'],
        'name' => $input['name'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'],
        'postal' => $input['postal'],
        'city' => $input['city'],
        'country' => $input['country'] ?? 'FR',
        'productId' => $input['productId'] ?? '',
        'productName' => $input['productName'] ?? '',
        'price' => $input['price'] ?? 0,
        'promoCode' => $input['promoCode'] ?? '',
        'status' => 'paid',
        'createdAt' => date('c')
    ];
    $orders[] = $order;
    writeJson($ORDERS_FILE, $orders);

    echo json_encode(['success' => true, 'trackingNumber' => $tn, 'orderId' => $order['id']]);
    exit;
}

// ── POST create PayPal order + send email ──
if ($method === 'POST' && $action === 'paypal-order') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['email']) || empty($input['address']) || empty($input['city']) || empty($input['postal'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Please fill in all required fields']);
        exit;
    }

    $orderId = 'IKA-' . strtoupper(substr(uniqid(), -8));
    $price = floatval($input['price'] ?? 0);

    $orders = readJson($ORDERS_FILE);
    $order = [
        'id' => $orderId,
        'trackingNumber' => '',
        'email' => $input['email'],
        'telegram' => $input['telegram'] ?? '',
        'name' => $input['name'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'],
        'postal' => $input['postal'],
        'city' => $input['city'],
        'country' => $input['country'] ?? 'FR',
        'productId' => $input['productId'] ?? '',
        'productName' => $input['productName'] ?? '',
        'products' => $input['products'] ?? [],
        'price' => $price,
        'subtotal' => floatval($input['subtotal'] ?? 0),
        'shipping' => floatval($input['shipping'] ?? 0),
        'vat' => floatval($input['vat'] ?? 0),
        'discount' => floatval($input['discount'] ?? 0),
        'promoDiscount' => floatval($input['promoDiscount'] ?? 0),
        'paypalDiscount' => 0,
        'promoCode' => $input['promoCode'] ?? '',
        'status' => 'pending',
        'createdAt' => date('c')
    ];
    $orders[] = $order;
    writeJson($ORDERS_FILE, $orders);

    incrementPromoUse($input['promoCode'] ?? '');
    sendOrderEmail($order);

    echo json_encode(['success' => true, 'orderId' => $orderId]);
    exit;
}

// ── POST confirm order (admin) + send confirmation email ──
if ($method === 'POST' && $action === 'confirm-order') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['id'] ?? '';
    if (!$orderId) { http_response_code(400); echo json_encode(['error' => 'Missing order ID']); exit; }

    $all = readJson($ORDERS_FILE);
    $found = false;
    foreach ($all as &$o) {
        if ($o['id'] === $orderId) {
            $o['status'] = 'confirmed';
            $tn = generateTN();
            $o['trackingNumber'] = $tn;
            $found = $o;

            $shipments = readJson($DATA_FILE);
            $shipment = [
                'trackingNumber' => $tn,
                'recipientName' => $o['name'],
                'recipientAddress' => $o['address'],
                'recipientPostal' => $o['postal'] ?? '',
                'recipientCity' => $o['city'],
                'estimatedDelivery' => date('Y-m-d', strtotime('+21 days')),
                'status' => 'registered',
                'createdAt' => date('c')
            ];
            $shipments[] = $shipment;
            writeJson($DATA_FILE, $shipments);
            break;
        }
    }
    unset($o);
    if (!$found) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writeJson($ORDERS_FILE, $all);

    sendConfirmationEmail($found);

    echo json_encode(['success' => true, 'trackingNumber' => $found['trackingNumber']]);
    exit;
}

// ── GET orders (admin) ──
if ($method === 'GET' && $action === 'orders') {
    checkAuth();
    echo json_encode(readJson($ORDERS_FILE));
    exit;
}

// ── PUT update order (admin) ──
if ($method === 'PUT' && $action === 'orders') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['id'] ?? '';
    if (!$orderId) { http_response_code(400); echo json_encode(['error' => 'Missing order ID']); exit; }
    $all = readJson($ORDERS_FILE);
    $found = false;
    foreach ($all as &$o) {
        if ($o['id'] === $orderId) {
            foreach (['name','email','telegram','phone','address','postal','city','country','productName','price','status','promoCode'] as $field) {
                if (isset($input[$field])) $o[$field] = $input[$field];
            }
            $found = $o;
            break;
        }
    }
    unset($o);
    if (!$found) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writeJson($ORDERS_FILE, $all);
    echo json_encode($found);
    exit;
}

// ── DELETE order (admin) ──
if ($method === 'DELETE' && $action === 'orders') {
    checkAuth();
    $orderId = $_GET['id'] ?? '';
    if (!$orderId) { http_response_code(400); echo json_encode(['error' => 'Missing order ID']); exit; }
    $all = readJson($ORDERS_FILE);
    $newAll = array_filter($all, function($o) use ($orderId) { return $o['id'] !== $orderId; });
    if (count($newAll) === count($all)) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writeJson($ORDERS_FILE, $newAll);
    echo json_encode(['success' => true]);
    exit;
}

// ── POST create Crypto order ──
if ($method === 'POST' && $action === 'crypto-order') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name']) || empty($input['email']) || empty($input['address']) || empty($input['city']) || empty($input['postal'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Please fill in all required fields']);
        exit;
    }

    $orderId = 'IKA-' . strtoupper(substr(uniqid(), -8));
    $price = floatval($input['price'] ?? 0);

    $orders = readJson($ORDERS_FILE);
    $order = [
        'id' => $orderId,
        'trackingNumber' => '',
        'email' => $input['email'],
        'telegram' => $input['telegram'] ?? '',
        'name' => $input['name'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'],
        'postal' => $input['postal'],
        'city' => $input['city'],
        'country' => $input['country'] ?? 'FR',
        'productId' => $input['productId'] ?? '',
        'productName' => $input['productName'] ?? '',
        'products' => $input['products'] ?? [],
        'price' => $price,
        'subtotal' => floatval($input['subtotal'] ?? 0),
        'shipping' => floatval($input['shipping'] ?? 0),
        'vat' => floatval($input['vat'] ?? 0),
        'discount' => floatval($input['discount'] ?? 0),
        'promoDiscount' => floatval($input['promoDiscount'] ?? 0),
        'paypalDiscount' => 0,
        'cryptoDiscount' => floatval($input['cryptoDiscount'] ?? 0),
        'promoCode' => $input['promoCode'] ?? '',
        'paymentMethod' => 'crypto',
        'status' => 'pending',
        'createdAt' => date('c')
    ];
    $orders[] = $order;
    writeJson($ORDERS_FILE, $orders);

    incrementPromoUse($input['promoCode'] ?? '');
    try { sendOrderEmail($order); } catch (\Throwable $e) {}

    echo json_encode(['success' => true, 'orderId' => $orderId]);
    exit;
}

// ── GET crypto rates (public, cached 5min) ──
if ($method === 'GET' && $action === 'crypto-rates') {
    $cacheFile = __DIR__ . '/crypto_rates_cache.json';
    $cacheTime = 300;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $ch = curl_init('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,solana,litecoin&vs_currencies=eur');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['bitcoin'])) {
            $rates = [
                'btc' => floatval($data['bitcoin']['eur'] ?? 0),
                'eth' => floatval($data['ethereum']['eur'] ?? 0),
                'sol' => floatval($data['solana']['eur'] ?? 0),
                'ltc' => floatval($data['litecoin']['eur'] ?? 0),
                'updatedAt' => date('c')
            ];
            $json = json_encode($rates);
            file_put_contents($cacheFile, $json);
            echo $json;
            exit;
        }
    }

    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }

    http_response_code(503);
    echo json_encode(['error' => 'Unable to fetch crypto rates']);
    exit;
}

// ── GET check promo code (public) ──
if ($method === 'GET' && $action === 'check-promo') {
    $code = trim($_GET['code'] ?? '');
    if (!$code) { echo json_encode(['valid' => false, 'reason' => 'No code provided']); exit; }
    echo json_encode(validatePromo($code));
    exit;
}

// ── GET all promos (admin) ──
if ($method === 'GET' && $action === 'promos') {
    checkAuth();
    echo json_encode(getPromos());
    exit;
}

// ── POST create promo (admin) ──
if ($method === 'POST' && $action === 'promos') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));
    if (!$code || !isset($input['value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Code and value are required']);
        exit;
    }
    if (getPromo($code)) {
        http_response_code(409);
        echo json_encode(['error' => 'Code already exists']);
        exit;
    }
    $promos = getPromos();
    $promo = [
        'code' => $code,
        'type' => $input['type'] ?? 'percent',
        'value' => intval($input['value']),
        'maxUses' => intval($input['maxUses'] ?? 0),
        'usedCount' => 0,
        'expiresAt' => $input['expiresAt'] ?? '',
        'createdAt' => date('c')
    ];
    $promos[] = $promo;
    writePromos($promos);
    echo json_encode($promo);
    exit;
}

// ── DELETE promo (admin) ──
if ($method === 'DELETE' && $action === 'promos') {
    checkAuth();
    $code = $_GET['code'] ?? '';
    if (!$code) { http_response_code(400); echo json_encode(['error' => 'Missing code']); exit; }
    $promos = getPromos();
    $newPromos = array_values(array_filter($promos, function($p) use ($code) { return $p['code'] !== $code; }));
    if (count($newPromos) === count($promos)) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    writePromos($newPromos);
    echo json_encode(['success' => true]);
    exit;
}

// ── GET IP tracking logs (admin) ──
if ($method === 'GET' && $action === 'ip-logs') {
    checkAuth();
    echo json_encode(readJson($IP_LOGS_FILE));
    exit;
}

// ── DELETE clear IP logs (admin) ──
if ($method === 'DELETE' && $action === 'ip-logs') {
    checkAuth();
    file_put_contents($IP_LOGS_FILE, '[]');
    echo json_encode(['success' => true]);
    exit;
}

// ── GET blocked IPs (admin) ──
if ($method === 'GET' && $action === 'blocked-ips') {
    checkAuth();
    echo json_encode(readJson($BLOCKED_IPS_FILE));
    exit;
}

// ── POST block an IP (admin) ──
if ($method === 'POST' && $action === 'blocked-ips') {
    checkAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $ip = trim($input['ip'] ?? '');
    $reason = trim($input['reason'] ?? '');
    if (!$ip) { http_response_code(400); echo json_encode(['error' => 'Missing IP']); exit; }
    $blocked = readJson($BLOCKED_IPS_FILE);
    foreach ($blocked as $b) {
        if ($b['ip'] === $ip) {
            http_response_code(409);
            echo json_encode(['error' => 'IP already blocked']);
            exit;
        }
    }
    $blocked[] = ['ip' => $ip, 'reason' => $reason, 'blockedAt' => date('c')];
    file_put_contents($BLOCKED_IPS_FILE, json_encode($blocked, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE unblock an IP (admin) ──
if ($method === 'DELETE' && $action === 'blocked-ips') {
    checkAuth();
    $ip = $_GET['ip'] ?? '';
    if (!$ip) { http_response_code(400); echo json_encode(['error' => 'Missing IP']); exit; }
    $blocked = readJson($BLOCKED_IPS_FILE);
    $newBlocked = array_values(array_filter($blocked, function($b) use ($ip) { return $b['ip'] !== $ip; }));
    if (count($newBlocked) === count($blocked)) { http_response_code(404); echo json_encode(['error' => 'IP not found']); exit; }
    file_put_contents($BLOCKED_IPS_FILE, json_encode($newBlocked, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
}

// ── GET cart by IP ──
if ($method === 'GET' && $action === 'get-cart') {
    $ip = getClientIp();
    $cartsFile = __DIR__ . '/carts.json';
    $carts = readJson($cartsFile);
    $myCart = [];
    foreach ($carts as $c) {
        if ($c['ip'] === $ip) { $myCart = $c['items'] ?? []; break; }
    }
    echo json_encode(['items' => $myCart]);
    exit;
}

// ── POST save cart by IP ──
if ($method === 'POST' && $action === 'save-cart') {
    $ip = getClientIp();
    $body = json_decode(file_get_contents('php://input'), true);
    $items = $body['items'] ?? [];
    $cartsFile = __DIR__ . '/carts.json';
    $carts = readJson($cartsFile);
    $found = false;
    foreach ($carts as &$c) {
        if ($c['ip'] === $ip) { $c['items'] = $items; $c['updated'] = date('c'); $found = true; break; }
    }
    unset($c);
    if (!$found) $carts[] = ['ip' => $ip, 'items' => $items, 'updated' => date('c')];
    if (count($carts) > 2000) $carts = array_slice($carts, -2000);
    file_put_contents($cartsFile, json_encode($carts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown endpoint']);
