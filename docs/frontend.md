# Frontend — Consignes détaillées

Ce document spécifie les deux interfaces à réaliser par les étudiants pour le projet Coffre‑fort numérique :
- un client lourd Java (JavaFX) pour la gestion complète de l’espace de stockage (privé, authentifié) ;
- une vue Web publique (MVC Slim) pour l’accès aux liens de téléchargement (non authentifié).

Se conformer à l’OpenAPI du dépôt (`openapi.yaml`) pour les échanges réseau. Ne réimplémentez pas la logique métier côté client ; le frontend orchestre uniquement les appels API et l’UX.

---

## 1) Spécification — Client lourd JavaFX

### 1.1 Objectif
Application de bureau permettant à un utilisateur authentifié de :
- visualiser son espace (dossiers, fichiers, métadonnées, versions) ;
- surveiller son quota et son utilisation ;
- créer/renommer/déplacer/supprimer des dossiers/fichiers ;
- téléverser de nouveaux fichiers et remplacer par de nouvelles versions (progression) ;
- télécharger ses fichiers ;
- créer et gérer des liens de partage publics paramétrés (durée de validité, nombre d’usages, éventuellement mot de passe si le back le supporte) ;
- révoquer des liens à tout moment ;
- visualiser l’historique minimal d’usage (ex : nombre de téléchargements).

### 1.2 Cibles techniques
- JDK 17+ recommandé.
- JavaFX 17+ (modules `javafx-controls`, `javafx-fxml`, `javafx-graphics`).
- HTTP client : `java.net.http.HttpClient` ou Retrofit/OkHttp.
- JSON : Jackson ou Gson.
- Build : Maven ou Gradle.
- OS : Windows/macOS/Linux (comportement multi‑plateforme attendu).

### 1.3 Architecture (suggestion)
- `api` : client OpenAPI (généré ou écrit à la main) + gestion des erreurs (mapping codes → messages).
- `auth` : gestion des tokens/session (stockage sécurisé en mémoire, persistance chiffrée optionnelle).
- `store` : modèles (User, Folder, File, Share, Version, Quota).
- `view` : scènes JavaFX, FXML ou code.
- `service` : adaptateurs UX (upload avec progression, pagination, retry simple, annulation).
- `util` : formatage tailles, dates, validations, copier dans le presse‑papiers.

### 1.4 Navigation & scènes attendues
1) Écran de connexion
- Champs : URL du serveur (préconfigurée), email/identifiant, mot de passe ou token API.
- Actions : « Se connecter », « Mémoriser le serveur » (pas le mot de passe). 
- États : chargement, erreurs d’authentification (401), indisponibilité serveur.

2) Tableau de bord (accueil)
- Carte « Quota » : utilisé/total, barre de progression, pourcentage.
- Raccourcis : « Nouveau dossier », « Téléverser », « Mes partages ».
- Dernières activités (optionnel, si exposé par l’API) : dernières mises en ligne.

3) Explorateur de stockage (vue principale)
- Layout type « gestionnaire de fichiers » :
  - Panneau gauche : arborescence `TreeView` des dossiers (lazy‑loading si nécessaire).
  - Panneau central : `TableView` listant le contenu du dossier courant (icône, nom, taille, type, versions, modifié le, propriétaire si pertinent).
  - Barre d’actions : 
    - Dossier : Nouveau, Renommer, Déplacer, Supprimer.
    - Fichier : Télécharger, Renommer, Déplacer, Supprimer, Remplacer (nouvelle version), Créer un lien de partage.
    - Upload : bouton « Téléverser » + glisser‑déposer (drag & drop) avec file chooser.
- Barre de recherche (local côté client par nom, et/ou serveur si l’API le permet).
- États : vide, chargement (skeleton), erreurs (toasts/bannières), droits insuffisants.

4) Détails d’une ressource (pane latéral ou fenêtre modale)
- Fichier : nom, taille, type MIME, hash/empreinte si fournie, date de création/modif., nombre de versions, auteur/owner.
- Versions : liste (n°, date, taille), actions : Télécharger une version, Restaurer (si API), Supprimer une version (si API).
- Dossier : nom, nombre d’éléments, taille cumulée (si exposée), date création.

5) Téléverser / Remplacer (nouvelle version)
- Boîte de dialogue avec : sélection du fichier, destination (dossier), barre de progression, vitesse estimée, bouton « Annuler ».
- En cas de dépassement de quota (413/422 suivant API) : message clair, suggestion de libérer de l’espace.

