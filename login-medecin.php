<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    connecter_utilisateur($pdo, $config, 'medecin', 'index-medecin.html', 'dashboard-medecin.html');
}
header('Location: index-medecin.html');
