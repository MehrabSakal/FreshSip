<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$_SESSION = [];
session_destroy();

session_start();
flash('info', 'You have been logged out.');
redirect('features/auth/login.php');
