<?php
require 'config.php';
if (empty($_SESSION['pending_user'])) { header('Location: login.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['resend'])) {
        $o = $_SESSION['otp'] ?? null;
        if ($o && time() - $o['issued'] < OTP_RESEND_AFTER) $error = 'Please wait a few seconds before resending.';
        else { send_otp($_SESSION['pending_user'], issue_otp()); header('Location: verify.php'); exit; }
    } else {
        $code = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        if (strlen($code) !== 6) $error = 'Enter the 6-digit code.';
        else {
            [$ok, $msg] = check_otp($code);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user'] = $_SESSION['pending_user'];
                unset($_SESSION['pending_user']);
                header('Location: dashboard.php'); exit;
            }
            $error = $msg;
        }
    }
}
$remaining = isset($_SESSION['otp']) ? max(0, $_SESSION['otp']['expires'] - time()) : 0;
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify OTP</title><link rel="stylesheet" href="style.css"></head><body>
<form class="card" method="post">
  <h1>Enter verification code</h1>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <?php if (DEV_SHOW_OTP && !empty($_SESSION['dev_otp'])): ?>
    <div class="note">Demo mode – your OTP is <b><?= e($_SESSION['dev_otp']) ?></b></div>
  <?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label for="otp">6-digit code</label>
  <input id="otp" name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" autofocus>
  <div class="timer">Expires in <span id="t"><?= $remaining ?></span>s</div>
  <button id="verify" type="submit">Verify</button>
  <button class="alt" type="submit" name="resend" value="1" formnovalidate>Resend code</button>
</form>
<script>
let s = <?= (int)$remaining ?>;
const t = document.getElementById('t'), v = document.getElementById('verify');
const tick = () => { t.textContent = s; if (s <= 0) { v.disabled = true; t.parentElement.textContent = 'Code expired – request a new one.'; clearInterval(i); } s--; };
const i = setInterval(tick, 1000); tick();
</script></body></html>
