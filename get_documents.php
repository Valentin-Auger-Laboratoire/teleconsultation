<?php
// Patient : tous ses documents. Médecin : les documents d'une de ses consultations (?id_rdv=)
require_once 'config.php';
$user = exiger_connexion_ajax();

$colonnes = "d.id, d.type_doc, d.contenu, d.nom_original, d.depose_par, (d.nom_fichier IS NOT NULL) AS a_fichier,
             DATE_FORMAT(d.date_creation, '%d/%m/%Y %H:%i') AS date_creation";

if ($user['role'] === 'patient') {
    $stmt = $pdo->prepare("SELECT $colonnes FROM documents d WHERE d.id_patient = ? ORDER BY d.date_creation DESC");
    $stmt->execute([$user['id']]);
} else {
    $rdv = exiger_rdv_ajax($pdo, $_GET['id_rdv'] ?? null, $user);
    $stmt = $pdo->prepare("SELECT $colonnes FROM documents d WHERE d.id_rdv = ? ORDER BY d.date_creation DESC");
    $stmt->execute([$rdv['id']]);
}

repondre_json(array_map(function ($d) {
    $d['a_fichier'] = (bool) $d['a_fichier'];
    return $d;
}, $stmt->fetchAll()));
