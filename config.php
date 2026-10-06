<?php
// Shared config + helpers
session_start();

const API_USERS_URL = 'https://event-driven-user-api.onrender.com/users'; // tracking params (?fbclid=...) removed
const API_TIMEOUT   = 40;   // Render free tier can take a while to wake up
const OTP_TTL       = 30;   // seconds
const OTP_MAX_TRIES = 5;
const OTP_RESEND_AFTER = 10; // seconds before "resend" is allowed
const DEV_SHOW_OTP  = true; // DEMO ONLY: shows OTP on screen. Set false and implement send_otp() for real delivery.

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

/** Fetch all users from the API. Returns [array|null, error|null] */
function fetch_users(): array {
    $ch = curl_init(API_USERS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) return [null, "API connection failed: $err"];
    if ($code < 200 || $code >= 300) return [null, "API returned HTTP $code"];
    $data = json_decode($body, true);
    if (!is_array($data)) return [null, 'API returned invalid JSON'];
    // Some APIs wrap the list: {"users":[...]} or {"data":[...]}
    foreach (['users', 'data'] as $k) if (isset($data[$k]) && is_array($data[$k])) $data = $data[$k];
    return [$data, null];
}

/** Get a field from a user array, ignoring key case (API uses "Email", "Password", ...). */
function uget(array $u, string $key): ?string {
    foreach ($u as $k => $v) if (strcasecmp((string)$k, $key) === 0) return is_scalar($v) ? (string)$v : null;
    return null;
}

/** Find a user by email or username (case-insensitive). */
function find_user(array $users, string $login): ?array {
    foreach ($users as $u) {
        if (!is_array($u)) continue;
        foreach (['email', 'username'] as $f) {
            $val = uget($u, $f);
            if ($val !== null && strcasecmp($val, $login) === 0) return $u;
        }
    }
    return null;
}

/** Works with bcrypt/argon hashes or plain-text passwords stored by the API. */
function password_ok(string $input, string $stored): bool {
    if (preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $stored)) return password_verify($input, $stored);
    return hash_equals($stored, $input);
}

// ---- OTP ----
function issue_otp(): string {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = [
        'hash'    => password_hash($otp, PASSWORD_DEFAULT),
        'expires' => time() + OTP_TTL,
        'tries'   => 0,
        'issued'  => time(),
    ];
    return $otp;
}
function send_otp(array $user, string $otp): void {
    // TODO: real delivery (email via PHPMailer/mail(), or SMS provider) using $user['email'] / $user['phone'].
    if (DEV_SHOW_OTP) $_SESSION['dev_otp'] = $otp;
}
/** Returns [ok, message] */
function check_otp(string $input): array {
    $o = $_SESSION['otp'] ?? null;
    if (!$o) return [false, 'No OTP pending. Please log in again.'];
    if (time() > $o['expires']) { unset($_SESSION['otp']); return [false, 'OTP expired. Request a new one.']; }
    if ($o['tries'] >= OTP_MAX_TRIES) { unset($_SESSION['otp']); return [false, 'Too many attempts. Request a new OTP.']; }
    $_SESSION['otp']['tries']++;
    if (!password_verify($input, $o['hash'])) return [false, 'Incorrect OTP.'];
    unset($_SESSION['otp'], $_SESSION['dev_otp']); // single use
    return [true, ''];
}
