<?php
// Rendez-vous en cours du médecin connecté
require_once 'config.php';
$user = exiger_connexion_ajax('medecin');

$stmt = $pdo->prepare("SELECT r.id, CONCAT(p.prenom, ' ', p.nom) AS patient, r.motif,
                              DATE_FORMAT(r.date_rdv, '%d/%m/%Y %H:%i') AS date_rdv, r.statut, r.montant, r.statut_paiement
                       FROM rendez_vous r
                       JOIN utilisateurs p ON p.id = r.id_patient
                       WHERE r.id_medecin = ? AND r.statut <> 'Terminé'
                       ORDER BY r.date_rdv ASC");
$stmt->execute([$user['id']]);
repondre_json($stmt->fetchAll());
