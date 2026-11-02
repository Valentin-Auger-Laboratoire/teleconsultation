<?php
// Fin de consultation (médecin) : le chat est effacé et le rendez-vous passe à "Terminé".
// La facture et son statut de paiement sont conservés.
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');
$rdv = exiger_rdv_ajax($pdo, $_POST['id'] ?? null, $user);

$pdo->prepare("DELETE FROM messages WHERE id_consultation = ?")->execute([$rdv['id']]);
$pdo->prepare("UPDATE rendez_vous SET statut = 'Terminé' WHERE id = ?")->execute([$rdv['id']]);
repondre_json(['ok' => true]);
