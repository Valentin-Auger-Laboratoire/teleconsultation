<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('patient');

$idCreneau = filter_var($_POST['id_creneau'] ?? null, FILTER_VALIDATE_INT);
$motif = trim($_POST['motif'] ?? '');
$motif = $motif === '' ? 'Non précisé' : mb_substr($motif, 0, 500);
if (!$idCreneau) {
    repondre_json(['erreur' => 'creneau_invalide'], 400);
}

// Transaction : le créneau est verrouillé puis passé à indisponible, il ne peut pas être réservé deux fois
$pdo->beginTransaction();
$stmt = $pdo->prepare("SELECT id, id_medecin, date_heure FROM creneaux WHERE id = ? AND disponible = 1 AND date_heure > NOW() FOR UPDATE");
$stmt->execute([$idCreneau]);
$creneau = $stmt->fetch();
if (!$creneau) {
    $pdo->rollBack();
    repondre_json(['erreur' => 'creneau_indisponible'], 409);
}

$pdo->prepare("UPDATE creneaux SET disponible = 0 WHERE id = ?")->execute([$creneau['id']]);
// cle_visio : partie aléatoire des identifiants PeerJS de cette consultation
$stmt = $pdo->prepare("INSERT INTO rendez_vous (id_patient, id_medecin, id_creneau, date_rdv, motif, statut, cle_visio)
                       VALUES (?, ?, ?, ?, ?, 'En attente', ?)");
$stmt->execute([$user['id'], $creneau['id_medecin'], $creneau['id'], $creneau['date_heure'], $motif, bin2hex(random_bytes(16))]);
$idRdv = (int) $pdo->lastInsertId();
$pdo->commit();

repondre_json(['ok' => true, 'id' => $idRdv]);
