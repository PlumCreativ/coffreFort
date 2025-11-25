# Jour 1 — Cadrage, organisation et contrat d’API (OpenAPI v1)

Objectif du jour
- Sortir une première version du contrat d’API (OpenAPI v1) pour débloquer le travail en parallèle (Back, JavaFX, Web).
- Poser l’organisation de projet (rôles, branches Git, CI minimale) et les conventions (erreurs JSON, statuts HTTP, sécurité de base).

Livrables attendus (fin de journée)
- `openapi.yaml` v1 cohérent avec le périmètre MVP (auth, dossiers/fichiers, partages, téléchargement, quotas).
- Règles d’erreurs communes documentées: format `{error, code}`, mapping des statuts (200/201/204/400/401/403/404/409/413/422/429/500).
- Squelette de l’API (Slim + Medoo) avec middlewares: CORS, JSON, gestion d’erreurs; variables d’environnement (`.env.example`).
- Projet Git initialisé avec branches `main`/`dev`, règles PR, et CI minimale (lint + tests placeholder).

Plan détaillé des tâches
1) Kick-off & organisation (1h)
- Clarifier les rôles: Back, JavaFX, Web, Ops/Qualité.
- Ouvrir un board Kanban (ToDo / In Progress / Review / Done) et un rituel daily 10 min.
- Définir la stratégie Git: branches `main`, `dev`, `feature/*`, PR + review obligatoires.

2) Modèle de données initial (1h)
- Valider les tables proposées dans README (`users`, `folders`, `files`, `file_versions`, `shares`, `downloads_log`) et les champs clés.
- Identifier les index indispensables (`user_id`, `folder_id`, `token`, `created_at`) et les contraintes d’intégrité.

3) OpenAPI v1 (2–3h)
- Lister les endpoints du MVP (README §6) et leurs schémas de requêtes/réponses.
- Définir les objets et erreurs: `Error {error:string, code:string}`, Pagination (si besoin), `File`, `Folder`, `Share`, etc.
- Spécifier la sécurité: JWT Bearer, réponses 401/403.
- Vérifier la cohérence dans l’éditeur Swagger (linting, exemples, descriptions).

4) Squelette Back-end (Slim + Medoo) (1–2h)
- Créer un projet PHP minimal avec Slim, config CORS, parseur JSON, gestion d’erreurs uniformisée.
- Brancher Medoo et une connexion DB (dev); préparer migrations initiales.
- Déposer un `.env.example` (`DB_DSN`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `STORAGE_PATH`, `QUOTA_DEFAULT`, etc.).

5) CI & Qualité (30–45min)
- Mettre en place GitHub Actions simple: exécution d’un linter (PHP-CS-Fixer/PHPCS), tests placeholder, validation OpenAPI (Spectral action).
- Rédiger un README section « Contribuer »: run local, scripts make/compose (optionnel), règles de style.

Critères d’acceptation
- Le `openapi.yaml` s’ouvre sans erreur dans Swagger Editor et couvre les cas d’usage listés dans README.
- Tous les endpoints du MVP ont au moins leurs schémas de base (request/response) et les codes d’erreurs.
- Un squelette d’API démarre en local (Hello World JSON) avec CORS et JSON middleware.
- La CI passe sur la branche `dev` et bloque en cas d’erreur de style ou OpenAPI invalide.

Ressources utiles
- OpenAPI
  - Swagger Editor: https://editor.swagger.io/
  - OpenAPI 3.0 Spec: https://spec.openapis.org/oas/v3.0.3
  - Spectral (lint): https://github.com/stoplightio/spectral
- Slim Framework
  - Site officiel: https://www.slimframework.com/
  - Middleware erreurs/CORS: https://www.slimframework.com/docs/v4/middleware/
- Medoo (micro ORM): https://medoo.in/
- JWT
  - RFC 7519: https://www.rfc-editor.org/rfc/rfc7519
  - PHP Firebase JWT: https://github.com/firebase/php-jwt
- Bonnes pratiques API erreurs: https://zalando.github.io/problem/

Notes
- Décider rapidement du vocabulaire des erreurs (codes applicatifs) pour éviter la dérive.
- Préparer un jeu d’exemples JSON réalistes dans OpenAPI (schemas + examples).
