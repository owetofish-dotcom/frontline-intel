<?php
/** Logowanie do panelu (T-1.1, T-1.2). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

if (Auth::check()) {
    redirect('index.php');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $email = (string) ($_POST['email'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');
    if (Auth::attempt($email, $pass)) {
        redirect('index.php');
    }
    // Komunikat ogólny — bez zdradzania, czy e-mail istnieje (anty-enumeracja).
    $error = 'Nieprawidłowy e-mail lub hasło.';
}
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie — CzaseoPraceo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="brand"><span class="brand-mark" aria-hidden="true">⏱</span>CzaseoPraceo</div>
    <h1>Zaloguj się do panelu</h1>
    <?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="username"
               value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">Hasło</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn" style="width:100%">Zaloguj się</button>
    </form>
  </div>
</div>
</body>
</html>
