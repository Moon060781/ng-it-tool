<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (!voIsAuthenticated()) {
    $redirect = rawurlencode($_SERVER['REQUEST_URI'] ?? '/VO/');
    header('Location: login.php?redirect=' . $redirect, true, 302);
    exit;
}

require __DIR__ . '/studio.html';
