<?php
require __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::logout();
}
header('Location: /admin/login.php');
