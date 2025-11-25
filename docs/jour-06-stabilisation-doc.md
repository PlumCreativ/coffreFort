# Jour 6 — Stabilisation, couverture de tests et documentation

Objectifs du jour
- Corriger les défauts trouvés en J5, augmenter la couverture de tests (unitaires, intégration, E2E Postman/Newman).
- Produire la documentation d’installation/exploitation, la procédure de sauvegarde/restauration, et finaliser `openapi.yaml`.
- Préparer un jeu d’essai reproductible pour les démos/tests (seed de données, scripts).

Livrables attendus (fin de journée)
- Suite de tests renforcée: unités (services clés), intégration (routes), E2E (scénarios fil rouge) → verte en local et CI.
- Documentation:
  - Guide d’installation (API + DB + variables d’env + lancement local/Docker).
  - Procédure de sauvegarde/restauration (BDD + fichiers chiffrés) + test de restauration.
  - `openapi.yaml` finalisé (exemples, descriptions, codes d’erreurs complets).
- Script(s) de jeu d’essai: création d’un compte, upload de fichiers, création d’un lien, téléchargement.

Plan détaillé des tâches
1) Revue des issues & correctifs rapides (1–2h)
- Passer les tickets ouverts (board Kanban), prioriser: régressions > sécurité > UX > nice‑to‑have.
- Corriger et ajouter des tests ciblés sur chaque correction.

2) Tests unitaires & intégration (1–2h)
- Cibler d’abord les zones critiques: chiffrement/déchiffrement, quotas, génération/validation de tokens, règles d’expiration/uses.
- Ajouter tests d’intégration sur routes: Auth, Folders, Files (upload/download), Shares, Versions, Quota.
- Mesurer la couverture (sans viser un chiffre absolu, viser pertinence des cas).

3) Tests E2E (Postman/Newman) (45–60min)
- Scénarios: Register → Login → Create Folder → Upload v1 → Create Share → Public Download → Upload v2 → Public Download (v2).
- Inclure cas d’erreurs: 401, 403, 404, 409/413 (quota), 422 (validation), 429 (rate‑limit).
- Paramétrer l’environnement (URLs, tokens) et intégrer à la CI.

4) Documentation d’installation & d’exploitation (1–2h)
- Décrire: prérequis, variables `.env`, commandes de migration, lancement (PHP-FPM/Apache, ou Docker Compose), configuration CORS, clés crypto.
- Exemple de configuration nginx/Apache.
- Section « Dépannage »: ports occupés, erreurs de permissions sur stockage, certificats.

5) Sauvegarde/restauration (1h)
- Définir et documenter: dump de la BDD (mysqldump/pg_dump), archive du répertoire de stockage des fichiers chiffrés.
- Procédure de restauration pas à pas; exécuter un test de restauration dans un environnement isolé.

6) Données de démo/seed (45min)
- Script pour insérer: 1 utilisateur demo, 2 dossiers, 2 fichiers (v1), 1 partage actif, 1 expiré.
- Nettoyage/reset: script pour purger et réinitialiser l’environnement de test.

Critères d’acceptation
- Toutes les suites de tests passent localement et en CI; couverture améliorée sur les modules sensibles.
- La doc permet à un nouveau développeur de lancer l’environnement sans aide.
- La procédure de restauration a été exécutée avec succès au moins une fois (preuve: notes/capture/logs).
- `openapi.yaml` est validé par l’éditeur Swagger et par un linter (Spectral) en CI.

Ressources utiles
- Tests PHP
  - PHPUnit: https://phpunit.de/
  - Slim testing: https://www.slimframework.com/docs/v4/cookbook/phpunit.html
- Postman/Newman
  - Collections & environments: https://learning.postman.com/docs/publishing-your-api/run-in-postman/creating-run-button/
  - Newman CI: https://github.com/postmanlabs/newman#using-newman-with-your-continuous-integration-system
- Sauvegardes
  - mysqldump: https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html
  - pg_dump: https://www.postgresql.org/docs/current/app-pgdump.html
- Déploiement
  - Docker Compose: https://docs.docker.com/compose/
  - Nginx reverse proxy: https://docs.nginx.com/nginx/admin-guide/web-server/reverse-proxy/

Notes
- Documenter les clés et secrets: rotation, emplacement, droits d’accès; ne jamais committer de secrets.
- Inclure des captures d’écran pour la doc si le temps le permet.
