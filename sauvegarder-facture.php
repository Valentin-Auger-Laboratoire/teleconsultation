<?php
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');
$rdv = exiger_rdv_ajax($pdo, $_POST['id'] ?? null, $user);

$montant = filter_var($_POST['montant'] ?? null, FILTER_VALIDATE_FLOAT);
if ($montant === false || $montant <= 0 || $montant > 1000) {
    repondre_json(['erreur' => 'montant_invalide'], 400);
}
if ((int) $rdv['statut_paiement'] === 1) {
    repondre_json(['erreur' => 'deja_payee'], 409);
}

$pdo->prepare("UPDATE rendez_vous SET montant = ?, statut_paiement = 0 WHERE id = ?")->execute([round($montant, 2), $rdv['id']]);
repondre_json(['ok' => true]);
