<?php
// Fonctions communes : échappement, réponses JSON, contrôle d'accès, comptes.

const DUREE_CODE_MINUTES = 15;
const TENTATIVES_CODE_MAX = 5;

// Échappement HTML de toute donnée affichée
function e($valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

function repondre_json($donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($donnees);
    exit;
}

// Affiche une alerte puis redirige (messages encodés en JSON pour éviter toute injection dans le script)
function alerte_et_redirection(string $message, string $url): never
{
    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
    echo '<script>alert(' . json_encode($message, $flags) . '); window.location.href = ' . json_encode($url, $flags) . ';</script>';
    exit;
}

function exiger_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        repondre_json(['erreur' => 'methode_non_autorisee'], 405);
    }
}

// Pour les scripts appelés en AJAX : 401 si non connecté, 403 si mauvais rôle
function exiger_connexion_ajax(?string $role = null): array
{
    if (empty($_SESSION['user_id'])) {
        repondre_json(['erreur' => 'non_connecte'], 401);
    }
    if ($role !== null && $_SESSION['user_role'] !== $role) {
        repondre_json(['erreur' => 'acces_refuse'], 403);
    }
    return ['id' => (int) $_SESSION['user_id'], 'role' => $_SESSION['user_role']];
}

// Pour les pages PHP : redirection vers la bonne page de connexion
function exiger_connexion_page(string $role): array
{
    if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== $role) {
        header('Location: ' . ($role === 'medecin' ? 'index-medecin.html' : 'index.html'));
        exit;
    }
    return ['id' => (int) $_SESSION['user_id'], 'role' => $_SESSION['user_role']];
}

// Retourne le rendez-vous (= la consultation) uniquement si l'utilisateur en est le patient ou le médecin
function charger_rdv_autorise(PDO $pdo, $idRdv, array $user): ?array
{
    $idRdv = filter_var($idRdv, FILTER_VALIDATE_INT);
    if (!$idRdv) {
        return null;
    }
    $colonne = $user['role'] === 'medecin' ? 'r.id_medecin' : 'r.id_patient';
    $stmt = $pdo->prepare("SELECT r.*,
                                  p.prenom AS patient_prenom, p.nom AS patient_nom, p.email AS patient_email,
                                  m.prenom AS medecin_prenom, m.nom AS medecin_nom, m.specialite AS medecin_specialite
                           FROM rendez_vous r
                           JOIN utilisateurs p ON p.id = r.id_patient
                           JOIN utilisateurs m ON m.id = r.id_medecin
                           WHERE r.id = ? AND $colonne = ?");
    $stmt->execute([$idRdv, $user['id']]);
    return $stmt->fetch() ?: null;
}

function exiger_rdv_ajax(PDO $pdo, $idRdv, array $user): array
{
    $rdv = charger_rdv_autorise($pdo, $idRdv, $user);
    if (!$rdv) {
        repondre_json(['erreur' => 'consultation_introuvable'], 404);
    }
    return $rdv;
}

function generer_code_verification(PDO $pdo, int $idUser): string
{
    $code = (string) random_int(100000, 999999);
    $stmt = $pdo->prepare("UPDATE utilisateurs
                           SET code_verification = ?, code_expiration = DATE_ADD(NOW(), INTERVAL " . DUREE_CODE_MINUTES . " MINUTE), tentatives_code = 0
                           WHERE id = ?");
    $stmt->execute([$code, $idUser]);
    return $code;
}

// Envoie le code par mail ; en mode démo, le garde en session pour l'afficher si l'envoi échoue
function envoyer_code_verification(PDO $pdo, array $config, int $idUser, string $email): void
{
    require_once __DIR__ . '/envoyer_mail.php';
    $code = generer_code_verification($pdo, $idUser);
    $_SESSION['verif_email'] = $email;
    unset($_SESSION['code_demo']);
    if (!envoyerCodeVerif($config, $email, $code) && !empty($config['mode_demo'])) {
        $_SESSION['code_demo'] = $code;
    }
}

function inscrire_utilisateur(PDO $pdo, array $config, string $role, string $pageRetour): void
{
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['password'] ?? '';
    $specialite = $role === 'medecin' ? trim($_POST['specialite'] ?? '') : null;

    if ($prenom === '' || $nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        alerte_et_redirection('Merci de remplir correctement tous les champs.', $pageRetour);
    }
    if (strlen($mdp) < 8) {
        alerte_et_redirection('Le mot de passe doit contenir au moins 8 caractères.', $pageRetour);
    }

    $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        alerte_et_redirection('Cet email est déjà utilisé.', $pageRetour);
    }

    $stmt = $pdo->prepare("INSERT INTO utilisateurs (prenom, nom, email, password, role, specialite, est_verifie) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$prenom, $nom, $email, password_hash($mdp, PASSWORD_DEFAULT), $role, $specialite]);

    envoyer_code_verification($pdo, $config, (int) $pdo->lastInsertId(), $email);
    alerte_et_redirection('Compte créé ! Un code de vérification vous a été envoyé par mail.', 'verification.php');
}

function connecter_utilisateur(PDO $pdo, array $config, string $role, string $pageLogin, string $pageAccueil): void
{
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($mdp, $user['password'])) {
        alerte_et_redirection('Email ou mot de passe incorrect.', $pageLogin);
    }
    if ($user['role'] !== $role) {
        alerte_et_redirection($role === 'medecin'
            ? 'Accès refusé : vous n\'avez pas les droits de médecin.'
            : 'Accès refusé : cet espace est réservé aux patients.', $pageLogin);
    }
    if ((int) $user['est_verifie'] === 0) {
        // Nouveau code (l'ancien a pu expirer)
        envoyer_code_verification($pdo, $config, (int) $user['id'], $user['email']);
        alerte_et_redirection('Votre compte n\'est pas encore activé. Un nouveau code vous a été envoyé par mail.', 'verification.php');
    }

    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?")->execute([password_hash($mdp, PASSWORD_DEFAULT), $user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_prenom'] = $user['prenom'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_role'] = $user['role'];

    header('Location: ' . $pageAccueil);
    exit;
}
