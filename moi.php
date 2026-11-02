<?php
// Informations de l'utilisateur connecté (affichage du nom dans les tableaux de bord)
require_once 'config.php';
$user = exiger_connexion_ajax();

repondre_json([
    'id'     => $user['id'],
    'role'   => $user['role'],
    'prenom' => $_SESSION['user_prenom'],
    'nom'    => $_SESSION['user_nom'],
]);
