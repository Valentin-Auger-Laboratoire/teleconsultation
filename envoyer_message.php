<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax();
$rdv = exiger_rdv_ajax($pdo, $_POST['id_consultation'] ?? null, $user);

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    repondre_json(['erreur' => 'message_vide'], 400);
}

$stmt = $pdo->prepare("INSERT INTO messages (id_consultation, id_expediteur, contenu, date_envoi) VALUES (?, ?, ?, NOW())");
$stmt->execute([$rdv['id'], $user['id'], mb_substr($message, 0, 2000)]);
repondre_json(['ok' => true]);
