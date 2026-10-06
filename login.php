<?php
require 'config.php';
$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!csrf_check())                         $error = 'Invalid request. Refresh and try again.';
    elseif ($login === '' || $pass === '')     $error = 'Email/username and password are required.';
    elseif (mb_strlen($login) > 254 || mb_strlen($pass) > 128) $error = 'Input too long.';
    elseif (str_contains($login, '@') && !filter_var($login, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email format.';
    elseif (strlen($pass) < 6)                 $error = 'Password must be at least 6 characters.';
    else {
        [$users, $apiErr] = fetch_users();
        if ($apiErr) {
            $error = $apiErr;
        } else {
            $user = find_user($users, $login);
            // Same message for unknown user / wrong password (no account enumeration)
            if (!$user || !isset($user['password']) || !password_ok($pass, (string)$user['password'])) {
                $error = 'Invalid credentials.';
            } else {
                session_regenerate_id(true);
                unset($user['password']);
                $_SESSION['pending_user'] = $user;   // password verified, but NOT logged in until OTP passes
                send_otp($user, issue_otp());
                header('Location: verify.php'); exit;
            }
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title><link rel="stylesheet" href="style.css"></head><body>
<form class="card" method="post" novalidate>
  <h1>Sign in</h1>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label for="login">Email or username</label>
  <input id="login" name="login" value="<?= e($login) ?>" autocomplete="username" required>
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Continue</button>
</form></body></html>
