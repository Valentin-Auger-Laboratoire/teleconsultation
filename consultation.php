<?php
require_once 'config.php';
$user = exiger_connexion_page('patient');

$rdv = charger_rdv_autorise($pdo, $_GET['id'] ?? null, $user);
if (!$rdv || $rdv['statut'] !== 'Confirmé') {
    alerte_et_redirection('Consultation introuvable ou pas encore confirmée par le médecin.', 'dashboard.html');
}

$nomMedecin = 'Dr. ' . $rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom'];
// Données transmises au JS : id réel du rendez-vous et identifiants PeerJS propres à cette consultation
$donneesJs = [
    'idRdv'      => (int) $rdv['id'],
    'peerLocal'  => 'santeconnect-' . $rdv['id'] . '-' . $rdv['cle_visio'] . '-patient',
    'peerDistant' => 'santeconnect-' . $rdv['id'] . '-' . $rdv['cle_visio'] . '-medecin',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - SantéConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <style>
        body { background: #121417; color: white; height: 100vh; display: flex; flex-direction: column; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        .top-bar { background: #007bff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .btn-quitter { background: transparent; border: 1px solid white; color: white; padding: 5px 15px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; transition: 0.3s; cursor: pointer; }
        .btn-quitter:hover { background: white; color: #007bff; }
        .main-wrapper { display: flex; flex: 1; overflow: hidden; }
        .left-side { flex: 1; display: flex; flex-direction: column; padding: 20px; gap: 20px; overflow-y: auto; }
        .video-container { background: #000; border-radius: 15px; flex-grow: 1; min-height: 400px; position: relative; border: 1px solid #333; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .dr-label { position: absolute; top: 15px; left: 20px; color: #aaa; font-size: 0.9rem; z-index: 10; }
        .grand-video { width: 100%; height: 100%; object-fit: cover; }
        .mini-video { position: absolute; bottom: 20px; right: 20px; width: 160px; height: 110px; border: 2px solid #444; border-radius: 10px; background: #222; object-fit: cover; z-index: 10; }
        .visio-controls { display: flex; justify-content: center; gap: 10px; flex-shrink: 0; margin-bottom: 5px; }
        .btn-control { padding: 5px 20px; border-radius: 5px; font-size: 0.8rem; cursor: pointer; transition: 0.2s; background: transparent; border: 1px solid #444; color: #aaa; }
        .btn-control.btn-off { background: #e0e0e0 !important; color: #000 !important; border-color: #fff !important; }
        .btn-end { background: #dc3545; color: white; border: none; }
        .bottom-tools { background: white; color: black; border-radius: 12px; padding: 20px; display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 20px; flex-shrink: 0; }
        .tool-section h6 { border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: 15px; font-weight: bold; color: #333; }
        .doc-row { display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; }
        .doc-label { font-weight: bold; font-size: 0.9rem; width: 120px; color: #333; }
        .doc-input-wrapper { flex: 1; margin: 0 15px; }
        .btn-transfert { background: #007bff; color: white; border: none; padding: 6px 15px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; }
        #payment-zone { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; border: 1px solid #dee2e6; min-height: 120px; display: flex; flex-direction: column; justify-content: center; }
        .right-chat { width: 350px; background: #1a1d21; border-left: 1px solid #333; display: flex; flex-direction: column; flex-shrink: 0; }
        .chat-header { padding: 15px; border-bottom: 1px solid #333; font-weight: bold; background: #000; }
        #chat-box { flex: 1; overflow-y: auto; padding: 15px; background: #121417; display: flex; flex-direction: column; gap: 10px; }
        .msg { background: #333; color: white; border-radius: 15px; padding: 10px 14px; max-width: 85%; display: flex; flex-direction: column; white-space: pre-wrap; word-break: break-word; }
        .msg.sent { align-self: flex-end; }
        .msg.received { align-self: flex-start; }
        .msg .time { font-size: 0.7rem; color: #aaa; margin-top: 5px; text-align: right; }
        .chat-input-area { padding: 15px; background: #000; display: flex; gap: 10px; border-top: 1px solid #333; }
        #msg-input { background: #222; border: 1px solid #444; color: white; border-radius: 5px; padding: 10px; flex-grow: 1; }
        .btn-ok { background: #bd2130; color: white; font-weight: bold; border: none; padding: 0 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="top-bar">
        <h5 class="m-0">SantéConnect - Espace Patient</h5>
        <div class="d-flex align-items-center gap-3">
            <span id="status-visio" class="badge bg-danger">EN ATTENTE</span>
            <button onclick="quitterConsultation()" class="btn-quitter">Quitter la Consultation</button>
        </div>
    </div>
    <div class="main-wrapper">
        <div class="left-side">
            <div class="video-container shadow-lg">
                <div class="dr-label"><?= e($nomMedecin) ?></div>
                <video id="grand-flux" autoplay playsinline class="grand-video"></video>
                <video id="webcam" autoplay playsinline muted class="mini-video shadow"></video>
                <div id="loading-text" class="text-muted small">Connexion sécurisée...</div>
            </div>
            <div class="visio-controls">
                <button id="btn-mic" class="btn-control" onclick="toggleMic()">Micro Actif</button>
                <button id="btn-cam" class="btn-control" onclick="toggleVideo()">Caméra Active</button>
                <button id="btn-appel" class="btn-control btn-end">Lancer l'appel</button>
            </div>
            <div class="bottom-tools shadow">
                <div class="tool-section">
                    <h6>Envoyer au médecin <small class="text-muted fw-normal">(PDF, JPG ou PNG, 5 Mo max.)</small></h6>
                    <div class="doc-row">
                        <div class="doc-label">Dernière Ordonnance</div>
                        <div class="doc-input-wrapper"><input type="file" class="form-control form-control-sm" id="input-ord" accept=".pdf,.jpg,.jpeg,.png"></div>
                        <button class="btn-transfert" onclick="envoyerDocument('Ordonnance', 'input-ord')">Transférer</button>
                    </div>
                    <div class="doc-row">
                        <div class="doc-label">Dernier Bilan Sanguin</div>
                        <div class="doc-input-wrapper"><input type="file" class="form-control form-control-sm" id="input-bilan" accept=".pdf,.jpg,.jpeg,.png"></div>
                        <button class="btn-transfert" onclick="envoyerDocument('Bilan Sanguin', 'input-bilan')">Transférer</button>
                    </div>
                    <div class="doc-row">
                        <div class="doc-label">Autre fichier</div>
                        <div class="doc-input-wrapper"><input type="file" class="form-control form-control-sm" id="input-autre" accept=".pdf,.jpg,.jpeg,.png"></div>
                        <button class="btn-transfert" onclick="envoyerDocument('Autre fichier', 'input-autre')">Transférer</button>
                    </div>
                </div>
                <div class="tool-section">
                    <h6>Statut Facturation</h6>
                    <div id="payment-zone">
                        <div id="wait-doc">
                            <div class="text-muted small">En attente de la facture...</div>
                        </div>
                        <div id="facture-recue" style="display:none;">
                            <h3 class="mb-1"><span id="montant-final">0</span>€</h3>
                            <p id="statut-paiement-texte" class="small fw-bold mb-2"></p>
                            <a href="paiement.php?id=<?= (int) $rdv['id'] ?>" id="btn-payer" class="btn btn-success btn-sm w-100 fw-bold">PAYER MAINTENANT</a>
                            <p id="texte-regle" style="display:none;" class="fw-bold text-success mt-2">RÉGLÉ</p>
                            <p class="text-muted small mb-0">Merci pour votre paiement !</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="right-chat">
            <div class="chat-header">Discussion Directe avec le Docteur</div>
            <div id="chat-box"></div>
            <div class="chat-input-area">
                <input type="text" id="msg-input" class="form-control shadow-none" placeholder="Écrire..." maxlength="2000">
                <button class="btn-ok" onclick="envoyer()">OK</button>
            </div>
        </div>
    </div>

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
            } catch (e) { console.error("Caméra bloquée", e); }
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

        document.getElementById('btn-appel').addEventListener('click', () => {
            if (!localStream) return alert("Caméra non prête");
            const call = peer.call(CONSULTATION.peerDistant, localStream);
            call.on('stream', (remoteStream) => {
                document.getElementById('grand-flux').srcObject = remoteStream;
                document.getElementById('status-visio').textContent = "EN DIRECT";
                document.getElementById('status-visio').className = "badge bg-success";
                document.getElementById('loading-text').style.display = "none";
            });
        });

        function quitterConsultation() {
            if(confirm("Quitter la consultation ?")) {
                window.location.href = "dashboard.html";
            }
        }

        // --- CHAT : messages affichés avec textContent (aucune injection HTML possible) ---
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
                    if (res.status === 401) window.location.href = 'index.html';
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

        function verifierFacture() {
            fetch('get_status.php?id=' + CONSULTATION.idRdv)
                .then(response => response.json())
                .then(data => {
                    const montant = parseFloat(data?.montant);
                    const statutPaiement = parseInt(data?.statut_paiement);
                    const btnPayer = document.getElementById('btn-payer');
                    const statutTexte = document.getElementById('statut-paiement-texte');
                    const texteRegle = document.getElementById('texte-regle');

                    if (!isNaN(montant) && montant > 0) {
                        document.getElementById('wait-doc').style.display = 'none';
                        document.getElementById('facture-recue').style.display = 'block';
                        document.getElementById('montant-final').textContent = montant.toFixed(2);
                        if (statutPaiement === 1) {
                            btnPayer.style.display = 'none';
                            statutTexte.style.display = 'none';
                            texteRegle.style.display = 'block';
                        } else {
                            btnPayer.style.display = 'block';
                            texteRegle.style.display = 'none';
                            statutTexte.textContent = "PAIEMENT REQUIS";
                            statutTexte.className = "small text-danger fw-bold mb-2";
                        }
                    } else {
                        document.getElementById('wait-doc').style.display = 'block';
                        document.getElementById('facture-recue').style.display = 'none';
                    }
                })
                .catch(err => console.error("Erreur check facture:", err));
        }

        // --- ENVOI DE DOCUMENTS AU MÉDECIN ---
        function envoyerDocument(nom, id) {
            const input = document.getElementById(id);
            if (!input.files.length) return alert("Choisissez d'abord un fichier.");
            const fd = new FormData();
            fd.append('fichier', input.files[0]);
            fd.append('type', nom);
            fd.append('id_rdv', CONSULTATION.idRdv);
            fetch('upload_doc.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        alert(nom + " envoyé !");
                        input.value = '';
                    } else {
                        alert("Envoi refusé : " + ({
                            type_non_autorise: "seuls les fichiers PDF, JPG et PNG sont acceptés.",
                            fichier_trop_lourd: "le fichier dépasse 5 Mo."
                        }[data.erreur] || "erreur lors de l'envoi."));
                    }
                });
        }

        window.onload = () => {
            startWebcam();
            setInterval(charger, 2000);
            setInterval(verifierFacture, 3000);
            charger();
            verifierFacture();
        }

        document.getElementById('msg-input').addEventListener('keypress', (e) => {
            if(e.key === 'Enter') envoyer();
        });
    </script>
</body>
</html>
