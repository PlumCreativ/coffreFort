# Sujet & Cahier des charges — Réalisation Professionnelle

> **Base pédagogique** : prolonge le TP « Mini coffre‑fort REST (Slim + Medoo) » ([repo de base : MVC-API-REST](https://github.com/AstrowareConception/MVC-API-REST)) en projet complet multi‑équipes. On passe d’un **CRUD fichiers** à un **coffre‑fort numérique** avec comptes, sécurité, partages et double client (JavaFX + Web).

---

## 1) Vision & objectifs

* Concevoir et livrer un **coffre‑fort numérique sécurisé** permettant à un utilisateur de **déposer, organiser, partager** et **suivre** des fichiers.
* Industrialiser côté back‑end (Slim + Medoo), définir un **contrat d’API** partagé, développer un **client JavaFX** (dépôt/gestion) et un **client Web** (consultation/téléchargement via liens partagés).
* Apprendre à travailler **en parallèle** (équipes Back / JavaFX / Web / Ops), à synchroniser via **OpenAPI**, **GitHub Projects**, **branches/PR** et **revues de code**.

**Livrable attendu en fin de projet** : MVP fonctionnel + documentation + tests + démo.

---

## 2) Périmètre fonctionnel

### 2.1 Comptes & sécurité

* **Création de compte** et **authentification** par **JWT** (sessions stateless).
* **Rôles** : Utilisateur (propriétaire de coffre), Admin (gestion quotas, supervision logs).
* **Politique de mots de passe** + hachage **Argon2id**.

### 2.2 Stockage sécurisé de fichiers

* **Chiffrement au repos** obligatoire.

  * Recommandation par défaut : **AES‑256‑GCM** (symétrique) pour le contenu des fichiers.
  * **Enveloppe de clés** (asymétrique) : la clé de fichier (aléatoire) est chiffrée par une **clé publique serveur** (RSA‑OAEP ou X25519 + libsodium). Stockage séparé des secrets.
  * Les étudiants peuvent proposer une variante (tout symétrique + KMS applicatif) **à condition de justifier** la décision (fiche de veille).
* **Organisation** : dossiers hiérarchiques (+ tags optionnels).
* **Versionnage** : remplacer un fichier crée **une nouvelle version** et **préserve les liens** existants (qui pointent par défaut vers la **dernière version** — option pour figer une version).

### 2.3 Partage & diffusion

* **Liens de partage** pour **fichiers** ou **dossiers** (URL signée) :

  * **Durée de vie** (expiration horodatée) **ou** **nombre d’utilisations** (décrémenté à chaque téléchargement).
  * **Révocation** immédiate par le propriétaire.
  * Page publique minimaliste (client Web) pour le destinataire : voir la liste et **télécharger**.
* **Journalisation** : ouverture/téléchargement (date/heure, IP, UA, succès/échec).

### 2.4 Quotas & limites

* **Quota d’espace** par utilisateur (valeur par défaut configurable), alertes à 80%/100%.
* Taille maximale de fichier (paramétrable).

### 2.5 Clients

* **Client JavaFX (client lourd)** : authentification, explorer dossiers/fichiers, **upload**, **renommer/déplacer**, **supprimer**, **créer des liens** et **mettre à jour** (nouvelle version) un fichier. Indicateurs de quota et progression d’upload.
* **Client Web (client léger)** :

  * **Public** (destinataires) : page de téléchargement via lien sécurisé.
  * **Privé (connecté)** : tableau de bord minimal (mes partages, compteur d’usages, révocation).

---

## 3) Exigences non fonctionnelles

* **HTTPS** partout, CORS maîtrisé.
* **Logs structurés** (JSON), traçabilité des actions sensibles.
* **Sauvegardes** BDD + fichiers et **test de restauration** (procédure écrite).
* **Perf** : upload multi‑part, streaming download ; seuils paramétrables (ex. ≥ 200 Mo).
* **RGPD** : mentions, export/suppression de compte (au moins conçu).

---

## 4) Architecture technique

* **Back‑end** : PHP 8.2+, **Slim** (MVC, middlewares), **Medoo**, MariaDB/MySQL ou PostgreSQL, **libsodium/OpenSSL**, JWT.
* **Web** : HTML/CSS/JS (Bootstrap ok). Pas d’auth complexe côté public.
* **JavaFX** : client lourd (jlink/jpackage), appels REST + upload avec barre de progression.
* **Déploiement** : nginx/Apache + PHP‑FPM, variables **.env**, Docker **recommandé** (compose : api + db + reverse proxy).

---

## 5) Modèle de données (proposition)

* **users**(id, email, pass_hash, quota_total, quota_used, is_admin, created_at)
* **folders**(id, user_id, parent_id, name, created_at)
* **files**(id, user_id, folder_id, original_name, mime, size, created_at)
* **file_versions**(id, file_id, version, stored_name, iv, auth_tag, key_envelope, checksum, created_at)
* **shares**(id, user_id, kind: 'file'|'folder', target_id, label, expires_at, max_uses, remaining_uses, is_revoked, created_at)
* **downloads_log**(id, share_id, version_id, downloaded_at, ip, user_agent, success)

> **Remarque** : `file_versions` permet de conserver l’historique et d’orienter un lien vers la dernière version.

---

## 6) Contrat d’API (brouillon à finaliser **Jour 1**)

> Doc Swagger interactive (en ligne) : https://editor.swagger.io/?url=https://raw.githubusercontent.com/AstrowareConception/Coffre-fort-numerique/refs/heads/main/openapi.yaml

**Auth**

* `POST /auth/register` {email,password} → 201
* `POST /auth/login` {email,password} → 200 {jwt}

**Dossiers & fichiers**

* `GET /folders` / `POST /folders` / `DELETE /folders/{id}`
* `GET /files?folder={id}`
* `POST /files` (multipart) → crée **version 1** (chiffrée)
* `POST /files/{id}/versions` (multipart) → **nouvelle version**
* `GET /files/{id}` (métadonnées + version courante)
* `DELETE /files/{id}` (supprime logique ou totale)
* `GET /files/{id}/download` (auth)

**Partages**

* `POST /shares` {kind,target_id,expires_at|max_uses,label}
* `GET /shares` (listes + stats)
* `POST /shares/{id}/revoke`
* **Public** : `GET /s/{token}` (infos) · `POST /s/{token}/download`

**Quotas & stats**

* `GET /me/quota` — utilisé / total / %
* `GET /me/activity` — derniers événements

> **Convention** : réponses **JSON**, erreurs normalisées `{error, code}` ; statuts : 200/201/204/400/401/403/404/409/413/422/429/500.

---

## 7) Sécurité détaillée

* **Chiffrement** : AES‑256‑GCM pour le contenu ; **clé par version** ; `key_envelope` = clé de version chiffrée par la clé publique serveur (RSA‑OAEP ou X25519 + sealed box). IV aléatoire, tag d’authentification stocké.
* **JWT** : durée courte (ex. 15 min) + **refresh token** (option) ; stockage côté JavaFX WebView : sécurisé.
* **Liens** : token signé (HMAC SHA‑256) + champs `exp` / `remaining_uses`.
* **Rate‑limit** basique et **headers de sécurité**.

---

## 8) Tests & qualité

* **Unitaires** : services (crypto wrapper, quotas, DAO Medoo).
* **Intégration** : routes (auth, upload, share/download, versions).
* **E2E** : collection Postman/Newman ; script de **jeu d’essai**.
* **Definition of Done** : tests verts, linter, doc mise à jour, revue de code OK.

---

## 9) Organisation de projet

* **Rôles** :

  * *Back‑end* (API, sécurité, BDD, packaging),
  * *JavaFX* (UX dépôt/gestion),
  * *Web* (pages partage & tableau de bord),
  * *Ops/Qualité* (Docker, CI, sauvegardes, Postman, documentation).
* **GitHub** : mono‑repo conseillé (api/, clients/javafx/, clients/web/). Branching : `main`, `dev`, feature branches ; **PR + review** obligatoires.
* **OpenAPI** source‑de‑vérité** (yaml) : générée **Jour 1** ; *mocks* via JSON Server/Prism (option).
* **Daily** 10 min ; **board** Kanban (ToDo / In Prog / Review / Done).

---

## 10) Planning (7 jours — Lundi→Vendredi + 2 jours)

> **Principe** : définir le contrat d’API en **Jour 1** pour débloquer le travail en parallèle.

### Jour 1 — Cadrage & contrat

* Kick‑off, risques, répartition des rôles.
* Schéma BDD + **OpenAPI v1** (endpoints ci‑dessus) + conventions d’erreurs.
* Squelette Slim + Medoo, middlewares (CORS, JSON, erreurs), migrations initiales.
* Setup repo GitHub (issues/labels), actions CI (lint + tests), .env.example.

### Jour 2 — Fichiers & dossiers (Back) / Shells clients

* Back : CRUD dossiers, upload **chiffré** v1, quotas, téléchargement.
* JavaFX : scaffolding, login écran, liste dossiers/fichiers (mock si besoin).
* Web : page publique `/s/{token}` (maquette) + intégration Bootstrap.

### Jour 3 — Partages & journalisation

* Back : création `shares`, tokens, expiration/uses, logs téléchargements.
* JavaFX : création de liens depuis la vue fichier/dossier ; affichage « mes partages ».
* Web : flux public `download` opérationnel.

### Jour 4 — Versions de fichiers

* Back : endpoint **nouvelle version** + règle « liens → dernière version ».
* JavaFX : *remplacer fichier* (progress bar) + métadonnées version.
* Web : afficher si une ressource a plusieurs versions (simple).

### Jour 5 — Finitions MVP & sécurité

* Back : passes sécurité (headers, rate‑limit simple), pagination, 404/413…
* Clients : UX minimale, messages d’erreur, indicateurs de quota.
* Tests Postman/Newman + README usage.

### Jour 6 — Stabilisation & doc

* Corrections, couverture de tests, script de jeu d’essai.
* Doc : OpenAPI finalisée, guide d’installation, sauvegarde/restauration, schémas.
* Démo interne : scénario de bout en bout.

### Jour 7 — Démo & soutenance

* Démo fil rouge (création compte → upload → lien → téléchargement → mise à jour version → suivi usages).
* Revue de code croisée, bilan d’équipe, dettes techniques & pistes (2FA, monitoring…).

---

## 11) Critères d’acceptation (extraits)

* **Upload chiffré** : hash du déchiffrement == hash original (test de preuve).
* **Lien expiré** : renvoie 410/403 à l’instant prévu ; **révocation** immédiate.
* **Versionnage** : un lien existant télécharge la **dernière version** => compteur usages OK.
* **Quota** : dépassement refusé avec message clair ; tableau de bord met à jour l’utilisation.
* **JavaFX** : upload avec barre de progression ; création de lien ; remplacement (nouvelle version).
* **Web** : téléchargement public via token ; affichage simple des métadonnées.

---

## 12) Livrables

* **Dépôt GitHub** (mono‑repo conseillé) avec README, **OpenAPI.yaml**, scripts SQL/migrations, collection Postman, docker‑compose (optionnel), captures démo.
* **Documentation** :

  * technique (archi, sécurité, modèle, choix crypto justifiés),
  * utilisateur (chemin critique),
  * exploitation (sauvegarde/restauration).
* **Jeu d’essai** reproductible.

---

## 13) Backlog améliorations (post‑MVP)

* 2FA TOTP, limites de débit, liste blanche IP.
* Prévisualisation, notifications email, statistiques détaillées.
* Rôles avancés/partage collaboratif, dossiers partagés, webhooks.
* Monitoring, PRA, rotation des clés, KMS.

---

## 14) Points à trancher en équipe (décisions documentées)

* RSA‑2048 vs ECC/X25519 pour l’enveloppe de clés.
* Suppression logique vs physique des fichiers.
* Rétention des logs (durée, anonymisation).
* Pagination et tailles limites par défaut.

---

