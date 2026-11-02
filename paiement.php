<?php
// Paiement simulé : aucune donnée bancaire n'est demandée.
// Sans ?id= : liste des factures du patient. Avec ?id= : règlement d'une consultation.
require_once 'config.php';
$user = exiger_connexion_page('patient');

$rdv = isset($_GET['id']) ? charger_rdv_autorise($pdo, $_GET['id'], $user) : null;

if (!$rdv) {
    $stmt = $pdo->prepare("SELECT r.id, r.montant, r.statut_paiement, DATE_FORMAT(r.date_rdv, '%d/%m/%Y %H:%i') AS date_rdv,
                                  CONCAT('Dr. ', m.prenom, ' ', m.nom) AS medecin
                           FROM rendez_vous r JOIN utilisateurs m ON m.id = r.id_medecin
                           WHERE r.id_patient = ? AND r.montant > 0
                           ORDER BY r.date_rdv DESC");
    $stmt->execute([$user['id']]);
    $factures = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - SantéConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 15px; }
        .btn-success { background-color: #28a745; border: none; font-weight: bold; }
        .btn-success:hover { background-color: #218838; }
        .amount-box { background-color: #e7f3ff; border: 1px solid #b6d4fe; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container mt-5" style="max-width: 500px;">
    <div class="alert alert-warning text-center fw-bold">Paiement simulé – démonstration</div>

    <div class="card shadow border-0">
        <div class="card-body p-4">
        <?php if ($rdv): ?>
            <h4 class="card-title text-center mb-1">Paiement de la consultation</h4>
            <p class="text-center text-muted small mb-4">Dr. <?= e($rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom']) ?> – <?= e(date('d/m/Y H:i', strtotime($rdv['date_rdv']))) ?></p>

            <?php if ($rdv['montant'] <= 0): ?>
                <p class="text-center text-muted">Aucune facture n'a encore été émise pour cette consultation.</p>
            <?php else: ?>
                <div class="amount-box p-3 mb-4 d-flex justify-content-between align-items-center">
                    <span class="text-primary fw-bold">Montant à régler :</span>
                    <h4 class="m-0 text-dark"><?= number_format((float) $rdv['montant'], 2, ',', ' ') ?> €</h4>
                </div>

                <?php if ((int) $rdv['statut_paiement'] === 1): ?>
                    <p class="text-center fw-bold text-success">RÉGLÉ</p>
                <?php else: ?>
                    <form action="valider_paiement.php" method="POST" id="payment-form">
                        <input type="hidden" name="id" value="<?= (int) $rdv['id'] ?>">
                        <button type="submit" id="submit-btn" class="btn btn-success w-100 py-3 mt-2 shadow-sm">
                            Simuler le paiement
                        </button>
                    </form>
                    <p class="text-muted small text-center mt-2 mb-0">Aucune donnée bancaire n'est demandée ni enregistrée.</p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="text-center mt-4">
                <?php if ($rdv['statut'] === 'Confirmé'): ?>
                    <a href="consultation.php?id=<?= (int) $rdv['id'] ?>" class="text-decoration-none small text-secondary">Retourner à la consultation</a>
                <?php else: ?>
                    <a href="paiement.php" class="text-decoration-none small text-secondary">Retour à mes factures</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <h4 class="card-title text-center mb-4">Mes factures</h4>
            <?php if (isset($_GET['paiement'])): ?>
                <div class="alert alert-success small text-center">Paiement simulé enregistré.</div>
            <?php endif; ?>
            <?php if (empty($factures)): ?>
                <p class="text-center text-muted">Aucune facture pour le moment.</p>
            <?php endif; ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($factures as $f): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="small"><?= e($f['date_rdv']) ?><br><span class="text-muted"><?= e($f['medecin']) ?></span></span>
                        <span class="text-end">
                            <strong><?= number_format((float) $f['montant'], 2, ',', ' ') ?> €</strong><br>
                            <?php if ((int) $f['statut_paiement'] === 1): ?>
                                <span class="badge bg-success">Réglé</span>
                            <?php else: ?>
                                <a href="paiement.php?id=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-success py-0">Payer</a>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="text-center mt-4">
                <a href="dashboard.html" class="text-decoration-none small text-secondary">Retour à mon espace</a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Désactive le bouton après le clic pour éviter les doubles validations
    const form = document.getElementById('payment-form');
    if (form) {
        form.onsubmit = function() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Traitement...';
        };
    }
</script>

</body>
</html>
