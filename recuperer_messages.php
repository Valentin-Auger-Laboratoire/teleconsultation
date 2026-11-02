<?php
// Messages d'une consultation, uniquement pour son patient ou son médecin.
// Renvoie du JSON : l'affichage est construit côté JS avec textContent.
require_once 'config.php';
$user = exiger_connexion_ajax();
$rdv = exiger_rdv_ajax($pdo, $_GET['id'] ?? null, $user);

$stmt = $pdo->prepare("SELECT contenu, id_expediteur, DATE_FORMAT(date_envoi, '%H:%i') AS heure
                       FROM messages WHERE id_consultation = ? ORDER BY date_envoi ASC, id ASC");
$stmt->execute([$rdv['id']]);

repondre_json(array_map(fn($m) => [
    'contenu' => $m['contenu'],
    'heure'   => $m['heure'],
    'moi'     => (int) $m['id_expediteur'] === $user['id'],
], $stmt->fetchAll()));
