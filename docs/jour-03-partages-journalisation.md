# Jour 3 — Partages (liens sécurisés) & journalisation des téléchargements

Objectifs du jour
- Côté Back-end: implémenter la création/gestion de liens de partage sécurisés (token signé), contraintes d’expiration/d’uses, et la journalisation des accès/téléchargements.
- Côté JavaFX: permettre la création d’un lien de partage depuis la vue fichier/dossier et afficher « mes partages » avec leurs statuts.
- Côté Web: rendre fonctionnel le flux public de téléchargement via `/s/{token}`.

Livrables attendus (fin de journée)
- Endpoints Back opérationnels et documentés dans `openapi.yaml`:
  - Privé: `POST /shares` {kind, target_id, label, expires_at|max_uses} → 201 {id, token, url}
  - Privé: `GET /shares` (liste paginée + stats: remaining_uses, exp, revoked)
  - Privé: `POST /shares/{id}/revoke` → 204 (révocation immédiate)
  - Public: `GET /s/{token}` → métadonnées minimalistes (nom, type, taille, version courante)
  - Public: `POST /s/{token}/download` → binaire (ou redirection), décrémente `remaining_uses`, loggue l’événement
- Journal `downloads_log` alimenté à chaque tentative (succès/échec, IP, user-agent, horodatage).
- JavaFX: UI pour créer un lien depuis un fichier/dossier; écran « Mes partages » listant statut (actif, expiré, révoqué, compteur).
- Web: page publique `/s/{token}` affiche les infos et permet le téléchargement si autorisé.

Plan détaillé des tâches
1) Back — Modèle & sécurité du token (1h)
- Choix du format de token: opaque signé (HMAC SHA‑256 sur payload) ou JWT public minimal. Recommandation: token opaque signé côté serveur.
- Payload minimal (côté BDD): `shares { id, user_id, kind, target_id, label, expires_at, max_uses, remaining_uses, is_revoked, created_at }`.
- Génération token: `base64url(random32) + signature HMAC(secret, random32 || id)` stockée en BDD; longueur suffisante (≥ 32 octets randomness + signature).
- Lien public: `/s/{token}`; aucune info sensible dans l’URL (pas d’id brut).

2) Back — Règles métier & endpoints (2–3h)
- `POST /shares` validations: propriétaire de la ressource, `expires_at` dans le futur, `max_uses` ≥ 1, label optionnel.
- `GET /shares`: filtrage par ressource optionnel, tri par `created_at desc`, pagination (limit/offset ou cursor).
- `POST /shares/{id}/revoke`: met `is_revoked=true`, rend le token invalide immédiatement.
- `GET /s/{token}`: vérifie token, non révoqué, non expiré, `remaining_uses > 0`; renvoie métadonnées non sensibles.
- `POST /s/{token}/download`: même vérifs, journalise la tentative, décrémente `remaining_uses` si succès.
- Gestion concurrente des `remaining_uses`: UPDATE atomique avec condition (`remaining_uses > 0`) pour éviter double consommation.

3) Back — Journalisation (1h)
- Table `downloads_log`: `id, share_id, version_id?, downloaded_at, ip, user_agent, success, message?`.
- Logguer toutes les tentatives: 200/403/404/410/429; inclure raison en cas d’échec (expiré, révoqué, plus d’uses, token inconnu).
- Exposer un endpoint privé `GET /me/activity` (simple) listant les derniers événements liés à l’utilisateur (optionnel aujourd’hui).

4) JavaFX — Création & liste des partages (1–2h)
- Dans la vue fichier/dossier, bouton « Créer un lien » ouvrant un dialogue: label, expiration OU max_uses.
- Appel `POST /shares` et affichage de l’URL résultante (copie presse-papiers).
- Écran « Mes partages »: table avec colonnes: ressource, label, expires_at, remaining_uses, état (révoqué/actif).
- Action « Révoquer » avec confirmation → `POST /shares/{id}/revoke`.

5) Web — Flux public (1–2h)
- Page `/s/{token}`: récupération des métadonnées (`GET /s/{token}`) et rendu.
- Bouton « Télécharger » → `POST /s/{token}/download`; gérer les réponses d’erreur (expiré/révoqué/épuisé).
- Option UX: afficher compteur restant si `max_uses` défini; message d’expiration relatif (ex: « expire dans 2 jours »).

6) Tests & vérifications (45–60min)
- Postman: ajouter scénarios création → consultation publique → téléchargement → décrément; cas d’erreurs et révocation.
- Tester concurrence: deux téléchargements quasi simultanés ne doivent pas permettre de passer sous zéro.
- Vérifier que les logs se remplissent et qu’aucune info sensible n’est exposée publiquement.

Critères d’acceptation
- Les liens publics fonctionnent et respectent expiration/compteur d’usages et révocation.
- Les téléchargements sont journalisés (succès/échecs) avec IP et user-agent.
- JavaFX permet de créer/révoquer des liens et d’en consulter la liste.
- La page publique Web permet un téléchargement valide et affiche des erreurs claires autrement.

Ressources utiles
- HMAC et tokens
  - HMAC (RFC 2104): https://www.rfc-editor.org/rfc/rfc2104
  - base64url: https://datatracker.ietf.org/doc/html/rfc4648#section-5
- Concurrence SQL
  - Transactions et UPDATE conditionnel: https://dev.mysql.com/doc/refman/8.0/en/commit.html
- Sécurité Web
  - OWASP Cheat Sheets (Auth, Session, Token): https://cheatsheetseries.owasp.org/
- JavaFX
  - Dialogs & Forms: https://code.makery.ch/library/javafx-tutorial/part2/
- Web
  - Gestion téléchargement via Fetch/anchor: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API

Notes
- Éviter de divulguer le nom réel de fichier ou des chemins internes dans les endpoints publics.
- Penser à la compatibilité liens périmés: retourner 410 Gone pour un lien expiré peut être pertinent.
