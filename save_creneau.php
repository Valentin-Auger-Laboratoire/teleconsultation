<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');

$date = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['date_heure'] ?? '');
if (!$date || $date <= new DateTime()) {
    repondre_json(['erreur' => 'date_invalide'], 400);
}

// Le créneau est toujours rattaché au médecin connecté
$stmt = $pdo->prepare("INSERT IGNORE INTO creneaux (id_medecin, date_heure, disponible) VALUES (?, ?, 1)");
$stmt->execute([$user['id'], $date->format('Y-m-d H:i:00')]);
repondre_json(['ok' => true]);
