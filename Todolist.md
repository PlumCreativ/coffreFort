# To-Do List — Projet Coffre-fort Numérique (GitHub Projects)

## Épic 1 — Cadrage & environnement

### Issue : “Setup repo & CI de base”

**Description :**

* Initialiser le repo GitHub (`main` + `dev`)
* Ajouter `.gitignore`, `README.md`, `LICENSE`
* Configurer GitHub Actions basique (PHPUnit ou vérifications Composer)
  **Labels :** `epic`, `setup`, `backend`

### Issue : “Définir et valider le contrat d’API (OpenAPI)”

**Description :**

* Importer `openapi.yaml`
* Compléter schémas/réponses d’erreur/exemples
* Faire valider par équipes JavaFX & Web
  **Labels :** `api`, `spec`, `frontend`, `backend`

---

## Épic 2 — Backend core (Slim + Medoo)

### Issue : “Squelette Slim + Medoo + config .env”

**Description :**

* Créer `public/index.php`, config AppFactory, JSON/CORS
* Configurer Medoo avec `.env`
* Route `/health`
  **Labels :** `backend`, `Slim`, `Medoo`

### Issue : “Schéma BDD initial & migrations”

**Description :**

* Tables : `users`, `folders`, `files`, `file_versions`, `shares`, `downloads_log`
* Script SQL/migrations
  **Labels :** `backend`, `database`

### Issue : “Auth JWT (register + login)”

**Description :**

* Implémenter `/auth/register`, `/auth/login`
* Hachage Argon2id, validation email, génération JWT
  **Labels :** `auth`, `backend`, `security`

### Issue : “Gestion des dossiers (CRUD minimal)”

**Description :**

* Implémenter `GET/POST/DELETE /folders`
* Dossiers liés à l’utilisateur dans JWT
  **Labels :** `backend`, `folders`

### Issue : “Upload chiffré & version 1 des fichiers”

**Description :**

* Endpoint `POST /files`
* Chiffrement AES-256-GCM + enveloppe de clé
* Insertion (files + file_versions)
  **Labels :** `backend`, `security`, `files`

### Issue : “Téléchargement et suppression de fichiers”

**Description :**

* Implémenter `GET /files/{id}`, `GET /files/{id}/download`, `DELETE /files/{id}`
* Vérifier droits
  **Labels :** `backend`, `files`

### Issue : “Nouvelle version de fichier”

**Description :**

* Endpoint `POST /files/{id}/versions`
* Mise à jour de la version courante
  **Labels :** `backend`, `files`, `versions`

---

## Épic 3 — Partages & liens publics

### Issue : “Création et listing des partages”

**Description :**

* Endpoint `POST /shares`, `GET /shares`
* Support `kind=file|folder`, `expires_at`, `max_uses`
  **Labels :** `backend`, `shares`

### Issue : “Révocation & règles d’expiration / max_uses”

**Description :**

* Endpoint `POST /shares/{id}/revoke`
* Vérifier expiration + décrément `remaining_uses`
  **Labels :** `backend`, `shares`, `security`

### Issue : “Lien public /s/{token} + /s/{token}/download”

**Description :**

* Endpoints publics (infos + téléchargement)
* Statuts 404/410 + journalisation
  **Labels :** `backend`, `shares`, `public`

---

## Épic 4 — Quotas, stats & sécurité

### Issue : “Quotas & endpoint /me/quota”

**Description :**

* Calculer `used_bytes`
* Bloquer upload en dépassement
* Endpoint `GET /me/quota`
  **Labels :** `backend`, `quotas`

### Issue : “Journalisation des téléchargements”

**Description :**

* Enregistrer IP, user-agent, date, succès/échec
  **Labels :** `backend`, `logs`

### Issue : “Petite hardening sécurité (headers, rate-limit)”

**Description :**

* Ajouter headers de sécurité
* Rate-limit bruteforce simple
  **Labels :** `backend`, `security`

---

## Épic 5 — Client JavaFX

### Issue : “Squelette appli JavaFX + écran de login”

**Description :**

* Projet Maven/Gradle
* Login → `/auth/login`, stockage JWT
  **Labels :** `frontend`, `javafx`

### Issue : “Explorer dossiers/fichiers”

**Description :**

* TreeView/ListView
* Consommer `GET /folders`, `GET /files`
  **Labels :** `javafx`, `ui`

### Issue : “Upload avec barre de progression”

**Description :**

* Formulaire upload
* ProgressBar pour `POST /files`
  **Labels :** `javafx`, `files`

### Issue : “Remplacement de fichier (nouvelle version)”

**Description :**

* Bouton “Remplacer” → `POST /files/{id}/versions`
* Affichage version courante
  **Labels :** `javafx`, `versions`

### Issue : “Gestion des liens de partage dans JavaFX”

**Description :**

* UI création de lien
* Vue « Mes partages » + bouton Révoquer
  **Labels :** `javafx`, `shares`

---

## Épic 6 — Client Web (partage & téléchargement)

### Issue : “Page publique de téléchargement /s/{token}”

**Description :**

* Page HTML/CSS Bootstrap
* Consomme `GET /s/{token}` + bouton download
  **Labels :** `web`, `frontend`, `shares`

### Issue : “Mini tableau de bord utilisateur Web”

**Description :**

* (Optionnel) Login simple
* Vue “Mes partages” avec stats
  **Labels :** `web`, `frontend`

---

## Épic 7 — Qualité, doc & démo

### Issue : “Collection Postman + tests E2E”

**Description :**

* Collection couvrant auth, upload, partage
* Documentation jeux de tests
  **Labels :** `tests`, `backend`

### Issue : “Doc technique & guide d’installation”

**Description :**

* Archi, BDD, endpoints, sécurité, `.env`, Docker
  **Labels :** `docs`

### Issue : “Scénario de démo fil rouge”

**Description :**

* Script : création compte → upload → partage → téléchargement → versionnage → quota
  **Labels :** `demo`, `docs`
