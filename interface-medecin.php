<?php
require_once 'config.php';
$user = exiger_connexion_page('medecin');

$rdv = charger_rdv_autorise($pdo, $_GET['id'] ?? null, $user);
if (!$rdv || $rdv['statut'] !== 'Confirmé') {
    alerte_et_redirection('Consultation introuvable ou non confirmée.', 'dashboard-medecin.html');
}

$nomMedecin = 'Dr. ' . $rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom'];
$nomPatient = $rdv['patient_prenom'] . ' ' . $rdv['patient_nom'];
// Données transmises au JS : id réel du rendez-vous et identifiants PeerJS propres à cette consultation
$donneesJs = [
    'idRdv'     => (int) $rdv['id'],
    'peerLocal' => 'santeconnect-' . $rdv['id'] . '-' . $rdv['cle_visio'] . '-medecin',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - Espace Praticien - SantéConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <style>
        body { background: #121417; color: white; height: 100vh; display: flex; flex-direction: column; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        .top-bar { background: #1a1d21; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
        .btn-quitter { background: transparent; border: 1px solid #444; color: white; padding: 5px 15px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; transition: 0.3s; cursor: pointer; }
        .btn-quitter:hover { background: #333; border-color: #666; }
        .main-wrapper { display: flex; flex: 1; overflow: hidden; }
        .left-side { flex: 1; display: flex; flex-direction: column; padding: 20px; gap: 20px; overflow-y: auto; }

        .video-container {
            background: #000; border-radius: 15px; flex-grow: 1; min-height: 400px;
            position: relative; border: 1px solid #333; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }

        .grand-video { width: 100%; height: 100%; object-fit: cover; background: #000; }
        .patient-label { position: absolute; top: 15px; left: 20px; color: #aaa; font-size: 0.9rem; z-index: 10; }
        .mini-video { position: absolute; bottom: 20px; right: 20px; width: 160px; height: 110px; border: 2px solid #444; border-radius: 10px; background: #222; object-fit: cover; z-index: 10; }

        .visio-controls { display: flex; justify-content: center; gap: 10px; flex-shrink: 0; margin-bottom: 5px; }
        .btn-control { padding: 5px 20px; border-radius: 5px; font-size: 0.8rem; cursor: pointer; transition: 0.2s; background: transparent; border: 1px solid #444; color: #aaa; }
        .btn-control.btn-off { background: #e0e0e0 !important; color: #000 !important; border-color: #fff !important; }
        .btn-end { background: #dc3545; color: white; border: none; }

        .bottom-tools { background: white; color: black; border-radius: 12px; padding: 20px; display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 20px; flex-shrink: 0; }
        .tool-section h6 { border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: 15px; font-weight: bold; color: #333; }

        .patient-info-card { background: #f8f9fa; border-radius: 8px; padding: 10px; border: 1px solid #eee; min-height: 60px; }
        .info-label { font-size: 0.75rem; color: #666; text-transform: uppercase; font-weight: bold; display: block; }
        .info-value { font-size: 0.9rem; color: #000; display: block; }

        .reception-box { background: #f1f3f5; border: 1px dashed #adb5bd; border-radius: 6px; padding: 8px; margin-top: 5px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .btn-download { font-size: 0.7rem; padding: 2px 8px; }

        .right-chat { width: 350px; background: #1a1d21; border-left: 1px solid #333; display: flex; flex-direction: column; flex-shrink: 0; }
        .chat-header { padding: 15px; border-bottom: 1px solid #333; font-weight: bold; background: #000; font-size: 1rem; }
        #chat-box { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 12px; background: #121417; }
        .msg { background: #333; color: white; border-radius: 15px; padding: 10px 14px; max-width: 85%; display: flex; flex-direction: column; white-space: pre-wrap; word-break: break-word; }
        .msg.sent { align-self: flex-end; }
        .msg.received { align-self: flex-start; }
        .msg .time { font-size: 0.7rem; color: #aaa; margin-top: 5px; text-align: right; }
        .chat-input-area { padding: 15px; background: #000; display: flex; gap: 10px; border-top: 1px solid #333; }
        #msg-input { background: #222; border: 1px solid #444; color: white; border-radius: 5px; padding: 10px; flex-grow: 1; }
        .btn-ok { background: #bd2130; color: white; font-weight: bold; border: none; padding: 0 20px; border-radius: 5px; }
        .prescription-area { border: 1px solid #eee; padding: 20px; background: #fff; min-height: 300px; }
    </style>
</head>
<body>

    <div class="top-bar">
        <h5 class="m-0">SantéConnect - Espace Docteur</h5>
        <div class="d-flex align-items-center gap-3">
            <span id="status-badge" class="badge bg-secondary">EN ATTENTE</span>
            <span class="small"><?= e($nomMedecin) ?></span>
            <a onclick="quitterConsultation()" class="btn-quitter">Quitter Consultation</a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="left-side">
            <div class="video-container shadow-lg">
                <div class="patient-label">Patient : <?= e($nomPatient) ?></div>
                <video id="grand-flux" autoplay playsinline class="grand-video"></video>
                <video id="webcam" autoplay playsinline muted class="mini-video shadow"></video>
                <div id="loading-msg" class="text-muted small">En attente de l'appel patient...</div>
            </div>

            <div class="visio-controls">
                <button id="btn-mic" class="btn-control" onclick="toggleMic()">Micro Actif</button>
                <button id="btn-cam" class="btn-control" onclick="toggleVideo()">Caméra Active</button>
                <button class="btn-control btn-end" onclick="quitterConsultation()">Terminer Consultation</button>
            </div>

            <div class="bottom-tools shadow">

                <div class="tool-section">
                    <h6>Réception des Documents</h6>
                    <div class="patient-info-card border-start border-1 border-secondary bg-light">
                        <span class="info-label">Fichiers partagés par le patient</span>
                        <span class="info-value small text-muted">Motif : <?= e($rdv['motif']) ?></span>
                        <div id="documents-reception" class="mt-2">
                            <div class="reception-box"><span class="small text-muted">Aucun document reçu.</span></div>
                        </div>
                    </div>
                </div>

                <div class="tool-section text-center">
                    <h6>Actions Rapides</h6>
                    <div class="d-flex flex-column gap-2 px-4">
                        <div class="input-group input-group-sm">
                            <input type="number" id="montant-input" class="form-control" placeholder="Montant €" value="25" min="1" max="1000" step="0.01">
                            <button class="btn btn-primary" onclick="envoyerFacture()">Facturer</button>
                        </div>
                        <button class="btn btn-danger btn-sm py-2" data-bs-toggle="modal" data-bs-target="#modalOrdo">Rédiger Une Ordonnance</button>
                        <button class="btn btn-outline-secondary btn-sm py-2" data-bs-toggle="modal" data-bs-target="#modalSuivi">Planifier Suivi</button>
                    </div>
                </div>

                <div class="tool-section">
                    <h6>Statut Facturation</h6>
                    <div class="d-flex flex-column gap-2">
                        <div id="billing-status-card" class="patient-info-card border-start border-1 border-secondary bg-light">
                            <span class="info-label">État du règlement</span>
                            <span id="payment-label" class="info-value fw-bold text-muted">En attente d'émission...</span>
                        </div>

                        <div class="patient-info-card border-start border-1 border-secondary bg-light">
                            <span class="info-label">Détails de la consultation</span>
                            <span class="info-value small">
                                <span class="d-flex justify-content-between">
                                    <span>Montant :</span>
                                    <strong id="display-montant">-- €</strong>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-chat">
            <div class="chat-header"> Discussion Directe avec le patient</div>
            <div id="chat-box"></div>
            <div class="chat-input-area">
                <input type="text" id="msg-input" class="form-control shadow-none" placeholder="Écrire au patient..." maxlength="2000">
                <button class="btn-ok" onclick="envoyer()">OK</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalOrdo" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg text-dark">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title fw-bold">Rédaction Ordonnance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body bg-light px-4 pb-4">
            <div class="prescription-area shadow-sm">
                <form id="form-ordonnance">
                    <div class="d-flex justify-content-between mb-4">
                        <div><strong><?= e($nomMedecin) ?></strong><br><small class="text-muted"><?= e($rdv['medecin_specialite']) ?></small></div>
                        <div>Patient : <?= e($nomPatient) ?><br>Date : <?= date('d/m/Y') ?></div>
                    </div>
                    <textarea name="contenu" class="form-control border-0 shadow-none mb-3" rows="8" placeholder="Médicaments et posologie..." maxlength="5000" required></textarea>
                    <div class="text-end"><button type="submit" class="btn btn-success px-4">Envoyer au patient</button></div>
                </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalSuivi" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog text-dark">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Planifier un nouveau rendez-vous</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">Choisissez la date et l'heure de la prochaine consultation de suivi pour ce patient (simulation : rien n'est enregistré).</p>
            <div class="mb-3">
                <label class="form-label fw-bold">Date du prochain RDV</label>
                <input type="datetime-local" id="date-rdv" class="form-control">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="confirmerRDV()">Enregistrer le rendez-vous</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CONSULTATION = <?= json_encode($donneesJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const peer = new Peer(CONSULTATION.peerLocal, {
            debug: 1,
            config: { 'iceServers': [{ urls: 'stun:stun.l.google.com:19302' }] }
        });

        let localStream;
        let micActive = true;
        let camActive = true;

        async function startWebcam() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('webcam').srcObject = localStream;
            } catch (e) { console.error("Erreur caméra :", e); }
        }

        function toggleMic() {
            if(!localStream) return;
            micActive = !micActive;
            localStream.getAudioTracks()[0].enabled = micActive;
            const btn = document.getElementById('btn-mic');
            btn.textContent = micActive ? "Micro Actif" : "Micro Coupé";
            btn.classList.toggle('btn-off', !micActive);
        }

        function toggleVideo() {
            if(!localStream) return;
            camActive = !camActive;
            localStream.getVideoTracks()[0].enabled = camActive;
            const btn = document.getElementById('btn-cam');
            btn.textContent = camActive ? "Caméra Active" : "Caméra Coupée";
            btn.classList.toggle('btn-off', !camActive);
        }

        peer.on('call', (call) => {
            call.answer(localStream);
            call.on('stream', (remoteStream) => {
                document.getElementById('grand-flux').srcObject = remoteStream;
                document.getElementById('status-badge').textContent = "EN DIRECT";
                document.getElementById('status-badge').className = "badge bg-danger";
                document.getElementById('loading-msg').style.display = "none";
            });
        });

        // --- FACTURATION ---
        function envoyerFacture() {
            const montant = document.getElementById('montant-input').value;
            if(!montant || montant <= 0) return alert("Saisissez un montant valide.");

            const fd = new FormData();
            fd.append('id', CONSULTATION.idRdv);
            fd.append('montant', montant);

            fetch('sauvegarder-facture.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        alert("Facture de " + montant + "€ envoyée.");
                        updateBillingUI('sent', montant);
                    } else {
                        alert(data.erreur === 'deja_payee' ? "Cette consultation est déjà réglée." : "Montant invalide.");
                    }
                })
                .catch(err => console.error("Erreur envoi facture:", err));
        }

        function updateBillingUI(status, montant) {
            const card = document.getElementById('billing-status-card');
            const label = document.getElementById('payment-label');
            document.getElementById('display-montant').textContent = Number(montant).toFixed(2) + " €";

            if (status === 'sent') {
                card.className = "patient-info-card border-start border-1 border-danger bg-light";
                label.textContent = "EN ATTENTE DE PAIEMENT";
                label.className = "info-value fw-bold text-danger";
            } else if (status === 'paid') {
                card.className = "patient-info-card border-start border-1 border-success bg-light";
                label.textContent = "RÉGLÉ";
                label.className = "info-value fw-bold text-success";
            }
        }

        function verifierSiPaye() {
            fetch('get_status.php?id=' + CONSULTATION.idRdv)
                .then(res => res.json())
                .then(data => {
                    if (data.montant > 0) {
                        updateBillingUI(data.statut_paiement == 1 ? 'paid' : 'sent', data.montant);
                    }
                })
                .catch(err => console.error("Erreur vérification paiement:", err));
        }

        // --- ORDONNANCE : envoyée au patient de cette consultation ---
        document.getElementById('form-ordonnance').addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('id_rdv', CONSULTATION.idRdv);
            fetch('sauvegarder_doc.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return alert("Erreur lors de l'enregistrement de l'ordonnance.");
                    alert("Ordonnance transmise à " + data.patient + (data.mail_envoye ? " (mail envoyé)." : " (disponible dans son espace ; mail non envoyé)."));
                    e.target.reset();
                    bootstrap.Modal.getInstance(document.getElementById('modalOrdo')).hide();
                });
        });

        function confirmerRDV() {
            const dateVal = document.getElementById('date-rdv').value;
            if(!dateVal) return alert("Veuillez choisir une date.");
            alert("Rendez-vous de suivi planifié pour le : " + dateVal);
            bootstrap.Modal.getInstance(document.getElementById('modalSuivi')).hide();
        }

        // --- DOCUMENTS ENVOYÉS PAR LE PATIENT ---
        function chargerDocuments() {
            fetch('get_documents.php?id_rdv=' + CONSULTATION.idRdv)
                .then(res => res.json())
                .then(docs => {
                    const zone = document.getElementById('documents-reception');
                    const recus = docs.filter(d => d.depose_par === 'patient' && d.a_fichier);
                    if (recus.length === 0) {
                        const vide = document.createElement('div');
                        vide.className = 'reception-box';
                        vide.innerHTML = '<span class="small text-muted">Aucun document reçu.</span>';
                        zone.replaceChildren(vide);
                        return;
                    }
                    zone.replaceChildren(...recus.map(d => {
                        const ligne = document.createElement('div');
                        ligne.className = 'reception-box';
                        const libelle = document.createElement('span');
                        libelle.className = 'small fw-bold text-truncate';
                        libelle.textContent = d.type_doc + ' — ' + d.nom_original;
                        const lien = document.createElement('a');
                        lien.className = 'btn btn-sm btn-outline-primary btn-download';
                        lien.href = 'telecharger_doc.php?id=' + encodeURIComponent(d.id);
                        lien.textContent = 'Télécharger';
                        ligne.append(libelle, lien);
                        return ligne;
                    }));
                })
                .catch(err => console.error("Erreur documents :", err));
        }

        // --- CHAT : messages affichés avec textContent ---
        function envoyer() {
            const input = document.getElementById('msg-input');
            if(input.value.trim() === "") return;
            const fd = new FormData();
            fd.append('id_consultation', CONSULTATION.idRdv);
            fd.append('message', input.value);
            fetch('envoyer_message.php', { method: 'POST', body: fd }).then(() => { input.value = ''; charger(); });
        }

        function charger() {
            fetch('recuperer_messages.php?id=' + CONSULTATION.idRdv)
                .then(res => {
                    if (res.status === 401) window.location.href = 'index-medecin.html';
                    return res.json();
                })
                .then(messages => {
                    const box = document.getElementById('chat-box');
                    box.replaceChildren(...messages.map(m => {
                        const bulle = document.createElement('div');
                        bulle.className = 'msg ' + (m.moi ? 'sent' : 'received');
                        bulle.textContent = m.contenu;
                        const heure = document.createElement('span');
                        heure.className = 'time';
                        heure.textContent = m.heure;
                        bulle.appendChild(heure);
                        return bulle;
                    }));
                    box.scrollTop = box.scrollHeight;
                })
                .catch(err => console.error("Erreur chat :", err));
        }

        // --- SORTIE DE CONSULTATION : chat effacé, rendez-vous terminé (la facture est conservée) ---
        function quitterConsultation() {
            if(confirm("Terminer la consultation ? Le chat sera effacé.")) {
                const fd = new FormData();
                fd.append('id', CONSULTATION.idRdv);
                fetch('effacer_messages.php', { method: 'POST', body: fd })
                    .then(() => { window.location.href = "dashboard-medecin.html"; })
                    .catch(err => console.error("Erreur fin de consultation :", err));
            }
        }

        window.onload = () => {
            startWebcam();
            charger();
            chargerDocuments();
            verifierSiPaye();
            setInterval(charger, 2000);
            setInterval(verifierSiPaye, 3000);
            setInterval(chargerDocuments, 5000);
        };

        document.getElementById('msg-input').addEventListener('keypress', (e) => {
            if(e.key === 'Enter') envoyer();
        });
    </script>
</body>
</html>
