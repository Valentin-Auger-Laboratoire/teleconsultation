<?php
// Rendez-vous du patient connecté
require_once 'config.php';
$user = exiger_connexion_ajax('patient');

$stmt = $pdo->prepare("SELECT r.id, CONCAT('Dr. ', m.prenom, ' ', m.nom) AS medecin, m.specialite,
                              DATE_FORMAT(r.date_rdv, '%d/%m/%Y %H:%i') AS date_rdv, r.statut, r.montant, r.statut_paiement
                       FROM rendez_vous r
                       JOIN utilisateurs m ON m.id = r.id_medecin
                       WHERE r.id_patient = ?
                       ORDER BY r.date_rdv ASC");
$stmt->execute([$user['id']]);
repondre_json($stmt->fetchAll());
