# Jour 7 — Démo finale & soutenance

Objectifs du jour
- Préparer et exécuter une démonstration fluide du fil rouge fonctionnel de bout en bout.
- Finaliser les supports de soutenance (slides, schémas, métriques, risques/axes d’amélioration).
- S’assurer que le dépôt est propre: tags, release notes, documentation à jour, jeux d’essai prêts.

Livrables attendus (fin de journée)
- Démo « fil rouge » maîtrisée et chronométrée (10–15 minutes) + Q&A (5–10 minutes).
- Slides de soutenance (PDF/PowerPoint) avec: vision, architecture, périmètre, sécurité, démos, tests/qualité, rétrospective.
- Release v1.0.0 (ou v0.9.0) taguée dans Git avec changelog résumé et liens vers `openapi.yaml`, docs `/docs` et collection Postman.

Scénario de démo conseillé (fil rouge — 10 à 12 minutes)
1) Contexte (1 min)
- Rappeler la vision (coffre‑fort numérique sécurisé) et le périmètre du MVP.

2) Back-end up & running (30s)
- Montrer rapidement l’API qui tourne (logs/healthcheck) et `openapi.yaml` dans Swagger Editor.

3) Création de compte & login (1 min)
- Via Postman ou JavaFX: `POST /auth/register` (si nécessaire) puis `POST /auth/login` → JWT.

4) Dossiers & upload v1 (2 min)
- Créer un dossier, lister, uploader un fichier (progress bar JavaFX), afficher quota.

5) Partage public & téléchargement (2 min)
- Créer un lien de partage (JavaFX), copier l’URL, ouvrir la page Web `/s/{token}`, télécharger; montrer décrément `remaining_uses` ou l’expiration.

6) Nouvelle version (1–2 min)
- Remplacer le fichier depuis JavaFX; vérifier que la version courante a changé; retélécharger via le même lien (pointe vers la dernière version).

7) Journalisation & activité (1 min)
- Afficher rapidement des logs (Postman: `GET /me/activity` si implémenté) ou table `downloads_log`.

8) Sécurité & limites (1 min)
- Évoquer headers de sécurité, CORS, quotas, rate‑limit; montrer un exemple d’erreur 429 ou 413.

9) Clôture (30s)
- Rappeler les points forts, limites connues et axes d’amélioration.

Préparation de la soutenance
- Slides (plan minimal):
  1. Vision & périmètre
  2. Architecture (schéma: API, DB, stockage, clients) + choix techniques
  3. Modèle de données (tables clés)
  4. Contrat d’API (principaux endpoints) & conventions d’erreurs
  5. Sécurité (chiffrement, JWT, partages/tokens, headers)
  6. Démo fil rouge (captures/étapes)
  7. Qualité (tests, CI, couverture, linter, OpenAPI lint)
  8. Déploiement & sauvegardes
  9. Rétrospective (ce qui marche, difficultés, leçons, améliorations)

- Chiffres & métriques possibles:
  - Taille max testée, temps d’upload (indicatif), % de couverture tests, nombre d’issues traitées, vélocité par jour.

- Backups & restauration: rappeler l’existence de la procédure et d’un test réel effectué (J6).

Checklist dépôt avant release
- Nettoyer les README et docs: liens valides, sommaires, mention de la version.
- Ajouter un `CHANGELOG.md` synthétique (Keep a Changelog style si possible).
- Créer un tag `v1.0.0` (ou `v0.9.0`) et une release GitHub avec artefacts (collections Postman, captures éventuelles).
- Vérifier que la CI est « verte » sur la release; inclure un badge de statut dans le README.
- Conserver les fichiers d’environnement d’exemple (`.env.example`) et retirer tout secret.

Gestion des risques de dernière minute
- Avoir un environnement de secours (mock/prism) en cas de panne API.
- Exporter la collection Postman et prévoir une démo « offline » si nécessaire (captures + script narratif).
- Tester la démo sur la machine de présentation (droits, réseau, certifs) et un jeu de données local.

Critères d’acceptation
- La démo s’exécute sans accroc et couvre le fil rouge complet.
- Les slides sont claires, autoportantes, et reflètent fidèlement l’état du projet.
- La release est taguée et contient les références nécessaires (OpenAPI, docs, tests Postman).

Ressources utiles
- Présentations efficaces: https://www.duarte.com/presentation-skills-resources/
- Keep a Changelog: https://keepachangelog.com/
- Semantic Versioning: https://semver.org/
- Swagger Editor: https://editor.swagger.io/
- GitHub Releases: https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases

Notes
- Faire une répétition générale chronométrée avec un binôme « critique » qui challenge le récit.
- Préparer des sauvegardes des fichiers de démo localement au cas où le réseau est lent.
