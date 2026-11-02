<?php
// Copiez ce fichier en config.local.php puis renseignez vos propres valeurs.
// config.local.php est exclu du dépôt par .gitignore : ne le publiez jamais.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'santeconnect',
        'user' => 'root',
        'pass' => '',
    ],
    'smtp' => [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'user'      => 'ton.email@exemple.com',
        'pass'      => 'MOT_DE_PASSE_APPLICATION',
        'from'      => 'ton.email@exemple.com',
        'from_name' => 'SantéConnect',
    ],
    // true = si l'envoi du mail échoue, le code de vérification s'affiche sur la page de vérification.
    // À n'utiliser qu'en local, pour une démonstration sans serveur SMTP.
    'mode_demo' => false,
];
