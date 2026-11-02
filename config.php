<?php
// Configuration centrale : session, connexion PDO partagée et fonctions communes.
// Les vraies valeurs (base de données, SMTP) sont lues dans config.local.php (non versionné).

$fichierLocal = __DIR__ . '/config.local.php';
if (!is_file($fichierLocal)) {
    http_response_code(500);
    die('Configuration manquante : copiez config.example.php en config.local.php et renseignez vos valeurs.');
}
$config = require $fichierLocal;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Connexion BDD : ' . $e->getMessage());
    http_response_code(500);
    die('Erreur de connexion à la base de données.');
}

require_once __DIR__ . '/fonctions.php';
