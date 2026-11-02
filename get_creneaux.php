<?php
// Créneaux libres à venir : ceux du médecin choisi (patient) ou ses propres créneaux (médecin)
require_once 'config.php';
$user = exiger_connexion_ajax();

$idMedecin = $user['role'] === 'medecin' ? $user['id'] : filter_var($_GET['id_medecin'] ?? null, FILTER_VALIDATE_INT);
if (!$idMedecin) {
    repondre_json([]);
}

$stmt = $pdo->prepare("SELECT id, DATE_FORMAT(date_heure, '%d/%m/%Y %H:%i') AS libelle
                       FROM creneaux
                       WHERE id_medecin = ? AND disponible = 1 AND date_heure > NOW()
                       ORDER BY date_heure ASC");
$stmt->execute([$idMedecin]);
repondre_json($stmt->fetchAll());
