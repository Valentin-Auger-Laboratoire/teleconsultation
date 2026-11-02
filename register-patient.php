<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    inscrire_utilisateur($pdo, $config, 'patient', 'inscription.html');
}
header('Location: inscription.html');
