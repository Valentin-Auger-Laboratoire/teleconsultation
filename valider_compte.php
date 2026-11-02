<?php
require_once 'config.php';

$email = $_SESSION['verif_email'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $email === '') {
    header('Location: index.html');
    exit;
}

$stmt = $pdo->prepare("SELECT id, role, code_verification, code_expiration > NOW() AS code_valide, tentatives_code
                       FROM utilisateurs WHERE email = ? AND est_verifie = 0");
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    unset($_SESSION['verif_email'], $_SESSION['code_demo']);
    alerte_et_redirection('Compte introuvable ou déjà activé.', 'index.html');
}

// Demande d'un nouveau code
if (($_POST['action'] ?? '') === 'renvoyer') {
    envoyer_code_verification($pdo, $config, (int) $user['id'], $email);
    alerte_et_redirection('Un nouveau code vous a été envoyé.', 'verification.php');
}

$codeTape = trim($_POST['code'] ?? '');

if (!$user['code_verification'] || !$user['code_valide'] || $user['tentatives_code'] >= TENTATIVES_CODE_MAX) {
    alerte_et_redirection('Code expiré : demandez un nouveau code.', 'verification.php');
}

if (!hash_equals($user['code_verification'], $codeTape)) {
    $pdo->prepare("UPDATE utilisateurs SET tentatives_code = tentatives_code + 1 WHERE id = ?")->execute([$user['id']]);
    alerte_et_redirection('Code incorrect, veuillez réessayer.', 'verification.php');
}

// Code correct : activation du compte
$pdo->prepare("UPDATE utilisateurs SET est_verifie = 1, code_verification = NULL, code_expiration = NULL, tentatives_code = 0 WHERE id = ?")
    ->execute([$user['id']]);
unset($_SESSION['verif_email'], $_SESSION['code_demo']);

header('Location: ' . ($user['role'] === 'medecin' ? 'index-medecin.html' : 'index.html') . '?success=compte_active');
exit;
