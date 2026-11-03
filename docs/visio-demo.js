// Mode démo : outils communs aux deux salles de consultation (caméra et erreurs PeerJS).

// Caméra et micro ; si l'accès est refusé ou impossible, un flux de remplacement (image fixe)
// permet quand même d'établir l'appel et de voir l'autre participant.
async function obtenirFluxLocal(zoneMessage) {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('getUserMedia indisponible');
        return { flux: await navigator.mediaDevices.getUserMedia({ video: true, audio: true }), reel: true };
    } catch (e) {
        console.warn('Caméra indisponible :', e);
        const raison = e && e.name === 'NotAllowedError'
            ? "Accès à la caméra et au micro refusé."
            : "Aucune caméra ou micro utilisable (absent, déjà utilisé ou page non sécurisée).";
        afficherMessageVisio(zoneMessage, raison + " La visio continue sans votre image : vous verrez l'autre participant s'il a une caméra.", 'warning');
        return { flux: fluxDeRemplacement(), reel: false };
    }
}

function fluxDeRemplacement() {
    const canvas = document.createElement('canvas');
    canvas.width = 320;
    canvas.height = 240;
    const ctx = canvas.getContext('2d');
    const dessiner = () => {
        ctx.fillStyle = '#222';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#bbb';
        ctx.font = '18px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Caméra indisponible', canvas.width / 2, canvas.height / 2);
    };
    dessiner();
    // Redessin régulier : certains navigateurs n'émettent pas d'image tant que le canvas ne change pas
    setInterval(dessiner, 1000);
    return canvas.captureStream ? canvas.captureStream(1) : null;
}

function messageErreurPeer(err) {
    switch (err && err.type) {
        case 'peer-unavailable': return "L'autre participant n'est pas encore dans la salle. Réessayez quand il a ouvert la consultation.";
        case 'unavailable-id': return "Cette consultation est déjà ouverte dans un autre onglet (ou vient d'être quittée). Nouvel essai dans quelques secondes...";
        case 'browser-incompatible': return "Ce navigateur ne gère pas la visio (WebRTC).";
        case 'network':
        case 'server-error':
        case 'socket-error':
        case 'socket-closed': return "Serveur de visio PeerJS injoignable : vérifiez votre connexion. Le chat et la facture fonctionnent quand même.";
        default: return "Erreur de visio : " + ((err && err.message) || 'inconnue') + '.';
    }
}

function afficherMessageVisio(zone, texte, niveau) {
    if (!zone) return;
    zone.textContent = texte;
    zone.style.display = 'block';
    zone.className = 'visio-message visio-' + (niveau || 'info');
}
