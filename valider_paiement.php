<?php
// Paiement simulé : aucune donnée bancaire n'est demandée ni transmise
require_once 'config.php';
$user = exiger_connexion_page('patient');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paiement.php');
    exit;
}
$rdv = charger_rdv_autorise($pdo, $_POST['id'] ?? null, $user);
if (!$rdv || $rdv['montant'] <= 0) {
    alerte_et_redirection('Aucune facture à régler pour cette consultation.', 'paiement.php');
}

$pdo->prepare("UPDATE rendez_vous SET statut_paiement = 1 WHERE id = ?")->execute([$rdv['id']]);

// Retour à la consultation si elle est en cours, sinon à la liste des factures
$retour = $rdv['statut'] === 'Terminé' ? 'paiement.php?paiement=success' : 'consultation.php?id=' . $rdv['id'] . '&paiement=success';
header('Location: ' . $retour);
exit;
