# Jour 4 — Versions de fichiers (versioning) et impacts clients

Objectifs du jour
- Côté Back-end: ajouter l’endpoint de création de nouvelle version d’un fichier, clarifier la règle « liens → dernière version par défaut », et exposer les métadonnées de versions.
- Côté JavaFX: permettre de remplacer un fichier (upload nouvelle version) avec barre de progression et afficher l’historique des versions.
- Côté Web: indiquer visuellement s’il existe plusieurs versions et, au minimum, mentionner la date/numéro de la version servie.

Livrables attendus (fin de journée)
- Endpoints Back documentés et opérationnels:
  - `POST /files/{id}/versions` (multipart) → crée `version = previous + 1` avec chiffrement identique à J2.
  - `GET /files/{id}` renvoie métadonnées incluant `current_version`, `versions_count`, et éventuellement la liste (limitée) des dernières versions.
  - Option: `GET /files/{id}/versions` (liste paginée) et `GET /files/{id}/versions/{v}` (métadonnées précises).
- Règle « liens → dernière version » confirmée et détaillée: les liens `/s/{token}` doivent pointer vers la version courante, sauf option de figeage.
- JavaFX: action « Remplacer fichier » avec progression et section « Versions » (table/accordéon) affichant date/size/checksum.
- Web: badge « vN » ou « Plusieurs versions » et info « version actuelle servie ».

Plan détaillé des tâches
1) Back — Modèle & migrations (30min)
- Confirmer structure `file_versions (id, file_id, version, stored_name, iv, auth_tag, key_envelope, checksum, size, created_at)`.
- Index `UNIQUE(file_id, version)` et index sur `created_at`.

2) Back — Endpoint nouvelle version (1–2h)
- `POST /files/{id}/versions` (multipart/form-data):
  - Valider propriétaire, existence du fichier, quotas disponibles.
  - Générer nouvelles clés/IV; chiffrer en streaming; stocker nouveaux artefacts.
  - Calculer `nextVersion = 1 + max(version)` et insérer.
- Mettre à jour `files.size` et autres métadonnées utiles si nécessaire.

3) Back — Métadonnées versions (1h)
- Étendre `GET /files/{id}` pour inclure:
  - `current_version`
  - `versions_count`
  - `latest_versions`: tableau des N dernières `{version, size, created_at, checksum (truncated)}`.
- Option: endpoint liste complète paginée des versions.

4) Back — Liens et versions (45min)
- Lors d’un téléchargement public via `/s/{token}` sans paramètre, retourner la version courante.
- Option: supporter `?v=3` si le lien autorise explicitement les versions figées (à définir/plus tard).
- Journaliser la `version_id` dans `downloads_log` si disponible.

5) JavaFX — UI remplacement & historique (1–2h)
- Dans la vue détail fichier: bouton « Remplacer » → sélection fichier → upload vers `POST /files/{id}/versions`.
- Barre de progression (Task/Service JavaFX) + messages d’erreur.
- Panneau « Versions »: liste triée desc; actions: copier checksum, ouvrir dossier local (option si téléchargé), télécharger version spécifique (option si endpoint exposé).

6) Web — Indication de versions (45min)
- Sur la page publique et/ou privée, si `versions_count > 1`, afficher un badge et la date de la `current_version`.
- Option: petit sélecteur de version si exposition publique autorisée (sinon texte d’info seulement).

7) Tests & vérifications (45–60min)
- Postman: scénarios upload v1 (J2) → nouvelle version v2 → vérif métadonnées → téléchargement pointe bien vers v2.
- Tester erreurs: quotas dépassés, fichier inexistant, type MIME non autorisé (si politique), taille max.
- Vérifier la cohérence du `checksum` et l’immutabilité des versions précédentes.

Critères d’acceptation
- La création d’une nouvelle version fonctionne et incrémente `version` correctement.
- Les métadonnées retournées par `GET /files/{id}` affichent `current_version` et `versions_count` justes.
- Les liens de partage téléchargent la version courante sans modification côté client.
- JavaFX permet le remplacement avec feedback utilisateur et liste correctement les versions.
- Web signale clairement la présence de plusieurs versions.

Ressources utiles
- JavaFX progress & tasks: https://openjfx.io/javadoc/21/javafx.graphics/javafx/concurrent/Task.html
- HTTP multipart Java: https://square.github.io/okhttp/recipes/
- PHP stream encryption patterns: https://www.php.net/manual/en/function.openssl-encrypt.php
- Schémas de versionnage d’objets: https://martinfowler.com/bliki/Versioning.html

Notes
- Ne jamais réécrire une version existante: immutabilité garantit la traçabilité.
- Conserver des exemples d’API dans OpenAPI pour les réponses enrichies.