6) Gestion des partages (« Mes partages »)
- Liste des partages existants : ressource ciblée (fichier ou dossier), lien public, état (Actif/Expiré/Révoqué), expirera le, usages restants/maximum, créé le.
- Actions par partage :
  - Copier l’URL (bouton « Copier » → presse‑papiers),
  - Révoquer (confirmation),
  - Prolonger/modifier paramètres si l’API l’autorise,
  - Voir journaux d’usage minimal (ex : compteur téléchargements).
- Création d’un partage (depuis un fichier/dossier ou depuis « Nouveau partage ») :
  - Paramètres : 
    - Durée de validité (date/heure d’expiration ou durée),
    - Nombre maximal d’usages (ex : 1, 5, illimité si autorisé),
    - Option « Protégé par mot de passe » si exposé par l’API,
    - Commentaire interne (non visible publiquement).
  - Résultat : affichage du lien généré, bouton « Copier », QR code optionnel.

7) Paramètres de l’application
- URL du backend, timeouts réseau, langue (FR/EN), thème clair/sombre.
- Déconnexion, purge des données locales (cache listages), certificats (voir 1.7).

### 1.5 Comportements UX à respecter
- Actions destructrices : confirmation explicite (nom du fichier/dossier dans la boîte de dialogue).
- Opérations longues (upload/download) : indicateur de progression + possibilité d’annuler.
- Gestion des erreurs HTTP :
  - 401 : rediriger vers Connexion.
  - 403 : message « Accès interdit ».
  - 404 : ressource introuvable (rafraîchir la vue).
  - 409 : conflit (nom déjà utilisé, version concurrente).
  - 413/422 : taille/quota dépassé.
  - 5xx : message générique + réessai.
- i18n : clés de traduction, pas de textes en dur dans le code.
- Accessibilité : navigation clavier, labels explicites, contrastes suffisants.

### 1.6 Interactions API clés (se conformer à `openapi.yaml`)
- Authentification (login/token), rafraîchissement de session si applicable.
- Parcours arborescence : listage dossiers/fichiers, pagination si nécessaire.
- CRUD dossiers/fichiers.
- Upload (multipart ou binaire) + reprise/annulation si supporté.
- Versions : création (remplacement), consultation listes.
- Partages : création, listage, révocation, interrogation d’état.
- Quota : récupération de l’utilisation actuelle.

### 1.7 Sécurité
- Utiliser HTTPS (TLS) ; vérifier les certificats ou permettre le pinning en dev.
- Ne pas stocker les mots de passe en clair. Si mémorisation, privilégier des tokens d’accès avec stockage chiffré.
- Ne pas logguer les contenus sensibles (tokens, chemins privés).
- Nettoyer le presse‑papiers des liens après quelques minutes (optionnel).

### 1.8 Critères d’acceptation (JavaFX)
- L’arborescence s’affiche et permet navigation, CRUD, upload (avec barre de progression).
- Le quota s’affiche et réagit après upload/suppression.
- Création d’un lien de partage depuis un fichier/dossier, avec paramètres, et copie facile du lien.
- Remplacement d’un fichier crée une nouvelle version visible dans les détails, et les liens pointent vers la dernière version.
- Les erreurs d’API sont gérées et expliquées à l’utilisateur.

---

## 2) Spécification — Vue Web publique (MVC Slim)

### 2.1 Objectif
Exposer une page simple accessible via un token public (lien de partage) permettant de :
- afficher les informations de la ressource partagée (fichier ou dossier) si le lien est valide ;
- proposer le téléchargement ;
- informer précisément en cas de lien invalide : révoqué, expiré, usages épuisés, token inconnu/mal formé.

Cette vue est publique (pas d’authentification). Elle n’offre pas de navigation privée ni de gestion de compte.

### 2.2 Routes et contrôleur
- Route de consultation : `GET /download/{token}`
  - Affiche la page avec l’état du lien.
- Action de téléchargement : `POST /download/{token}` ou `GET` dédié selon l’OpenAPI
  - Déclenche le téléchargement et laisse le serveur compter/valider l’usage.
- Option pour dossiers : si l’API renvoie une archive (zip/tar), elle est téléchargée d’un bloc ; sinon, afficher la liste des éléments avec lien unique fourni par le back.

