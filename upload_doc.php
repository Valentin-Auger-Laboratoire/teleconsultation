<?php
// Envoi d'un document par le patient : PDF, JPG ou PNG uniquement, 5 Mo maximum, nom de fichier aléatoire
require_once 'config.php';
exiger_post();
$user = exiger_connexion_ajax('patient');

const TAILLE_MAX = 5 * 1024 * 1024;
const TYPES_AUTORISES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
const LIBELLES_AUTORISES = ['Ordonnance', 'Bilan Sanguin', 'Autre fichier'];

$fichier = $_FILES['fichier'] ?? null;
if (!$fichier || $fichier['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'])) {
    repondre_json(['erreur' => 'fichier_manquant'], 400);
}
if ($fichier['size'] > TAILLE_MAX) {
    repondre_json(['erreur' => 'fichier_trop_lourd'], 400);
}

// Type réel lu dans le contenu du fichier (pas dans le nom ni dans l'en-tête envoyé par le navigateur)
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
$extensionOrigine = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
if (!isset(TYPES_AUTORISES[$mime]) || !in_array($extensionOrigine, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    repondre_json(['erreur' => 'type_non_autorise'], 400);
}

$typeDoc = in_array($_POST['type'] ?? '', LIBELLES_AUTORISES, true) ? $_POST['type'] : 'Autre fichier';

// Document rattaché à une consultation (envoyé pendant la visio) ou simplement déposé dans l'espace patient
$idRdv = null;
$idMedecin = null;
if (!empty($_POST['id_rdv'])) {
    $rdv = exiger_rdv_ajax($pdo, $_POST['id_rdv'], $user);
    $idRdv = $rdv['id'];
    $idMedecin = $rdv['id_medecin'];
}

$nomStockage = bin2hex(random_bytes(16)) . '.' . TYPES_AUTORISES[$mime];
if (!move_uploaded_file($fichier['tmp_name'], __DIR__ . '/uploads/' . $nomStockage)) {
    repondre_json(['erreur' => 'enregistrement_impossible'], 500);
}

$nomOriginal = mb_substr(basename($fichier['name']), 0, 255);
$stmt = $pdo->prepare("INSERT INTO documents (id_patient, id_medecin, id_rdv, depose_par, type_doc, nom_fichier, nom_original, type_mime)
                       VALUES (?, ?, ?, 'patient', ?, ?, ?, ?)");
$stmt->execute([$user['id'], $idMedecin, $idRdv, $typeDoc, $nomStockage, $nomOriginal, $mime]);

repondre_json(['ok' => true]);
