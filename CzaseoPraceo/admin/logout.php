<?php
/** Wylogowanie (T-1.3). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Auth::logout();
redirect('login.php');