### 2.3 États de la page
1) Chargement
- Indicateur de chargement pendant la vérification du token (ou rendu direct côté serveur si Slim interroge l’API avant d’afficher la vue).

2) Lien valide
- Afficher :
  - Nom de la ressource, type (fichier/dossier), taille (si fournie), hash/empreinte (si fournie),
  - Nombre d’usages restants vs maximum (si exposé),
  - Date/heure d’expiration,
  - Message de confidentialité (« Ce lien est public. Ne le partagez qu’avec des personnes de confiance. »).
- Action principale : bouton « Télécharger ».
- Si mot de passe requis (si supporté par l’API) : champ de saisie + validation côté serveur.

3) Lien invalide
- Afficher une alerte avec la cause exacte :
  - « Lien révoqué par le propriétaire »,
  - « Lien expiré le JJ/MM/AAAA à HH:MM »,
  - « Nombre maximal d’utilisations atteint »,
  - « Token inconnu ou mal formé ».
- Proposer un lien d’aide générique (ex : page d’accueil du service) sans révéler d’informations privées.

### 2.4 Maquettage minimal (HTML/CSS)
- Mise en page simple, responsive, centrée :
  - En‑tête avec logo/titre du service,
  - Carte principale contenant les informations et le bouton,
  - Pied de page léger (mentions « non indexer », contact).
- Styles sobres (classes utilitaires, pas de framework imposé) ; attention au contraste.

### 2.5 Sécurité & confidentialité
- Ajouter `noindex, nofollow` (balise meta + header) pour éviter l’indexation des liens publics.
- Ne pas logguer le token en clair côté client ; côté serveur, journaux minimaux.
- Désactiver l’autocomplétion sur un éventuel champ mot de passe.
- S’assurer que le token n’apparaît pas dans le `Referer` envoyé à des domaines tiers (éviter ressources externes).
- Limiter les fuites d’infos : pour un token inconnu, ne révéler aucun détail sur la ressource.

### 2.6 Accessibilité
- Sémantique HTML correcte : `main`, `header`, `footer`, `button`, `dl/dt/dd` pour les métadonnées.
- Focus visible, navigation clavier, textes de liens explicites.
- Messages d’erreur lisibles par les lecteurs d’écran (ARIA `role=alert`).

### 2.7 Critères d’acceptation (Web)
- Un token valide affiche les métadonnées et propose le téléchargement.
- Un token invalide affiche la cause exacte parmi : révoqué, expiré, usages épuisés, inconnu/mal formé.
- Le téléchargement déclenche bien la consommation d’un usage côté serveur (si le back le gère), et les infos d’usages restants sont mises à jour au rechargement.

---

## 3) Convergences et contraintes communes
- Respect strict de l’OpenAPI du dépôt (`openapi.yaml`) ; en cas d’écart, documenter et soumettre une MR.
- Messages d’erreur cohérents entre JavaFX et Web (terminologie identique).
- Gestion des formats : affichage des tailles (octets, Ko, Mo, Go), dates locales, fuseaux horaires.
- Internationalisation : FR par défaut, préparer l’anglais (fichiers de ressources).
- Journalisation : logs lisibles par un correcteur (niveau INFO/ERROR), sans secrets.

## 4) Livrables des étudiants
- Code source complet (JavaFX : projet Maven/Gradle ; Web : contrôleur + vue Slim).
- Fichiers de configuration (ex : URL du backend) avec valeurs par défaut pour l’évaluation.
- Instructions d’exécution dans le README : 
  - JavaFX : version de Java, commande `mvn javafx:run` ou équivalente.
  - Web : route Slim à appeler, configuration locale.
- Captures d’écran/GIF : 
  - JavaFX : connexion, explorateur, upload (progression), création d’un partage, liste des partages.
  - Web : affichage d’un lien valide, affichage des erreurs pour chaque cas d’invalidité.
- Optionnel : courte vidéo de démonstration (≤ 3 minutes).

## 5) Barème indicatif (orientation)
- Conformité fonctionnelle (JavaFX) : 40 %
- Conformité fonctionnelle (Web) : 25 %
- Qualité UX (états, erreurs, accessibilité de base) : 15 %
- Qualité technique (structure, propreté du code, gestion des erreurs, i18n) : 15 %
- Documentation et livrables (README, captures, clarté) : 5 %

---

Questions/écarts : utilisez les issues du dépôt pour clarifier avant implémentation. Seule la dernière version de `openapi.yaml` fait foi pour les intégrations.