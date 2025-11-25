# Jour 2 — Fichiers & dossiers (Back) + Shells clients (JavaFX/Web)

Objectifs du jour
- Côté Back-end: livrer les bases « dossiers/fichiers » avec upload chiffré (version 1), téléchargement, quotas, et premiers endpoints opérationnels.
- Côté JavaFX: mettre en place le squelette d’app (écran de login, navigation initiale dossiers/fichiers, services API).
- Côté Web: maquette de la page publique `/s/{token}` et intégration Bootstrap de base.

Livrables attendus (fin de journée)
- Endpoints Back fonctionnels (au moins en local):
  - `POST /auth/register`, `POST /auth/login` (JWT).
  - `GET /folders`, `POST /folders`, `DELETE /folders/{id}`.
  - `GET /files?folder={id}`, `POST /files` (multipart → crée version 1 chiffrée), `GET /files/{id}` (métadonnées), `GET /files/{id}/download`.
  - `GET /me/quota`.
- Chiffrement au repos implémenté pour les contenus de fichiers (AES‑256‑GCM recommandé) + enveloppe de clé stockée (RSA‑OAEP ou X25519 sealed box) dans `file_versions.key_envelope`.
- JavaFX: projet lancé, écran Login → navigation simple vers une liste dossiers/fichiers (mock si Back non prêt), consommation JWT si dispo.
- Web: page publique `/s/{token}` maquettée (HTML/CSS/Bootstrap) avec structure prévue pour afficher un fichier ou une liste.

Plan détaillé des tâches
1) Back — Structure « dossiers » (1–2h)
- Migrations BDD: tables `folders` et l’indexation (`user_id`, `parent_id`, `created_at`).
- Routes:
  - `GET /folders` → liste racine et/ou par `parent_id` param.
  - `POST /folders {parent_id?, name}` → 201; validations: nom non vide, parent existant, quotas éventuels.
  - `DELETE /folders/{id}` → 204; règle: suppression logique ou blocage si non vide (à décider/documenter).
- Sécurité: JWT obligatoire; vérifier propriétaire (`user_id`).

2) Back — Upload chiffré v1 (2–3h)
- Endpoint `POST /files` (multipart/form-data): champs `folder_id`, `file`.
- Pipeline recommandé:
  1. Générer une clé aléatoire de contenu (32 octets) + IV aléatoire (12 octets) pour AES‑GCM.
  2. Chiffrer le flux du fichier en streaming (pour gros fichiers).
  3. Sceller la clé de contenu via clé publique serveur (RSA‑OAEP ou X25519 libsodium) → `key_envelope`.
  4. Stocker: fichier chiffré (nom physique `stored_name` unique), `iv`, `auth_tag`, `key_envelope`, `checksum` (SHA‑256), `size`.
  5. Créer `files` (si nouveau) et `file_versions` (version=1).
- Téléchargement `GET /files/{id}/download`:
  - Auth requise (sauf liens publics en J3).
  - Déchiffrement côté serveur OU renvoi chiffré + déchiffrement client (pour l’instant: côté serveur pour simplicité).
- Quotas `GET /me/quota` + vérification lors de l’upload (bloquer si dépassement; 413 ou 409 selon cas). Mise à jour `quota_used` après upload.

3) Back — Détails d’implémentation pratiques (1h)
- Stockage fichiers: arborescence par `user_id`/`YYYY/MM/` + `stored_name` UUID; éviter les collisions et faciliter la maintenance.
- Noms originaux: stockés en BDD (`files.original_name`) et renvoyés dans les métadonnées; attention aux encodages.
- Types MIME: détecter via contenu si possible; sinon se fier à `$_FILES`.
- Téléversement volumineux: utiliser flux/streams PHP pour éviter de charger en mémoire.
- Journalisation: au minimum, logguer les uploads (user, taille, hash, succès/échec).

4) JavaFX — Squelette et écran Login (1–2h)
- Projet Maven/Gradle conforme au guide `docs/implementation-java.md`.
- Modules: `javafx-controls`, `javafx-fxml`, HTTP client (OkHttp/HttpClient), JSON (Jackson/Gson).
- Écran Login:
  - Formulaire `email/password`, appel `POST /auth/login` → récupération JWT.
  - Stockage JWT en mémoire (service singleton) et entête `Authorization: Bearer <jwt>`.
- Navigation: après login, vue Liste (mock) des dossiers/fichiers (observable list) avec placeholders.
- Service API: méthode `listFolders()`, `listFiles(folderId)` — utiliser l’URL du backend depuis une config.

5) Web — Page publique (maquette) (1h)
- Créer page `/s/{token}` (statique au départ) avec Bootstrap: entête, carte « Ressource partagée », bouton Télécharger (inactif aujourd’hui).
- Prévoir zones dynamiques: nom fichier/dossier, taille, compteur d’usages, message d’expiration.
- Intégrer un thème simple (Bootstrap 5) et un message d’erreur stylé.

6) Tests & vérifications (30–45min)
- Postman: ajouter requêtes Auth, Folders, Files (upload), Download, Quota; variables d’environnement; sauvegarder la collection.
- Tester des cas d’erreurs: nom de dossier vide (422), dépassement quota (409/413), folder inexistant (404), non authentifié (401).

Critères d’acceptation
- Upload d’un petit fichier fonctionne et crée `file_versions` v1 avec chiffrement et `key_envelope`.
- Liste des dossiers/fichiers renvoyée pour l’utilisateur connecté; suppression dossier gérée selon la règle choisie.
- Quotas: le pourcentage utilisé est cohérent et remonte via `GET /me/quota`.
- JavaFX: écran Login opérationnel (appel live ou mock) et navigation vers une liste affichée.
- Web: `/s/{token}` affiche une maquette propre; aucune erreur console.

Exemples de codes/points d’attention
- PHP upload streams: utiliser `fopen`, `fread`, `fwrite` en boucle pour chiffrer par blocs.
- AES‑GCM: ne pas réutiliser le même IV avec la même clé; stocker `auth_tag`.
- Validation: taille max de fichier (config), extensions autorisées (option), antivirus (option ClamAV).

Ressources utiles
- Upload & fichiers en PHP
  - PHP Manual — file uploads: https://www.php.net/manual/en/features.file-upload.php
  - PSR‑7 uploaded files (Slim): https://www.slimframework.com/docs/v4/cookbook/uploading-files.html
  - Flux/streams PHP: https://www.php.net/manual/en/book.stream.php
- Crypto
  - AES‑GCM (concepts): https://en.wikipedia.org/wiki/Galois/Counter_Mode
  - libsodium (PHP): https://www.php.net/manual/en/book.sodium.php
  - OpenSSL PHP: https://www.php.net/manual/en/book.openssl.php
- Stockage fichiers
  - Flysystem: https://flysystem.thephpleague.com/v3/
- JavaFX
  - Getting started: https://openjfx.io/openjfx-docs/
  - OkHttp: https://square.github.io/okhttp/
  - Jackson: https://github.com/FasterXML/jackson
  - ListView/TableView tutoriel: https://code.makery.ch/library/javafx-tutorial/
- Web
  - Bootstrap 5 docs: https://getbootstrap.com/docs/5.3/getting-started/introduction/
  - Fetch API: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API
- Outils
  - Postman: https://www.postman.com/
  - Swagger Editor: https://editor.swagger.io/

Notes
- Si le Back n’est pas prêt, publier rapidement un mock server (Prism/JSON Server) à partir d’`openapi.yaml` pour débloquer JavaFX/Web.
- Tenir un œil sur les performances: ne jamais charger un fichier entier en RAM pendant le chiffrement.
