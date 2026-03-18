<?php
// ============================================================
// includes/helpers.php — Logging, formatting, and utilities
// ============================================================

// ── Action Logger ─────────────────────────────────────────────
function logAction(string $action, string $module, ?float $recordId = null, string $note = ''): void {
    try {
        dbInsert('action_logs', [
            'user_id'   => $_SESSION['user_id'] ?? 0,
            'action'    => $action,
            'module'    => $module,
            'record_id' => $recordId,
            'note'      => $note,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at'=> date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // fail silently — logging must never break the app
    }
}

// ── Settings helper ───────────────────────────────────────────
function getSetting(string $key, mixed $default = ''): mixed {
    static $cache = null;
    if ($cache === null) {
        $rows  = dbFetchAll('SELECT `key`, `value` FROM settings');
        $cache = [];
        foreach ($rows as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache[$key] ?? $default;
}

function getAllSettings(): array {
    static $all = null;
    if ($all !== null) return $all;
    $rows = dbFetchAll('SELECT `key`, `value` FROM settings');
    $all  = [];
    foreach ($rows as $r) $all[$r['key']] = $r['value'];
    $all += [
        'shop_name'=>APP_NAME,'shop_address'=>'','shop_phone'=>'','shop_email'=>'',
        'shop_logo_url'=>'','shop_tax_no'=>'','invoice_footer'=>'Thank you!',
        'discount_enabled'=>'1','discount_type'=>'both','discount_max_percent'=>'100',
        'discount_max_amount'=>'0','discount_default'=>'0','product_discount_enabled'=>'0',
        'vat_enabled'=>'1','vat_default'=>'15','vat_inclusive'=>'0',
        'points_enabled'=>'1','points_earn_rate'=>'1','points_redeem_rate'=>'0.01',
        'points_min_redeem'=>'0','points_max_redeem_pct'=>'100',
        'currency_symbol'=>'৳', 'api_key_sms' => '', 'sms_enabled' => '0', 'sms_balance' => '0',
    ];
    return $all;
}

// ── Currency / Number Formatting ──────────────────────────────
function money(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function pct(float $rate): string {
    return round($rate * 100, 2) . '%';
}

// ── Sanitise output ───────────────────────────────────────────
function e(mixed $val): string {
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Unique ID generator ───────────────────────────────────────
function generateProductId(): string {
    return 'PRD-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

function generateInvoiceNo(): string {
    $last = dbFetch('SELECT MAX(id) as max_id FROM sales');
    $next = ($last['max_id'] ?? 0) + 1;
    return 'INV-' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

// ── QR Code URL (Google Charts free API) ─────────────────────
function qrUrl(string $data, int $size = 150): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
         . '&data=' . urlencode($data);
}

// ── Barcode (uses a pure-CSS/JS inline barcode) ──────────────
function barcodeValue(string $productId): string {
    return preg_replace('/[^A-Z0-9\-]/', '', strtoupper($productId));
}

// ── Redirect helper ───────────────────────────────────────────
function redirect(string $page, array $params = []): never {
    $url = BASE_URL . '/index.php?page=' . $page;
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    header('Location: ' . $url);
    exit;
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ── Date helpers ──────────────────────────────────────────────
function today(): string    { return date('Y-m-d'); }
function now(): string      { return date('Y-m-d H:i:s'); }
function fmtDate(string $d): string { return date('d M Y', strtotime($d)); }
function fmtDateTime(string $d): string { return date('d M Y H:i', strtotime($d)); }

// ── Pagination ────────────────────────────────────────────────
function paginate(string $sql, array $params, int $page, int $perPage = 20): array {
    $total = dbFetch("SELECT COUNT(*) as c FROM ($sql) t", $params)['c'] ?? 0;
    $offset = ($page - 1) * $perPage;
    $rows  = dbFetchAll("$sql LIMIT $perPage OFFSET $offset", $params);
    return [
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'last_page'  => max(1, (int) ceil($total / $perPage)),
    ];
}
 function sendHttpPost(string $url, array $data): string {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
                return $response;
            }