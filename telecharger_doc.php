<?php
// Téléchargement d'un document envoyé : réservé au patient concerné et au médecin destinataire
require_once 'config.php';
$user = exiger_connexion_ajax();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$colonne = $user['role'] === 'medecin' ? 'id_medecin' : 'id_patient';
$stmt = $pdo->prepare("SELECT nom_fichier, nom_original, type_mime FROM documents WHERE id = ? AND $colonne = ? AND nom_fichier IS NOT NULL");
$stmt->execute([$id ?: 0, $user['id']]);
$doc = $stmt->fetch();

$chemin = $doc ? __DIR__ . '/uploads/' . basename($doc['nom_fichier']) : null;
if (!$chemin || !is_file($chemin)) {
    repondre_json(['erreur' => 'document_introuvable'], 404);
}

$nomTelechargement = preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['nom_original']) ?: 'document';
header('Content-Type: ' . $doc['type_mime']);
header('Content-Length: ' . filesize($chemin));
header('Content-Disposition: attachment; filename="' . $nomTelechargement . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($chemin);
