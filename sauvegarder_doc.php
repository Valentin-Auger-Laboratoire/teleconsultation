<?php
// Ordonnance rédigée par le médecin : enregistrée pour le patient DE CETTE consultation, puis envoyée par mail
require_once 'config.php';
require_once 'envoyer_mail.php';
exiger_post();
$user = exiger_connexion_ajax('medecin');
$rdv = exiger_rdv_ajax($pdo, $_POST['id_rdv'] ?? null, $user);

$contenu = trim($_POST['contenu'] ?? '');
if ($contenu === '') {
    repondre_json(['erreur' => 'contenu_vide'], 400);
}

$stmt = $pdo->prepare("INSERT INTO documents (id_patient, id_medecin, id_rdv, depose_par, type_doc, contenu) VALUES (?, ?, ?, 'medecin', 'Ordonnance', ?)");
$stmt->execute([$rdv['id_patient'], $user['id'], $rdv['id'], mb_substr($contenu, 0, 5000)]);

$nomMedecin = 'Dr. ' . $rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom'];
$mailEnvoye = envoyerOrdonnanceMail($config, $rdv['patient_email'], $nomMedecin, $contenu);

repondre_json([
    'ok'          => true,
    'patient'     => $rdv['patient_prenom'] . ' ' . $rdv['patient_nom'],
    'mail_envoye' => $mailEnvoye,
]);
