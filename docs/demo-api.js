// SantéConnect – mode démo statique (GitHub Pages).
// Remplace tous les appels aux scripts PHP par des réponses simulées : les données fictives
// sont gardées dans le localStorage (partagé entre les onglets), l'utilisateur connecté
// dans le sessionStorage (propre à chaque onglet, pour avoir un patient et un médecin en parallèle).
// Rien n'est envoyé à un serveur, à part la signalisation de la visio (serveur public PeerJS).
(function () {
    'use strict';

    const CLE_DONNEES = 'santeconnect-demo:donnees';
    const CLE_SESSION = 'santeconnect-demo:utilisateur';
    const VERSION = 1;
    const TAILLE_MAX = 5 * 1024 * 1024;
    // Au-delà, le fichier est accepté mais son contenu n'est pas conservé (localStorage limité à ~5 Mo)
    const TAILLE_MAX_STOCKEE = 1024 * 1024;

    // SHA-256 des mots de passe fictifs Patient-Demo-2026 et Medecin-Demo-2026
    const HASH_PATIENT = 'e37a355d495199fad3c624653aef008b741fe8ccb6c07f0bf0220cdfe42a63e2';
    const HASH_MEDECIN = '9ec9fbd5b7a275c23f6a18d898011bded75b8b0281d817c0c94995b08c163e1b';

    const fetchOriginal = window.fetch.bind(window);

    // --- OUTILS ---
    function deux(n) { return String(n).padStart(2, '0'); }

    function formaterDate(iso) {
        const d = new Date(iso);
        return deux(d.getDate()) + '/' + deux(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + deux(d.getHours()) + ':' + deux(d.getMinutes());
    }

    function formaterHeure(iso) {
        const d = new Date(iso);
        return deux(d.getHours()) + ':' + deux(d.getMinutes());
    }

    // Date relative à aujourd'hui : dans `jours` jours à hh:mm
    function dansJours(jours, heure, minute) {
        const d = new Date();
        d.setDate(d.getDate() + jours);
        d.setHours(heure, minute, 0, 0);
        return d.toISOString();
    }

    function cleAleatoire() {
        const octets = new Uint8Array(16);
        crypto.getRandomValues(octets);
        return Array.from(octets, o => o.toString(16).padStart(2, '0')).join('');
    }

    async function sha256(texte) {
        const empreinte = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(texte));
        return Array.from(new Uint8Array(empreinte), o => o.toString(16).padStart(2, '0')).join('');
    }

    function lireFichier(fichier) {
        return new Promise((resoudre, rejeter) => {
            const lecteur = new FileReader();
            lecteur.onload = () => resoudre(lecteur.result);
            lecteur.onerror = () => rejeter(lecteur.error);
            lecteur.readAsDataURL(fichier);
        });
    }

    // --- DONNÉES FICTIVES ---
    function donneesInitiales() {
        const rdvPasse = dansJours(-7, 10, 30);
        return {
            version: VERSION,
            sequences: { utilisateurs: 3, creneaux: 7, rendez_vous: 1, messages: 0, documents: 1 },
            utilisateurs: [
                { id: 1, prenom: 'Claire', nom: 'Martin', email: 'medecin.demo@exemple.com', hash: HASH_MEDECIN, role: 'medecin', specialite: 'Généraliste' },
                { id: 2, prenom: 'Lucas', nom: 'Bernard', email: 'patient.demo@exemple.com', hash: HASH_PATIENT, role: 'patient', specialite: null },
                { id: 3, prenom: 'Sophie', nom: 'Rousseau', email: 'dermato.demo@exemple.com', hash: HASH_MEDECIN, role: 'medecin', specialite: 'Dermatologue' }
            ],
            creneaux: [
                { id: 1, id_medecin: 1, date_heure: dansJours(1, 9, 0), disponible: true },
                { id: 2, id_medecin: 1, date_heure: dansJours(1, 14, 30), disponible: true },
                { id: 3, id_medecin: 1, date_heure: dansJours(2, 10, 0), disponible: true },
                { id: 4, id_medecin: 1, date_heure: dansJours(3, 16, 0), disponible: true },
                { id: 5, id_medecin: 3, date_heure: dansJours(2, 11, 0), disponible: true },
                { id: 6, id_medecin: 3, date_heure: dansJours(4, 15, 30), disponible: true },
                { id: 7, id_medecin: 1, date_heure: rdvPasse, disponible: false }
            ],
            rendez_vous: [
                { id: 1, id_patient: 2, id_medecin: 1, id_creneau: 7, date_rdv: rdvPasse, motif: 'Toux persistante depuis une semaine',
                  statut: 'Terminé', montant: 25, statut_paiement: 1, cle_visio: cleAleatoire() }
            ],
            messages: [],
            documents: [
                { id: 1, id_patient: 2, id_medecin: 1, id_rdv: 1, depose_par: 'medecin', type_doc: 'Ordonnance',
                  contenu: 'Sirop antitussif : 1 cuillère à soupe 3 fois par jour pendant 5 jours.\nRepos et hydratation.',
                  nom_original: null, a_fichier: false, donnees: null, date_creation: rdvPasse }
            ]
        };
    }

    function lireDonnees() {
        try {
            const donnees = JSON.parse(localStorage.getItem(CLE_DONNEES));
            if (donnees && donnees.version === VERSION) return donnees;
        } catch (e) { /* données absentes ou illisibles : on repart des données fictives */ }
        const initiales = donneesInitiales();
        ecrireDonnees(initiales);
        return initiales;
    }

    function ecrireDonnees(donnees) {
        localStorage.setItem(CLE_DONNEES, JSON.stringify(donnees));
    }

    function nouvelId(donnees, table) {
        donnees.sequences[table] += 1;
        return donnees.sequences[table];
    }

    // --- SESSION (propre à l'onglet) ---
    function utilisateurConnecte(donnees) {
        const id = Number(sessionStorage.getItem(CLE_SESSION));
        return donnees.utilisateurs.find(u => u.id === id) || null;
    }

    function connecter(user) {
        sessionStorage.setItem(CLE_SESSION, String(user.id));
    }

    // --- RÉPONSES ---
    function json(donnees, code) {
        return new Response(JSON.stringify(donnees), {
            status: code || 200,
            headers: { 'Content-Type': 'application/json; charset=utf-8' }
        });
    }

    class ErreurHttp extends Error {
        constructor(code, erreur) { super(erreur); this.code = code; this.erreur = erreur; }
    }

    function exigerConnexion(donnees, role) {
        const user = utilisateurConnecte(donnees);
        if (!user) throw new ErreurHttp(401, 'non_connecte');
        if (role && user.role !== role) throw new ErreurHttp(403, 'acces_refuse');
        return user;
    }

    // Même règle que charger_rdv_autorise() : seul le patient ou le médecin du rendez-vous y a accès
    function exigerRdv(donnees, id, user) {
        const rdv = donnees.rendez_vous.find(r => r.id === Number(id));
        const colonne = user.role === 'medecin' ? 'id_medecin' : 'id_patient';
        if (!rdv || rdv[colonne] !== user.id) throw new ErreurHttp(404, 'consultation_introuvable');
        return rdv;
    }

    function nomDocteur(u) { return 'Dr. ' + u.prenom + ' ' + u.nom; }
    function utilisateur(donnees, id) { return donnees.utilisateurs.find(u => u.id === id); }

    function docPublic(d) {
        return { id: d.id, type_doc: d.type_doc, contenu: d.contenu, nom_original: d.nom_original,
                 depose_par: d.depose_par, a_fichier: d.a_fichier, date_creation: formaterDate(d.date_creation) };
    }

    // Réponse aux formulaires (connexion, inscription, paiement) : alerte éventuelle puis redirection
    function redirection(url, alerte) {
        return { redirection: url, alerte: alerte || null };
    }

    // --- POINTS D'ACCÈS SIMULÉS (un par script PHP) ---
    const routes = {
        'moi.php': (d) => {
            const u = exigerConnexion(d);
            return { id: u.id, role: u.role, prenom: u.prenom, nom: u.nom };
        },

        'get_medecins.php': (d) => {
            exigerConnexion(d);
            return d.utilisateurs.filter(u => u.role === 'medecin')
                .sort((a, b) => (a.nom + a.prenom).localeCompare(b.nom + b.prenom))
                .map(u => ({ id: u.id, prenom: u.prenom, nom: u.nom, specialite: u.specialite }));
        },

        'get_creneaux.php': (d, q) => {
            const u = exigerConnexion(d);
            const idMedecin = u.role === 'medecin' ? u.id : Number(q.get('id_medecin'));
            const maintenant = Date.now();
            return d.creneaux
                .filter(c => c.id_medecin === idMedecin && c.disponible && new Date(c.date_heure).getTime() > maintenant)
                .sort((a, b) => a.date_heure.localeCompare(b.date_heure))
                .map(c => ({ id: c.id, libelle: formaterDate(c.date_heure) }));
        },

        'save_creneau.php': (d, q, f) => {
            const u = exigerConnexion(d, 'medecin');
            const date = new Date(f.get('date_heure') || '');
            if (isNaN(date) || date.getTime() <= Date.now()) throw new ErreurHttp(400, 'date_invalide');
            date.setSeconds(0, 0);
            const iso = date.toISOString();
            if (!d.creneaux.some(c => c.id_medecin === u.id && c.date_heure === iso)) {
                d.creneaux.push({ id: nouvelId(d, 'creneaux'), id_medecin: u.id, date_heure: iso, disponible: true });
            }
            return { ok: true };
        },

        'delete_creneau.php': (d, q, f) => {
            const u = exigerConnexion(d, 'medecin');
            const avant = d.creneaux.length;
            d.creneaux = d.creneaux.filter(c => !(c.id === Number(f.get('id')) && c.id_medecin === u.id && c.disponible));
            return { ok: d.creneaux.length < avant };
        },

        'save_rdv.php': (d, q, f) => {
            const u = exigerConnexion(d, 'patient');
            const creneau = d.creneaux.find(c => c.id === Number(f.get('id_creneau')));
            if (!creneau || !creneau.disponible || new Date(creneau.date_heure).getTime() <= Date.now()) {
                throw new ErreurHttp(409, 'creneau_indisponible');
            }
            const motif = String(f.get('motif') || '').trim().slice(0, 500) || 'Non précisé';
            creneau.disponible = false;
            // cle_visio : partie aléatoire des identifiants PeerJS, partagée par les deux onglets via le localStorage
            const rdv = { id: nouvelId(d, 'rendez_vous'), id_patient: u.id, id_medecin: creneau.id_medecin, id_creneau: creneau.id,
                          date_rdv: creneau.date_heure, motif, statut: 'En attente', montant: 0, statut_paiement: 0, cle_visio: cleAleatoire() };
            d.rendez_vous.push(rdv);
            return { ok: true, id: rdv.id };
        },

        'get_rdv.php': (d) => {
            const u = exigerConnexion(d, 'patient');
            return d.rendez_vous.filter(r => r.id_patient === u.id)
                .sort((a, b) => a.date_rdv.localeCompare(b.date_rdv))
                .map(r => {
                    const m = utilisateur(d, r.id_medecin);
                    return { id: r.id, medecin: nomDocteur(m), specialite: m.specialite, date_rdv: formaterDate(r.date_rdv),
                             statut: r.statut, montant: r.montant, statut_paiement: r.statut_paiement };
                });
        },

        'get_all_rdv.php': (d) => {
            const u = exigerConnexion(d, 'medecin');
            return d.rendez_vous.filter(r => r.id_medecin === u.id && r.statut !== 'Terminé')
                .sort((a, b) => a.date_rdv.localeCompare(b.date_rdv))
                .map(r => {
                    const p = utilisateur(d, r.id_patient);
                    return { id: r.id, patient: p.prenom + ' ' + p.nom, motif: r.motif, date_rdv: formaterDate(r.date_rdv),
                             statut: r.statut, montant: r.montant, statut_paiement: r.statut_paiement };
                });
        },

        'confirm_rdv.php': (d, q, f) => {
            const rdv = exigerRdv(d, f.get('id'), exigerConnexion(d, 'medecin'));
            if (rdv.statut === 'En attente') rdv.statut = 'Confirmé';
            return { ok: true };
        },

        // Remplace les données que consultation.php, interface-medecin.php et paiement.php injectaient côté serveur
        'get_consultation.php': (d, q) => {
            const u = exigerConnexion(d);
            const rdv = exigerRdv(d, q.get('id'), u);
            const m = utilisateur(d, rdv.id_medecin);
            const p = utilisateur(d, rdv.id_patient);
            return { id: rdv.id, statut: rdv.statut, motif: rdv.motif, date_rdv: formaterDate(rdv.date_rdv),
                     montant: rdv.montant, statut_paiement: rdv.statut_paiement,
                     medecin: nomDocteur(m), medecin_specialite: m.specialite, patient: p.prenom + ' ' + p.nom,
                     peer_patient: 'santeconnect-demo-' + rdv.id + '-' + rdv.cle_visio + '-patient',
                     peer_medecin: 'santeconnect-demo-' + rdv.id + '-' + rdv.cle_visio + '-medecin' };
        },

        'get_factures.php': (d) => {
            const u = exigerConnexion(d, 'patient');
            return d.rendez_vous.filter(r => r.id_patient === u.id && r.montant > 0)
                .sort((a, b) => b.date_rdv.localeCompare(a.date_rdv))
                .map(r => ({ id: r.id, montant: r.montant, statut_paiement: r.statut_paiement,
                             date_rdv: formaterDate(r.date_rdv), medecin: nomDocteur(utilisateur(d, r.id_medecin)) }));
        },

        'envoyer_message.php': (d, q, f) => {
            const u = exigerConnexion(d);
            const rdv = exigerRdv(d, f.get('id_consultation'), u);
            const message = String(f.get('message') || '').trim();
            if (!message) throw new ErreurHttp(400, 'message_vide');
            d.messages.push({ id: nouvelId(d, 'messages'), id_consultation: rdv.id, id_expediteur: u.id,
                              contenu: message.slice(0, 2000), date_envoi: new Date().toISOString() });
            return { ok: true };
        },

        'recuperer_messages.php': (d, q) => {
            const u = exigerConnexion(d);
            const rdv = exigerRdv(d, q.get('id'), u);
            return d.messages.filter(m => m.id_consultation === rdv.id)
                .map(m => ({ contenu: m.contenu, heure: formaterHeure(m.date_envoi), moi: m.id_expediteur === u.id }));
        },

        'effacer_messages.php': (d, q, f) => {
            const rdv = exigerRdv(d, f.get('id'), exigerConnexion(d, 'medecin'));
            d.messages = d.messages.filter(m => m.id_consultation !== rdv.id);
            rdv.statut = 'Terminé';
            return { ok: true };
        },

        'get_status.php': (d, q) => {
            const rdv = exigerRdv(d, q.get('id'), exigerConnexion(d));
            return { montant: rdv.montant, statut_paiement: rdv.statut_paiement, statut: rdv.statut };
        },

        'sauvegarder-facture.php': (d, q, f) => {
            const rdv = exigerRdv(d, f.get('id'), exigerConnexion(d, 'medecin'));
            const montant = parseFloat(f.get('montant'));
            if (isNaN(montant) || montant <= 0 || montant > 1000) throw new ErreurHttp(400, 'montant_invalide');
            if (rdv.statut_paiement === 1) throw new ErreurHttp(409, 'deja_payee');
            rdv.montant = Math.round(montant * 100) / 100;
            rdv.statut_paiement = 0;
            return { ok: true };
        },

        // Formulaire de paiement simulé (aucune donnée bancaire)
        'valider_paiement.php': (d, q, f) => {
            const rdv = exigerRdv(d, f.get('id'), exigerConnexion(d, 'patient'));
            if (rdv.montant <= 0) return redirection('paiement.html', 'Aucune facture à régler pour cette consultation.');
            rdv.statut_paiement = 1;
            return redirection(rdv.statut === 'Terminé' ? 'paiement.html?paiement=success' : 'consultation.html?id=' + rdv.id + '&paiement=success');
        },

        // Ordonnance : enregistrée pour le patient, et l'e-mail est renvoyé pour être affiché en aperçu
        'sauvegarder_doc.php': (d, q, f) => {
            const u = exigerConnexion(d, 'medecin');
            const rdv = exigerRdv(d, f.get('id_rdv'), u);
            const contenu = String(f.get('contenu') || '').trim();
            if (!contenu) throw new ErreurHttp(400, 'contenu_vide');
            d.documents.push({ id: nouvelId(d, 'documents'), id_patient: rdv.id_patient, id_medecin: u.id, id_rdv: rdv.id,
                               depose_par: 'medecin', type_doc: 'Ordonnance', contenu: contenu.slice(0, 5000),
                               nom_original: null, a_fichier: false, donnees: null, date_creation: new Date().toISOString() });
            const p = utilisateur(d, rdv.id_patient);
            return { ok: true, patient: p.prenom + ' ' + p.nom, mail_envoye: false,
                     apercu_mail: { a: p.email, objet: 'Nouvelle ordonnance - ' + nomDocteur(u), medecin: nomDocteur(u), contenu } };
        },

        'upload_doc.php': (d, q, f, p) => {
            const u = exigerConnexion(d, 'patient');
            const fichier = f.get('fichier');
            if (!(fichier instanceof File) || !fichier.name) throw new ErreurHttp(400, 'fichier_manquant');
            if (fichier.size > TAILLE_MAX) throw new ErreurHttp(400, 'fichier_trop_lourd');
            const extension = fichier.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension) || !['application/pdf', 'image/jpeg', 'image/png'].includes(fichier.type)) {
                throw new ErreurHttp(400, 'type_non_autorise');
            }
            const typeDoc = ['Ordonnance', 'Bilan Sanguin', 'Autre fichier'].includes(f.get('type')) ? f.get('type') : 'Autre fichier';
            let idRdv = null;
            let idMedecin = null;
            if (f.get('id_rdv')) {
                const rdv = exigerRdv(d, f.get('id_rdv'), u);
                idRdv = rdv.id;
                idMedecin = rdv.id_medecin;
            }
            const donnees = p.donneesFichier || null;
            d.documents.push({ id: nouvelId(d, 'documents'), id_patient: u.id, id_medecin: idMedecin, id_rdv: idRdv,
                               depose_par: 'patient', type_doc: typeDoc, contenu: null, nom_original: fichier.name.slice(0, 255),
                               a_fichier: true, donnees, date_creation: new Date().toISOString() });
            return { ok: true };
        },

        'get_documents.php': (d, q) => {
            const u = exigerConnexion(d);
            let docs;
            if (u.role === 'patient') {
                docs = d.documents.filter(doc => doc.id_patient === u.id);
            } else {
                const rdv = exigerRdv(d, q.get('id_rdv'), u);
                docs = d.documents.filter(doc => doc.id_rdv === rdv.id);
            }
            return docs.sort((a, b) => b.date_creation.localeCompare(a.date_creation)).map(docPublic);
        },

        'telecharger_doc.php': (d, q) => {
            const u = exigerConnexion(d);
            const colonne = u.role === 'medecin' ? 'id_medecin' : 'id_patient';
            const doc = d.documents.find(x => x.id === Number(q.get('id')) && x[colonne] === u.id && x.a_fichier);
            if (!doc) throw new ErreurHttp(404, 'document_introuvable');
            if (!doc.donnees) throw new ErreurHttp(404, 'fichier_non_conserve');
            return { nom: doc.nom_original, donnees: doc.donnees };
        },

        'login.php': (d, q, f, p) => connexion(d, f, p, 'patient', 'connexion-patient.html', 'dashboard.html'),
        'login-medecin.php': (d, q, f, p) => connexion(d, f, p, 'medecin', 'index-medecin.html', 'dashboard-medecin.html'),
        'register-patient.php': (d, q, f, p) => inscription(d, f, p, 'patient', 'inscription.html', 'dashboard.html'),
        'register-medecin.php': (d, q, f, p) => inscription(d, f, p, 'medecin', 'register-medecin.html', 'dashboard-medecin.html'),

        'logout.php': (d) => {
            const u = utilisateurConnecte(d);
            sessionStorage.removeItem(CLE_SESSION);
            return redirection(u && u.role === 'medecin' ? 'index-medecin.html' : 'index.html');
        }
    };

    function connexion(d, f, p, role, pageLogin, pageAccueil) {
        const email = String(f.get('email') || '').trim().toLowerCase();
        const user = d.utilisateurs.find(u => u.email.toLowerCase() === email);
        if (!user || user.hash !== p.hash) {
            return redirection(pageLogin, 'Email ou mot de passe incorrect.');
        }
        if (user.role !== role) {
            return redirection(pageLogin, role === 'medecin'
                ? 'Accès refusé : vous n\'avez pas les droits de médecin.'
                : 'Accès refusé : cet espace est réservé aux patients.');
        }
        connecter(user);
        return redirection(pageAccueil);
    }

    // Inscription sans vérification par e-mail : le compte est actif et connecté tout de suite
    function inscription(d, f, p, role, pageRetour, pageAccueil) {
        const prenom = String(f.get('prenom') || '').trim();
        const nom = String(f.get('nom') || '').trim();
        const email = String(f.get('email') || '').trim();
        const mdp = String(f.get('password') || '');
        if (!prenom || !nom || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return redirection(pageRetour, 'Merci de remplir correctement tous les champs.');
        }
        if (mdp.length < 8) return redirection(pageRetour, 'Le mot de passe doit contenir au moins 8 caractères.');
        if (d.utilisateurs.some(u => u.email.toLowerCase() === email.toLowerCase())) {
            return redirection(pageRetour, 'Cet email est déjà utilisé.');
        }
        const user = { id: nouvelId(d, 'utilisateurs'), prenom, nom, email, hash: p.hash, role,
                       specialite: role === 'medecin' ? (String(f.get('specialite') || '').trim() || 'Généraliste') : null };
        d.utilisateurs.push(user);
        connecter(user);
        return redirection(pageAccueil, 'Compte créé ! Mode démo : la vérification par e-mail est ignorée, vous êtes connecté.');
    }

    // --- REMPLACEMENT DE fetch ---
    async function repondreLocalement(nomScript, url, init) {
        const route = routes[nomScript];
        if (!route) return json({ erreur: 'script_inconnu_en_mode_demo' }, 404);
        const corps = init && init.body instanceof FormData ? init.body : new FormData();
        // Étapes asynchrones faites AVANT de lire les données : la lecture, la modification et l'écriture
        // restent ensuite synchrones, deux requêtes simultanées ne peuvent donc pas s'écraser.
        const preparation = {};
        if (corps.has('password')) preparation.hash = await sha256(String(corps.get('password')));
        const fichier = corps.get('fichier');
        if (fichier instanceof File && fichier.size <= TAILLE_MAX_STOCKEE) preparation.donneesFichier = await lireFichier(fichier);
        try {
            const donnees = lireDonnees();
            const avant = JSON.stringify(donnees);
            const resultat = route(donnees, url.searchParams, corps, preparation);
            // Écriture seulement si quelque chose a changé : l'événement storage ne part alors que pour une vraie modification
            if (JSON.stringify(donnees) !== avant) ecrireDonnees(donnees);
            return json(resultat);
        } catch (e) {
            if (e instanceof ErreurHttp) return json({ erreur: e.erreur }, e.code);
            if (e && e.name === 'QuotaExceededError') return json({ erreur: 'stockage_plein' }, 507);
            throw e;
        }
    }

    function nomScriptPhp(cible) {
        const url = new URL(cible, location.href);
        const nom = url.pathname.split('/').pop();
        return url.origin === location.origin && nom.endsWith('.php') ? { nom, url } : null;
    }

    window.fetch = function (ressource, init) {
        const adresse = ressource instanceof Request ? ressource.url : String(ressource);
        const script = nomScriptPhp(adresse);
        if (!script) return fetchOriginal(ressource, init);
        return repondreLocalement(script.nom, script.url, init);
    };

    // Formulaires et liens qui visaient un script PHP (connexion, inscription, paiement, déconnexion, téléchargement)
    function suivreReponse(reponse) {
        if (reponse.alerte) alert(reponse.alerte);
        if (reponse.redirection) location.href = reponse.redirection;
    }

    document.addEventListener('submit', (e) => {
        const script = nomScriptPhp(e.target.getAttribute('action') || '');
        if (!script) return;
        e.preventDefault();
        window.fetch(script.url.href, { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json())
            .then(suivreReponse);
    }, true);

    document.addEventListener('click', (e) => {
        const lien = e.target.closest && e.target.closest('a[href]');
        const script = lien && nomScriptPhp(lien.getAttribute('href'));
        if (!script) return;
        e.preventDefault();
        window.fetch(script.url.href).then(r => r.json()).then(reponse => {
            if (script.nom === 'telecharger_doc.php') {
                if (reponse.erreur) {
                    alert(reponse.erreur === 'fichier_non_conserve'
                        ? 'Mode démo : ce fichier dépassait 1 Mo, seul son nom a été conservé.'
                        : 'Document introuvable.');
                    return;
                }
                const a = document.createElement('a');
                a.href = reponse.donnees;
                a.download = reponse.nom;
                a.click();
                return;
            }
            suivreReponse(reponse);
        });
    }, true);

    // --- SYNCHRONISATION ENTRE ONGLETS (événement storage) ---
    window.addEventListener('storage', (e) => {
        if (e.key !== CLE_DONNEES && e.key !== null) return;
        if (e.newValue === null) {
            // La démo a été réinitialisée dans un autre onglet
            sessionStorage.removeItem(CLE_SESSION);
            location.href = 'index.html';
            return;
        }
        window.dispatchEvent(new CustomEvent('demo-maj'));
    });

    // --- BANDEAU ET RÉINITIALISATION ---
    function reinitialiser() {
        if (!confirm('Réinitialiser la démo ? Toutes les données fictives de ce navigateur seront effacées.')) return;
        Object.keys(localStorage).filter(k => k.startsWith('santeconnect-demo:')).forEach(k => localStorage.removeItem(k));
        sessionStorage.removeItem(CLE_SESSION);
        location.href = 'index.html';
    }

    function afficherBandeau() {
        const bandeau = document.createElement('div');
        bandeau.id = 'bandeau-demo';
        bandeau.setAttribute('role', 'status');
        bandeau.style.cssText = 'background:#fff3cd;color:#664d03;border-bottom:1px solid #ffe69c;padding:6px 16px;font-size:0.85rem;'
            + 'display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;flex-shrink:0;position:relative;z-index:1060;font-family:system-ui,sans-serif;';
        const texte = document.createElement('strong');
        texte.textContent = 'Mode démo – données fictives, aucun serveur, rien n\'est envoyé';
        const accueil = document.createElement('a');
        accueil.href = 'index.html';
        accueil.textContent = 'Accueil de la démo';
        accueil.style.cssText = 'color:#664d03;';
        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.textContent = 'Réinitialiser la démo';
        bouton.style.cssText = 'background:#fff;border:1px solid #997404;color:#664d03;border-radius:4px;padding:1px 10px;font-size:0.8rem;cursor:pointer;';
        bouton.addEventListener('click', reinitialiser);
        bandeau.append(texte, accueil, bouton);
        document.body.prepend(bandeau);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', afficherBandeau);
    else afficherBandeau();

    // --- API utilisée par les pages de la démo ---
    window.DemoAPI = {
        // Boutons de l'accueil : connexion directe avec un compte fictif
        entrerComme(role) {
            const donnees = lireDonnees();
            const user = donnees.utilisateurs.find(u => u.id === (role === 'medecin' ? 1 : 2));
            connecter(user);
            location.href = role === 'medecin' ? 'dashboard-medecin.html' : 'dashboard.html';
        },
        utilisateur() { return utilisateurConnecte(lireDonnees()); },
        reinitialiser
    };
})();
