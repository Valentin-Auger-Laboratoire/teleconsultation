<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');
$rdv = exiger_rdv_ajax($pdo, $_POST['id'] ?? null, $user);

$pdo->prepare("UPDATE rendez_vous SET statut = 'Confirmé' WHERE id = ? AND statut = 'En attente'")->execute([$rdv['id']]);
repondre_json(['ok' => true]);
