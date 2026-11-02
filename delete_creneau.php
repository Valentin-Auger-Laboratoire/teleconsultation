<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    repondre_json(['erreur' => 'id_invalide'], 400);
}

// Un médecin ne supprime que ses propres créneaux encore libres
$stmt = $pdo->prepare("DELETE FROM creneaux WHERE id = ? AND id_medecin = ? AND disponible = 1");
$stmt->execute([$id, $user['id']]);
repondre_json(['ok' => $stmt->rowCount() === 1]);
