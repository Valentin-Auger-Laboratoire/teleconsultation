<?php
// Montant et statut de paiement d'une consultation
require_once 'config.php';
$user = exiger_connexion_ajax();
$rdv = exiger_rdv_ajax($pdo, $_GET['id'] ?? null, $user);

repondre_json([
    'montant'         => (float) $rdv['montant'],
    'statut_paiement' => (int) $rdv['statut_paiement'],
    'statut'          => $rdv['statut'],
]);
