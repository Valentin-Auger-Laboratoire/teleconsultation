<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    inscrire_utilisateur($pdo, $config, 'medecin', 'register-medecin.html');
}
header('Location: register-medecin.html');
