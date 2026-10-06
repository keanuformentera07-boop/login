<?php
require 'config.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$u = $_SESSION['user'];
?>
<!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title><link rel="stylesheet" href="style.css"></head><body>
<div class="card"><h1>Welcome, <?= e((string)($u['name'] ?? $u['username'] ?? $u['email'] ?? 'user')) ?></h1>
<p>You are signed in with multi-factor authentication.</p>
<a href="logout.php">Log out</a></div></body></html>
