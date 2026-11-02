<?php
// Liste des médecins (choix du praticien lors de la prise de rendez-vous)
require_once 'config.php';
exiger_connexion_ajax();

$stmt = $pdo->query("SELECT id, prenom, nom, specialite FROM utilisateurs WHERE role = 'medecin' AND est_verifie = 1 ORDER BY nom, prenom");
repondre_json($stmt->fetchAll());
