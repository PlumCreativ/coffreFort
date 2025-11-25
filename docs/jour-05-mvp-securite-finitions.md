# Jour 5 — Finitions du MVP & sécurité applicative de base

Objectifs du jour
- Solidifier le MVP: sécurité HTTP (headers), rate‑limit minimal, pagination des listes, messages d’erreur clairs.
- Améliorer l’UX des clients (JavaFX/Web): indicateurs de quota, retours d’erreur utilisables, micro‑polish UI.
- Consolider les tests (Postman/Newman) et préparer le README « usage » minimal.

Livrables attendus (fin de journée)
- Back-end: middlewares de sécurité activés (headers, CORS strict), rate‑limiting simple, pagination sur listes principales.
- Clients: UI avec messages d’erreur compréhensibles, affichage du quota (utilisé/total/%), état des opérations (progress, toasts).
- Tests: Collection Postman enrichie + exécution Newman OK en CI; README « comment lancer » et « scénarios de test ».

Plan détaillé des tâches
1) Sécurité HTTP (1h)
- Headers recommandés (via serveur ou app):
  - `Content-Security-Policy` (CSP) adaptée; au minimum, limiter scripts/styles de sources contrôlées.
  - `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` ou `SAMEORIGIN`, `Referrer-Policy: no-referrer-when-downgrade` (ou stricte), `Strict-Transport-Security` (si HTTPS).
- Vérifier CORS: n’autoriser que l’origine front autorisée; méthodes/headers précis; pas de wildcard en prod.

2) Rate‑limit minimal (45min)
- Stratégie simple: limiter par IP + route sensible (auth, téléchargement) avec une fenêtre glissante (ex. token bucket mémoire/Redis).
- Codes d’erreur: 429 Too Many Requests + `Retry-After`.

3) Pagination & tri (1h)
- Appliquer sur `GET /shares`, `GET /folders` (si pertinent), `GET /files?folder=…`.
- Paramètres: `limit`, `offset` (ou `page`, `per_page`) + tri `sort`/`order` optionnel.
- Réponses: enveloppe `{ data, total, limit, offset }`.

4) Gestion d’erreurs et UX clients (1–2h)
- Convention d’erreurs JSON déjà définie → s’y tenir; inclure un champ `hint` facultatif.
- JavaFX: afficher erreurs dans une barre/boîte dédiée; logs dev en console; traductions simples.
- Web: toasts Bootstrap pour succès/erreurs; messages spécifiques pour 401/403/404/409/413/422/429.

5) Quotas & indicateurs (45min)
- Back: endpoint `GET /me/quota` prêt (J2) → vérifier exactitude.
- JavaFX/Web: barre/anneau de progression du quota; couleurs selon seuils (80% orange, ≥100% rouge).

6) Tests Postman/Newman & CI (1h)
- Écrire tests d’API couvrant les codes d’erreur et la pagination.
- Intégrer Newman dans la CI (GitHub Actions) avec variables d’environnement sécurisées.

7) README « usage » (45min)
- Décrire comment lancer l’API en dev, créer un compte test, uploader un fichier, créer un lien, télécharger.
- Ajouter captures d’écran minimales (option) et liens vers `openapi.yaml` et docs `/docs`.

Critères d’acceptation
- Les headers de sécurité sont visibles à l’inspection réseau et compatibles avec les UIs.
- Les listes paginées répondent avec métadonnées et limites fonctionnent.
- Les erreurs sont explicites et reproductibles; 429 est renvoyé en cas d’abus simulé.
- Les clients affichent correctement le quota et gèrent des erreurs typiques (401, 413, 422, 429).
- La CI exécute Newman et échoue si une route critique casse.

Ressources utiles
- OWASP Secure Headers Project: https://owasp.org/www-project-secure-headers/
- Helmet (inspiration headers): https://helmetjs.github.io/
- Rate limiting concepts: https://cloudflare.com/learning/bots/what-is-rate-limiting/
- Slim middlewares: https://www.slimframework.com/docs/v4/middleware/
- Bootstrap Toasts: https://getbootstrap.com/docs/5.3/components/toasts/
- Newman CLI: https://www.npmjs.com/package/newman

Notes
- Tester la CSP en mode « Report-Only » d’abord pour éviter de casser l’appli.
- Documenter les limites (quotas, tailles) dans README pour les testeurs.
