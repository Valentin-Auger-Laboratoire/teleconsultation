<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    connecter_utilisateur($pdo, $config, 'patient', 'index.html', 'dashboard.html');
}
header('Location: index.html');
