<?php
require_once 'config.php';

$role = $_SESSION['user_role'] ?? 'patient';
$_SESSION = [];
session_destroy();
header('Location: ' . ($role === 'medecin' ? 'index-medecin.html' : 'index.html'));
