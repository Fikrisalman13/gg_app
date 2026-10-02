<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load helper yang mendefinisikan getUserGroup
require_once __DIR__ . '/includes/access_helper.php';

$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'user';
$groupId = getUserGroup($username); // sekarang aman
