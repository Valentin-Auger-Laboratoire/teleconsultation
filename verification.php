<?php
require_once 'config.php';

$email = $_SESSION['verif_email'] ?? '';
if ($email === '') {
    header('Location: index.html');
    exit;
}
$codeDemo = $_SESSION['code_demo'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - SantéConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { background: #ffffff; border: 1px solid #000000; max-width: 400px; width: 100%; }
        .form-control { background: #ffffff; border: 1px solid #444; text-align: center; font-size: 1.5rem; letter-spacing: 5px; }
    </style>
</head>

<body>
    <div class="card shadow p-4">
        <h3 class="text-center mb-3">Vérification</h3>
        <p class="text-center small text-danger">Entrez le code de sécurité envoyé à <strong><?= e($email) ?></strong> pour activer votre compte. Il est valable <?= DUREE_CODE_MINUTES ?> minutes.</p>

        <?php if ($codeDemo): ?>
            <div class="alert alert-warning text-center small">
                <strong>Mode démonstration</strong> (envoi du mail impossible)<br>
                Code : <strong class="fs-5"><?= e($codeDemo) ?></strong>
            </div>
        <?php endif; ?>

        <form action="valider_compte.php" method="POST">
            <div class="mb-3">
                <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">ACTIVER MON COMPTE</button>
        </form>
        <form action="valider_compte.php" method="POST" class="mt-2">
            <input type="hidden" name="action" value="renvoyer">
            <button type="submit" class="btn btn-link w-100 small">Recevoir un nouveau code</button>
        </form>
    </div>
</body>
</html>
