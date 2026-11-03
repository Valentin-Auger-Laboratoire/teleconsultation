# SantéConnect

> ⚠️ **Prototype pédagogique – non conforme HDS, ne pas utiliser avec de vraies données de santé.**

[Français](#français) · [English](#english)

**▶ Démo en ligne (sans serveur) : https://valentin-auger-laboratoire.github.io/teleconsultation/**

---

## Français

SantéConnect est une application web de téléconsultation réalisée en PHP et MySQL : un patient prend rendez-vous sur un créneau publié par un médecin, puis les deux échangent en visio et par chat. Le médecin rédige une ordonnance et émet une facture, que le patient règle (paiement simulé).

### Fonctionnalités

- **Deux espaces séparés**, patient et médecin, avec inscription, connexion et contrôle du rôle.
- **Vérification du compte par e-mail** : un code à 6 chiffres valable 15 minutes, avec possibilité d'en demander un nouveau.
- **Rendez-vous** : le médecin publie ses créneaux, le patient en réserve un (le créneau devient alors indisponible) et le médecin confirme.
- **Consultation en visio** en pair-à-pair (PeerJS / WebRTC), avec coupure du micro et de la caméra.
- **Chat en direct** propre à chaque consultation.
- **Documents** : le patient envoie des PDF, JPG ou PNG ; le médecin les télécharge.
- **Ordonnance numérique** : enregistrée pour le patient de la consultation, affichée dans son espace et envoyée par mail.
- **Facturation** : le médecin saisit un montant, le patient le voit en direct puis effectue un **paiement simulé**.

### Démo en ligne (mode démo statique)

Une version statique est publiée sur GitHub Pages depuis le dossier `docs/` : **https://valentin-auger-laboratoire.github.io/teleconsultation/**. Elle ne modifie en rien le fonctionnement PHP normal.

- Sur la page d'accueil, **Entrer comme patient (démo)** ou **Entrer comme médecin (démo)** connecte directement un compte fictif. Ouvrez un 2e onglet avec l'autre rôle pour voir le chat, la facture et la visio en direct.
- `docs/` contient une copie des pages front (HTML, CSS, JS) et `demo-api.js`, qui remplace tous les appels aux scripts PHP par des réponses simulées. Les données fictives (médecins, créneaux, rendez-vous, messages, factures, documents) sont stockées dans le `localStorage` du navigateur ; l'utilisateur connecté est propre à chaque onglet (`sessionStorage`). **Aucun fichier PHP, aucune configuration ni aucun secret** dans `docs/`.
- Les onglets se synchronisent avec l'événement `storage` : rendez-vous confirmé, messages du chat, facture et statut de paiement apparaissent tout de suite dans l'autre onglet.
- La visio utilise PeerJS et son serveur cloud public, avec des identifiants aléatoires partagés entre les onglets via le `localStorage`. Si la caméra est refusée ou absente, un message l'explique et l'appel reste possible (vous voyez l'autre participant s'il a une caméra).
- L'e-mail d'ordonnance s'affiche dans une fenêtre d'aperçu (rien n'est envoyé), l'inscription saute la vérification par e-mail et le paiement reste simulé (aucun champ carte).
- Un bandeau rappelle sur chaque page « Mode démo – données fictives, aucun serveur, rien n'est envoyé », avec un bouton **Réinitialiser la démo** qui efface les données de la démo.
- Les fichiers déposés de plus de 1 Mo sont acceptés, mais seul leur nom est conservé (le `localStorage` est limité à quelques Mo).

Pour la publier : *Settings → Pages → Deploy from a branch*, branche `main`, dossier `/docs`. Pour l'essayer en local, servez `docs/` avec n'importe quel serveur statique (par exemple `python -m http.server`), puis ouvrez `http://localhost:8000/`.

### Architecture

```
Navigateur (HTML + Bootstrap 5 + JavaScript fetch)
   │  AJAX (JSON)                     │  WebRTC (PeerJS, STUN Google)
   ▼                                  ▼
Scripts PHP (un fichier par action)   Visio pair-à-pair patient ⇄ médecin
   │  config.php : session + connexion PDO partagée + fonctions.php
   ▼
MySQL / MariaDB (database.sql)
```

| Fichier(s) | Rôle |
|---|---|
| `config.php`, `config.local.php` | Configuration centrale : lit `config.local.php` (non versionné), ouvre la session et l'unique connexion PDO |
| `fonctions.php` | Échappement, réponses JSON, contrôle de session et de rôle, accès aux consultations, inscription et connexion |
| `envoyer_mail.php` | Envoi des mails (code de vérification, ordonnance) avec PHPMailer |
| `index.html`, `inscription.html`, `login.php`, `register-patient.php` | Espace patient : connexion et inscription |
| `index-medecin.html`, `register-medecin.html`, `login-medecin.php`, `register-medecin.php` | Espace médecin : connexion et inscription |
| `verification.php`, `valider_compte.php` | Activation du compte par code |
| `dashboard.html` / `dashboard-medecin.html` | Tableaux de bord patient et médecin |
| `consultation.php` / `interface-medecin.php` | Salle de consultation (visio, chat, documents, facture) |
| `paiement.php`, `valider_paiement.php` | Liste des factures et paiement simulé |
| `get_*.php`, `save_*.php`, `confirm_rdv.php`, `delete_creneau.php`, `envoyer_message.php`, `recuperer_messages.php`, `sauvegarder-facture.php`, `sauvegarder_doc.php`, `upload_doc.php`, `telecharger_doc.php`, `effacer_messages.php`, `moi.php`, `logout.php` | Points d'accès AJAX, chacun protégé par session et rôle |
| `uploads/` | Fichiers envoyés : noms aléatoires, accès direct et exécution interdits (`.htaccess`) |
| `database.sql` | Structure de la base et comptes de démonstration |

**Modèle de données** : `utilisateurs`, `creneaux`, `rendez_vous` (un rendez-vous confirmé correspond aussi à la consultation, avec son montant et son `statut_paiement`), `messages` et `documents`.

### Installation (XAMPP)

1. Installez [XAMPP](https://www.apachefriends.org/) (PHP 8.1 ou plus récent), puis démarrez **Apache** et **MySQL**.
2. Copiez le dossier du projet dans `C:\xampp\htdocs\santeconnect`.
3. Dans phpMyAdmin (`http://localhost/phpmyadmin`), créez une base **vide** nommée `santeconnect` (interclassement `utf8mb4_unicode_ci`), puis importez `database.sql` dans l'onglet **Importer**.
4. Copiez `config.example.php` en `config.local.php` et complétez-le (voir ci-dessous).
5. Ouvrez `http://localhost/santeconnect/`.

> La visio a besoin de la caméra, que les navigateurs n'autorisent qu'en HTTPS ou sur `localhost`. Pour une démonstration à distance, passez par un tunnel HTTPS (par exemple ngrok, à installer séparément).

### Configuration

Toute la configuration est dans `config.local.php`. Ce fichier est **exclu du dépôt** par `.gitignore` ; partez de `config.example.php` :

```php
return [
    'db'   => ['host' => 'localhost', 'name' => 'santeconnect', 'user' => 'root', 'pass' => ''],
    'smtp' => [
        'host' => 'smtp.gmail.com', 'port' => 587,
        'user' => 'ton.email@exemple.com', 'pass' => 'MOT_DE_PASSE_APPLICATION',
        'from' => 'ton.email@exemple.com', 'from_name' => 'SantéConnect',
    ],
    'mode_demo' => false,
];
```

- **SMTP** : avec Gmail, utilisez un [mot de passe d'application](https://support.google.com/accounts/answer/185833), jamais le mot de passe du compte.
- **`mode_demo`** : mis à `true`, il affiche le code de vérification sur la page si l'envoi du mail échoue. C'est pratique en local sans SMTP, mais **à ne jamais activer en ligne**.

### Comptes de démonstration

Ces comptes fictifs sont créés par `database.sql` et déjà vérifiés :

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Médecin (Dr. Claire Martin, généraliste) | `medecin.demo@exemple.com` | `Medecin-Demo-2026` |
| Patient (Lucas Bernard) | `patient.demo@exemple.com` | `Patient-Demo-2026` |

Quatre créneaux libres sont créés sur les jours qui suivent l'import.

**Parcours de démonstration** :
1. Connectez-vous en patient et réservez un créneau.
2. Dans un autre navigateur, connectez-vous en médecin et cliquez sur **Valider**, puis sur **Lancer la Visio**.
3. Côté patient, cliquez sur **Rejoindre**, puis sur **Lancer l'appel**.
4. Échangez par chat, facturez, rédigez une ordonnance, puis payez (paiement simulé).

### Sécurité mise en place

- Mots de passe hachés avec `password_hash()` et vérifiés avec `password_verify()` (8 caractères minimum).
- Code de vérification généré avec `random_int()`, valable 15 minutes, comparé avec `hash_equals()` ; il est invalidé après 5 essais. La connexion est refusée tant que le compte n'est pas vérifié.
- Session et rôle vérifiés dans **chaque** script appelé en AJAX : 401 si l'utilisateur n'est pas connecté, 403 si le rôle ne convient pas. `session_regenerate_id()` est appelé à la connexion, et les cookies sont `HttpOnly` et `SameSite=Lax`.
- **Cloisonnement** : chacun n'accède qu'à SES consultations, messages, documents et factures (requêtes filtrées sur le patient ou le médecin de la consultation, 404 sinon).
- Les actions qui modifient des données n'acceptent que POST.
- Requêtes SQL préparées (PDO, sans émulation) et une seule connexion partagée.
- **Upload** : PDF, JPG ou PNG uniquement (type vérifié sur le contenu avec `finfo`), 5 Mo maximum, nom de stockage aléatoire. Le dossier `uploads/` interdit l'accès direct et l'exécution, et le téléchargement passe par `telecharger_doc.php`, qui contrôle les droits.
- **Échappement** : `htmlspecialchars()` côté PHP (pages et mails) ; côté JavaScript, les données sont insérées avec `textContent` ou `createElement`, jamais avec `innerHTML`.
- SMTP en STARTTLS avec vérification du certificat SSL.
- Identifiants PeerJS propres à chaque consultation : id du rendez-vous et clé aléatoire de 128 bits.
- Aucun secret dans le dépôt : `config.local.php` est ignoré par git.

### Limites connues

- **Prototype pédagogique, non conforme HDS ni RGPD** : pas d'hébergement certifié, pas de chiffrement des données au repos, pas de journal d'accès.
- La visio repose sur le serveur public PeerJS et sur un serveur STUN, sans serveur TURN : elle peut échouer derrière certains pare-feu ou réseaux d'entreprise.
- Le chat et le suivi de facture fonctionnent par interrogation régulière du serveur (toutes les 2 à 5 s), pas par WebSocket.
- Le paiement est **simulé** : aucun prestataire de paiement n'est branché.
- Le bouton « Planifier Suivi » est une simulation : rien n'est enregistré.
- Pas de jeton CSRF dédié (la protection repose sur les cookies `SameSite=Lax` et l'usage de POST), pas de limitation du nombre de tentatives de connexion, pas de réinitialisation du mot de passe.
- Pas d'annulation de rendez-vous ni de modification du profil.
- En fin de consultation, le médecin efface le chat ; la facture est conservée.

### Crédits et licences

- [PHPMailer](https://github.com/PHPMailer/PHPMailer) 7.0.2, sous licence **LGPL 2.1** (voir `PHPMailer/LICENSE`).
- [Bootstrap](https://getbootstrap.com/) 5.3 et Bootstrap Icons (MIT), [PeerJS](https://peerjs.com/) 1.5 (MIT), chargés depuis un CDN.

---

## English

> ⚠️ **Educational prototype – not HDS-compliant (French health data hosting standard). Do not use with real health data.**

**▶ Live demo (no server): https://valentin-auger-laboratoire.github.io/teleconsultation/**

SantéConnect is a PHP/MySQL telemedicine web app. A patient books one of the time slots a doctor has published, then both meet by video and chat. The doctor writes a prescription and issues an invoice, which the patient pays (simulated payment). The user interface is in French.

### Features

- **Separate patient and doctor areas**, with sign-up, login and role checks.
- **E-mail account verification**: a 6-digit code valid for 15 minutes, which can be resent.
- **Appointments**: the doctor publishes time slots, the patient books one (the slot then becomes unavailable) and the doctor confirms it.
- **Peer-to-peer video consultation** (PeerJS / WebRTC), with mic and camera toggles.
- **Live chat** scoped to each consultation.
- **Documents**: the patient uploads PDF, JPG or PNG files; the doctor downloads them.
- **Digital prescription**: saved for the patient of that consultation, shown in their area and sent by e-mail.
- **Billing**: the doctor enters an amount, the patient sees it live and makes a **simulated payment**.

### Live demo (static demo mode)

A static version is published on GitHub Pages from the `docs/` folder: **https://valentin-auger-laboratoire.github.io/teleconsultation/**. The regular PHP application is unchanged.

- On the home page, **Entrer comme patient (démo)** or **Entrer comme médecin (démo)** logs you straight into a fictitious account. Open a second tab with the other role to see the chat, the invoice and the video call live.
- `docs/` holds a copy of the front-end pages (HTML, CSS, JS) plus `demo-api.js`, which replaces every call to the PHP scripts with simulated responses. Fictitious data (doctors, slots, appointments, messages, invoices, documents) lives in the browser's `localStorage`; the logged-in user is per tab (`sessionStorage`). **No PHP file, configuration or secret** is in `docs/`.
- Tabs stay in sync through the `storage` event: confirmed appointments, chat messages, the invoice and the payment status show up instantly in the other tab.
- Video uses PeerJS and its public cloud server, with random IDs shared between tabs through `localStorage`. If the camera is denied or missing, a clear message is shown and the call still connects (you see the other participant if they have a camera).
- The prescription e-mail is shown in a preview window (nothing is sent), sign-up skips e-mail verification and payment stays simulated (no card fields).
- Every page shows a "Mode démo – données fictives, aucun serveur, rien n'est envoyé" banner with a **Réinitialiser la démo** (reset) button that clears the demo data.
- Uploaded files over 1 MB are accepted, but only their name is kept (`localStorage` is limited to a few MB).

To publish it: *Settings → Pages → Deploy from a branch*, branch `main`, folder `/docs`. To try it locally, serve `docs/` with any static server (e.g. `python -m http.server`) and open `http://localhost:8000/`.

### Architecture

```
Browser (HTML + Bootstrap 5 + JavaScript fetch)
   │  AJAX (JSON)                     │  WebRTC (PeerJS, Google STUN)
   ▼                                  ▼
PHP scripts (one file per action)     Peer-to-peer video patient ⇄ doctor
   │  config.php: session + shared PDO connection + fonctions.php
   ▼
MySQL / MariaDB (database.sql)
```

- `config.php` reads `config.local.php` (not versioned), starts the session and opens the single PDO connection.
- `fonctions.php` holds the shared helpers: escaping, JSON responses, session and role checks, consultation access control, sign-up and login.
- `dashboard.html` / `dashboard-medecin.html` are the patient and doctor dashboards. `consultation.php` / `interface-medecin.php` are the consultation rooms.
- Each `*.php` endpoint called by AJAX checks the session, the role and ownership of the consultation.
- **Data model**: `utilisateurs` (users), `creneaux` (slots), `rendez_vous` (appointments; a confirmed appointment is also the consultation and holds the amount and `statut_paiement`), `messages` and `documents`.

### Installation (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) (PHP 8.1+), then start **Apache** and **MySQL**.
2. Copy the project folder to `C:\xampp\htdocs\santeconnect`.
3. In phpMyAdmin, create an **empty** database named `santeconnect` (`utf8mb4_unicode_ci`), then import `database.sql`.
4. Copy `config.example.php` to `config.local.php` and fill it in.
5. Open `http://localhost/santeconnect/`.

> Video needs camera access, which browsers only allow over HTTPS or on `localhost`. For a remote demo, use an HTTPS tunnel (e.g. ngrok, installed separately).

### Configuration

All settings live in `config.local.php`, which is **ignored by git**. Start from `config.example.php`: database credentials, SMTP settings (with Gmail, use an [app password](https://support.google.com/accounts/answer/185833)), and `mode_demo`. When `mode_demo` is `true`, the verification code is shown on screen if the e-mail cannot be sent; use it for local demos only, **never in production**.

### Demo accounts

These fictitious accounts are created by `database.sql` and already verified:

| Role | E-mail | Password |
|---|---|---|
| Doctor (Dr. Claire Martin, GP) | `medecin.demo@exemple.com` | `Medecin-Demo-2026` |
| Patient (Lucas Bernard) | `patient.demo@exemple.com` | `Patient-Demo-2026` |

**Demo walkthrough**:
1. Log in as the patient and book a slot.
2. In another browser, log in as the doctor and click **Valider** (confirm), then **Lancer la Visio**.
3. On the patient side, click **Rejoindre**, then **Lancer l'appel**.
4. Chat, invoice, write a prescription, then pay (simulated payment).

### Security measures

- Passwords hashed with `password_hash()` / `password_verify()` (8 characters minimum).
- Verification code generated with `random_int()`, valid for 15 minutes, compared with `hash_equals()` and invalidated after 5 attempts. Login is refused until the account is verified.
- Session and role checked in **every** AJAX endpoint (401 if not logged in, 403 if the role is wrong). `session_regenerate_id()` on login; `HttpOnly` and `SameSite=Lax` cookies.
- **Isolation**: users can only access THEIR OWN consultations, messages, documents and invoices (404 otherwise).
- Endpoints that change data accept POST only.
- Prepared statements (PDO, no emulation) over a single shared connection.
- **Uploads**: PDF, JPG or PNG only (type checked on the file content with `finfo`), 5 MB max, random storage name. `uploads/` blocks direct access and script execution, and downloads go through `telecharger_doc.php`, which checks permissions.
- **Output escaping**: `htmlspecialchars()` in PHP (pages and e-mails); in JavaScript, data is inserted with `textContent` / `createElement`, never with `innerHTML`.
- SMTP over STARTTLS with SSL certificate verification.
- PeerJS IDs unique to each consultation: appointment id plus a random 128-bit key.
- No secrets in the repository: `config.local.php` is git-ignored.

### Known limitations

- **Educational prototype, not HDS- or GDPR-compliant**: no certified hosting, no encryption at rest, no access logs.
- Video relies on the public PeerJS server and a STUN server, with no TURN server, so it may fail behind strict firewalls.
- Chat and billing updates rely on polling every 2 to 5 s, not on WebSockets.
- Payment is **simulated**: no payment provider is connected.
- The "Planifier Suivi" (follow-up) button is a mock-up: nothing is saved.
- No dedicated CSRF token (protection relies on `SameSite=Lax` cookies and POST-only actions), no login rate limiting, no password reset.
- No appointment cancellation or profile editing.
- When the doctor ends the consultation, the chat is deleted; the invoice is kept.

### Credits and licences

- [PHPMailer](https://github.com/PHPMailer/PHPMailer) 7.0.2, licensed under **LGPL 2.1** (see `PHPMailer/LICENSE`).
- [Bootstrap](https://getbootstrap.com/) 5.3 and Bootstrap Icons (MIT), [PeerJS](https://peerjs.com/) 1.5 (MIT), loaded from a CDN.
