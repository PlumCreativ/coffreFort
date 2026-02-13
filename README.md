# Coffre-fort Numérique - Backend API

> Projet de réalisation professionnelle - Backend REST sécurisé pour un coffre-fort numérique avec chiffrement au repos, gestion de versions et partages contrôlés.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Slim Framework](https://img.shields.io/badge/Slim-4.12-green)](https://www.slimframework.com/)
[![Medoo](https://img.shields.io/badge/Medoo-2.2-orange)](https://medoo.in/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

##  Table des matières

- [Vue d'ensemble](#-vue-densemble)
- [Fonctionnalités](#-fonctionnalités)
- [Architecture technique](#️-architecture-technique)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#️-configuration)
- [Utilisation](#-utilisation)
- [API Documentation](#-api-documentation)
- [Sécurité](#-sécurité)
- [Tests](#-tests)
- [Déploiement](#-déploiement)
- [Contribution](#-contribution)
- [Problèmes connus et solutions](#-problèmes-connus-et-solutions)

---

##  Vue d'ensemble

Le backend du coffre-fort numérique est une API REST construite avec **Slim Framework 4.12** et **Medoo 2.2**, offrant un système de stockage sécurisé de fichiers avec :

-  **Chiffrement au repos** (AES-256-GCM)
-  **Authentification JWT** (Firebase PHP-JWT)
-  **Organisation hiérarchique** (dossiers/fichiers avec CASCADE)
-  **Versionnage** automatique des fichiers
-  **Partages contrôlés** avec tokens sécurisés (~43 caractères)
-  **Gestion des quotas** utilisateur
-  **Journalisation** complète des accès et téléchargements
-  **Rôle administrateur** (suppression utilisateurs, gestion quota)
-  **Audit complet** via table `audit_logs`

### Contexte pédagogique

Ce projet prolonge le TP « Mini coffre-fort REST (MVC-API-REST) » en ajoutant :
- Authentification multi-utilisateurs avec JWT
- Chiffrement AES-256-GCM avec enveloppe de clés
- Système de partage avec tokens signés
- Versionnage et traçabilité complète
- Gestion avancée des droits (admin/user)
- Protection contre les suppressions critiques

---

## Fonctionnalités

### Gestion des utilisateurs
-  Inscription avec validation email
-  Authentification JWT via Firebase PHP-JWT
-  Hachage sécurisé des mots de passe (password_hash)
-  Gestion des quotas d'espace individuels (quota_total, quota_used)
-  Rôle administrateur (is_admin)
-  Suppression d'utilisateurs par admin avec CASCADE automatique

### Stockage sécurisé
-  Upload de fichiers avec chiffrement AES-256-GCM
-  Organisation en dossiers hiérarchiques (parent_id)
-  Versionnage automatique lors du remplacement
-  Interdiction de supprimer la dernière version
-  Métadonnées complètes : original_name, stored_name, MIME, taille, checksum SHA-256
-  Suppression CASCADE : user → folders/files → versions/logs

### Partages et diffusion
-  Création de liens de partage sécurisés (token base64url)
-  Support fichiers ET dossiers (kind: 'file'|'folder')
-  Contrôle d'expiration (expires_at)
-  Limitation du nombre d'utilisations (max_uses, remaining_uses)
-  Révocation immédiate (is_revoked)
-  Journalisation détaillée (IP, user-agent, succès/échec, version téléchargée)

### Administration
-  Suppression d'utilisateurs avec nettoyage automatique :
    - Fichiers physiques sur le disque
    - Dossiers et sous-dossiers (CASCADE)
    - Fichiers et versions (CASCADE)
    - Partages et logs de téléchargements (CASCADE)
-  Protection contre l'auto-suppression admin
-  Gestion des quotas utilisateurs

### Audit et traçabilité
-  Table `audit_logs` pour traçabilité complète
-  Journalisation des actions utilisateurs (login, upload, delete, etc.)
-  Stockage IP, user-agent, détails de l'action
-  Index optimisés pour recherche rapide par user, action, date

---

##  Architecture technique

### Stack technologique

```
┌─────────────────────────────────────┐
│         Clients                     │
│  (JavaFX Desktop + Web Public)      │
└──────────────┬──────────────────────┘
               │ HTTPS/REST (38 routes)
               ▼
┌─────────────────────────────────────┐
│     API Backend (Slim 4.12)         │
│  ┌─────────────────────────────┐    │
│  │  Middlewares                │    │
│  │  - CORS (origins)           │    │
│  │  - JWT Auth (Firebase)      │    │
│  │  - Error Handler (JSON)     │    │
│  │  - Rate Limiter (TODO v2)   │    │
│  └─────────────────────────────┘    │
│  ┌─────────────────────────────┐    │
│  │  Routes & Controllers       │    │
│  │  - Auth (register, login)   │    │
│  │  - Folders (CRUD)           │    │
│  │  - Files (upload, versions) │    │
│  │  - Shares (create, revoke)  │    │
│  │  - Admin (delete users)     │    │
│  │  - Public (/s/{token})      │    │
│  └─────────────────────────────┘    │
│  ┌─────────────────────────────┐    │
│  │  Repositories (Medoo 2.2)   │    │
│  │  - UserRepository           │    │
│  │  - FolderRepository         │    │
│  │  - FileRepository           │    │
│  │  - ShareRepository          │    │
│  │  - DownloadLogRepository    │    │
│  │  - AuditLogRepository       │    │
│  └─────────────────────────────┘    │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│    Medoo ORM (Micro)                │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│   MySQL Database                    │
│   Tables:                           │
│   - users (with is_admin)           │
│   - folders (hierarchical)          │
│   - files (with stored_name)        │
│   - file_versions (AES-256-GCM)     │
│   - shares (token base64url)        │
│   - downloads_log                   │
│   - audit_logs                      │
│   + Triggers & CASCADE constraints  │
└─────────────────────────────────────┘

           +
           
┌─────────────────────────────────────┐
│   File Storage (chiffré)            │
│   storage/files/{user_id}/...       │
└─────────────────────────────────────┘
```

### Technologies utilisées

- **PHP 8.2+** : langage backend
- **Slim Framework 4.12** : micro-framework REST (AppFactory, PSR-7, PSR-15)
- **Medoo 2.2** : micro-ORM léger pour accès MySQL
- **Firebase PHP-JWT 6.11** : gestion des tokens d'authentification
- **OpenSSL** : chiffrement AES-256-GCM
- **MySQL 8.0** : base de données relationnelle
- **Docker & Docker Compose** : containerisation

### Modèle de données (implémenté)

```sql
-- Utilisateurs (avec rôle admin)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    quota_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    quota_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dossiers hiérarchiques (CASCADE sur parent et user)
CREATE TABLE folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_folders_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) 
        REFERENCES folders(id) ON DELETE CASCADE
);

-- Fichiers (avec nom stockage séparé)
CREATE TABLE files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(150) NOT NULL,  -- UUID
    mime VARCHAR(255) NOT NULL,
    size BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_files_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) 
        REFERENCES folders(id) ON DELETE SET NULL
);

-- Versions de fichiers (chiffrement AES-256-GCM)
CREATE TABLE file_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    iv VARBINARY(12) NOT NULL,              -- Initialization Vector
    auth_tag VARBINARY(16) NOT NULL,        -- Authentication Tag
    key_envelope BLOB NOT NULL,             -- Clé chiffrée avec KEK
    size BIGINT UNSIGNED NOT NULL,
    checksum CHAR(64) NOT NULL,             -- SHA-256 du ciphertext
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_file_versions_file FOREIGN KEY (file_id) 
        REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_file_version (file_id, version)
);

-- Partages sécurisés (token base64url)
CREATE TABLE shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    kind ENUM('file', 'folder') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,         -- Token base64url (~43 chars) + padding
    label VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    max_uses INT UNSIGNED NULL,
    remaining_uses INT UNSIGNED NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    allow_fixed_versions TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_shares_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
);

-- Logs des téléchargements (pour statistiques et audit)
CREATE TABLE downloads_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    success TINYINT(1) NOT NULL,
    message VARCHAR(255) NULL,
    CONSTRAINT fk_downloads_share FOREIGN KEY (share_id) 
        REFERENCES shares(id) ON DELETE CASCADE,
    CONSTRAINT fk_downloads_version FOREIGN KEY (version_id) 
        REFERENCES file_versions(id) ON DELETE SET NULL
);

-- Audit complet des actions utilisateurs
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action ENUM(
        'USER_LOGIN', 'USER_REGISTER', 'USER_LOGOUT',
        'FOLDER_CREATE', 'FOLDER_RENAME', 'FOLDER_DELETE',
        'FILE_UPLOAD', 'FILE_RENAME', 'FILE_DELETE',
        'FILE_VERSION_UPLOAD', 'FILE_VERSION_DELETE',
        'SHARE_CREATE', 'SHARE_REVOKE', 'SHARE_DELETE',
        'FILE_DOWNLOAD', 'FILE_VERSION_DOWNLOAD', 'SHARE_DOWNLOAD',
        'QUOTA_UPDATE', 'USER_DELETE',
        'OTHER'
    ) NOT NULL,
    table_name VARCHAR(50) NULL,
    record_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(50) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_table_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index pour optimisation des requêtes
CREATE INDEX idx_folders_user ON folders(user_id);
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_shares_token ON shares(token);
CREATE INDEX idx_downloads_share ON downloads_log(share_id);
CREATE INDEX idx_file_versions_created_at ON file_versions(created_at);
```

**Points clés CASCADE** :
- Supprimer un user → supprime automatiquement folders, files, shares
- Supprimer un folder → supprime sous-dossiers (parent_id)
- Supprimer un file → supprime toutes ses versions
- Supprimer un share → supprime logs de téléchargements associés

---

##  Prérequis

### Environnement de développement

- **PHP** ≥ 8.2
    - Extensions requises : `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`, `zip`
- **Composer** (gestionnaire de dépendances PHP)
- **MySQL** ≥ 8.0 ou **MariaDB** ≥ 10.5
- **Git**

### Optionnel mais recommandé

- **Docker** et **Docker Compose** (pour déploiement rapide)
- **Node.js** et **Newman** (pour tests E2E Postman)

---

##  Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/PlumCreativ/coffreFort.git
cd coffreFort
```

### 2. Installer les dépendances

```bash
composer install
```

**Dépendances principales** (automatiquement installées) :
```json
{
  "require": {
    "slim/slim": "^4.12",
    "slim/psr7": "^1.8",
    "catfan/medoo": "^2.2",
    "firebase/php-jwt": "^6.11"
  }
}
```

> **Note** : Si problème avec Medoo, utiliser :
> ```bash
> composer require slim/slim:"4.12" slim/psr7:"1.8" catfan/medoo:"2.2"
> composer update
> ```

### 3. Configurer l'environnement

```bash
cp .env.example .env
```

Éditer `.env` avec vos paramètres (voir section [Configuration](#️-configuration))

### 4. Créer la base de données

```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE coffreFort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Ou via Docker (voir docker-compose.yml)
docker-compose up -d mysql
```

### 5. Exécuter les migrations

```bash
# Via script SQL
mysql -u votre_user -p coffreFort < init.sql

# Ou via Docker
docker exec -i coffreFort-mysql mysql -uroot -proot coffreFort < init.sql
```

### 6. Créer le répertoire de stockage

```bash
mkdir -p storage
chmod 700 storage
```

### 7. Lancer avec Docker Compose (recommandé)

```bash
docker-compose up -d --build
```

Vérifier les logs :
```bash
docker-compose logs -f
docker logs coffreFort-web
```

Arrêter :
```bash
docker-compose down -v
```

---

##  Configuration

### Variables d'environnement (.env)

```ini
# Base de données
DB_HOST=mysql              # 'mysql' pour Docker, 'localhost' pour local
DB_PORT=3306
DB_NAME=coffreFort
DB_USER=root
DB_PASSWORD=root
DB_ROOT_PASSWORD=votre_password_root_securise

# JWT Authentication (OBLIGATOIRE)
JWT_SECRET=votre_secret_jwt_ultra_long_et_aleatoire_ici

# Clé de chiffrement AES-256-GCM (OBLIGATOIRE, ≥32 caractères)
KEY_ENCRYPTION_KEY=votre_cle_kek_de_32_caracteres_minimum_securisee

# Clé de signature HMAC pour tokens de partage (OBLIGATOIRE)
SHARE_SECRET=votre_secret_hmac_pour_tokens_partage

# Application
APP_PUBLIC_BASE_URL=http://localhost:9081
```

> ️ **SÉCURITÉ** : Ne jamais committer le fichier `.env` ! Utilisez `.env.example` comme template.

### Configuration Docker Compose

Le fichier `docker-compose.yml` configure :
- **Service `mysql`** : MySQL 8.0
- **Service `web`** : PHP 8.2 + Apache
- **Service `phpmyadmin`** : phpMyAdmin (latest)
- **Volumes** : persistance BDD + fichiers
- **Ports** :
    - 9081 (web API)
    - 3306 (mysql)
    - 8081 (phpmyadmin)

---

##  Utilisation

### Démarrage du serveur

```bash
# Avec Docker Compose (recommandé)
docker-compose up -d

# Vérifier que les conteneurs tournent
docker ps
```

L'API sera accessible sur :
- **API** : `http://localhost:9081`
- **phpMyAdmin** : `http://localhost:8081`

### Vérification santé

```bash
curl http://localhost:9081/
```

Réponse attendue :
```json
{
  "message": "File Vault API",
  "endpoints": [
    "GET /admin/users/quotas",
    "PUT /admin/users/{id}/quota",
    "DELETE /admin/users/{id}",
    "GET /files",
    "GET /files/{id}",
    "POST /files",
    "..."
  ]
}
```

### Workflow complet d'utilisation sur Postman

#### 1. Créer un compte

```bash
curl -X POST http://localhost:9081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "exemple@gmail.com",
    "password": "exemple12345"
  }'
```

#### 2. Se connecter (obtenir JWT)

```bash
curl -X POST http://localhost:9081/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "exemple@gmail.com",
    "password": "exemple12345"
  }'
```

Réponse :
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "email": "exemple@gmail.com",
    "is_admin": false
  }
}
```

#### 3. Créer un dossier

```bash
curl -X POST http://localhost:9081/folders \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Documents",
    "parent_id": null
  }'
```

#### 4. Uploader un fichier (chiffré automatiquement)

```bash
curl -X POST http://localhost:9081/files \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -F "file=@/chemin/vers/document.pdf" \
  -F "folder_id=1"
```

#### 5. Créer un partage

```bash
curl -X POST http://localhost:9081/shares \
  -H "Authorization: Bearer VOTRE_TOKEN_JWT" \
  -H "Content-Type: application/json" \
  -d '{
    "kind": "file",
    "target_id": 1,
    "label": "Mon rapport",
    "expires_at": "2026-02-18T23:59:59Z",
    "max_uses": 5
  }'
```

Réponse :
```json
{
  "id": 42,
  "token": "abc123def456ghi789jkl012mno345pqr678stu901vwx234",
  "url": "http://localhost:9081/s/abc123def456ghi789jkl012mno345pqr678stu901vwx234",
  "expires_at": "2026-02-18T23:59:59Z",
  "max_uses": 5,
  "remaining_uses": 5
}
```

#### 6. Télécharger via lien public (sans authentification)

```bash
curl -X POST http://localhost:9081/s/abc123def456.../download -O -J
```

---

##  API Documentation

### Documentation OpenAPI

La documentation complète est disponible dans `openapi.yaml` :

```bash
# Visualiser dans Swagger Editor
https://editor.swagger.io/?url=https://raw.githubusercontent.com/AstrowareConception/Coffre-fort-numerique/refs/heads/main/openapi.yaml
```

### Endpoints implémentés (38 routes)

| Méthode               | Endpoint                     | Description         | Auth | Status |
|-----------------------|------------------------------|---------------------|------|--------|
| **Authenti fication** |
| POST                  | `/auth/register`             | Inscription         | Non | ✅      |
| POST                  | `/auth/logi n`               | Connexi on JWT      | Non | ✅      |
| **Dossiers**          |
| GET                   | `/folders`                   | Liste dossiers      | Oui | ✅      |
| GET                   | `/folders/{id}`              | Détails dossier     | Oui | ✅      |
| POST                  | `/folders`                   | Créer dossier       | Oui | ✅      |
| PUT                   | `/folders/{id}`              | Renommer dossier    | Oui | ✅      |
| DELETE                | `/folders/{id}`              | Supprimer dossier   | Oui | ✅      |
| **Fichiers**          |
| GET                   | `/files`                     | Liste fichiers      | Oui | ✅      |
| GET                   | `/files/{id}`                | Métadonnées fichier | Oui | ✅      |
| POST                  | `/files`                     | Upload fichier v1   | Oui | ✅      |
| DELETE                | `/files/{id}`                | Supprimer fichier   | Oui | ✅      |
| GET                   | `/files/{id}/download`       | Télécharger         | Oui | ✅      |
| **Versions**          |
| POST                  | `/files/{id}/versions`       | Nouvelle version    | Oui | ✅      |
| GET                   | `/files/{id}/versions`       | Liste versions      | Oui | ✅      |
| DELETE                | `/files/{id}/versions/{vid}` | Supprimer version*  | Oui | ✅      |
| **Partages**          |
| GET                   | `/shares`                    | Mes partages        | Oui | ✅      |
| POST                  | `/shares`                    | Créer partage       | Oui | ✅      |
| POST                  | `/shares/{id}/revoke`        | Révoquer partage    | Oui | ✅      |
| GET                   | `/s/{token}`                 | Info partage public | Non | ✅      |
| POST                  | `/s/{token}/download`        | Télécharger public  | Non | ✅      |
| **Quotas & Stats**    |
| GET                   | `/me/quota`                  | Mon quota           | Oui | ✅      |
| GET                   | `/me/activity`               | Mon activité        | Oui | ✅      |
| **Admin**             |
| GET                   | `/admin/users/quotas`        | Liste quotas        | Admin | ✅      |
| PUT                   | `/admin/users/{id}/quota`    | Modifier quota      | Admin | ✅      |
| DELETE                | `/admin/users/{id}`          | Supprimer user      | Admin | ✅      |

\* Interdiction de supprimer la dernière version d'un fichier

### Codes de statut HTTP

| Code | Signification         | Exemples d'utilisation                 |
|------|-----------------------|----------------------------------------|
| 200 | O K                   | GET r éussi                            |
| 201 | Created               | POST création réussie                  |
| 204 | No Content            | DELETE réussi                          |
| 400 | Bad Request           | JSON malformé                          |
| 401 | Unauthorized          | Token JWT manquant/invalide/expiré     |
| 403 | Forbidden             | Non admin pour route admin             |
| 404 | Not Found             | Ressource introuvable                  |
| 409 | Conflict              | Nom dossier existant, dernière version |
| 413 | Payload Too Large     | Fichier > limite                       |
| 422 | Unprocessable Entity  | Validation échouée                     |
| 429 | Too Many Requests     | Rate limit (v2)                        |
| 500 | Internal Server Error | Erreur serveur                         |

### Format des erreurs JSON

Toutes les erreurs suivent ce format standardisé :

```json
{
  "error": "Message d'erreur lisible par l'utilisateur",
  "code": "CODE_APPLICATIF"
}
```

**Exemples** :

```json
// 401 - Token invalide
{
  "error": "Token JWT invalide ou expiré",
  "code": "AUTH_INVALID_TOKEN"
}

// 403 - Auto-suppression
{
  "error": "Vous ne pouvez pas supprimer votre propre compte",
  "code": "ADMIN_CANNOT_DELETE_SELF"
}

// 404 - Ressource introuvable
{
  "error": "Fichier introuvable",
  "code": "FILE_NOT_FOUND"
}

// 409 - Dernière version
{
  "error": "Impossible de supprimer la dernière version d'un fichier",
  "code": "CANNOT_DELETE_LAST_VERSION"
}

// 413 - Fichier trop gros
{
  "error": "Fichier trop volumineux (max 100 Mo)",
  "code": "FILE_TOO_LARGE"
}
```

---

##  Sécurité

### Authentification JWT (Firebase PHP-JWT)

**Configuration** (.env) :
```ini
JWT_SECRET="votre_secret_ultra_long_et_aleatoire_ici"
```

**Implémentation** : Voir `src/Security/AuthService.php`

**Format du payload JWT** :
```json
{
  "iss": "coffre-fort-api",
  "sub": 1,
  "email": "user@example.com",
  "is_admin": false,
  "iat": 1707675000,
  "exp": 1707678600
}
```

**Utilisation dans les controllers** :
```php
// Récupérer l'utilisateur authentifié
$user = $this->auth->getAuthenticatedUserFromToken($request);
// $user contient: ['id', 'email', 'is_admin', ...]
```

**Gestion des erreurs** :
- 401 : Token manquant ou invalide
- 404 : Utilisateur introuvable
- 500 : JWT_SECRET non configuré

---

### Chiffrement des fichiers (AES-256-GCM)

**Implémentation** : `src/Security/FileCrypto.php`

#### Architecture du chiffrement

```
┌─────────────────────────────────────────────────────────┐
│  UPLOAD : Fichier plaintext                             │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  FileCrypto::encryptForStorage()                        │
│                                                         │
│  1. Génération clé aléatoire (fileKey: 32 bytes)        │
│     + IV aléatoire (12 bytes)                           │
│                                                         │
│  2. Chiffrement AES-256-GCM:                            │
│     plaintext + fileKey + IV → ciphertext + auth_tag    │
│     AAD: "file:{fileId}:v{version}"                     │
│                                                         │
│  3. Key Wrapping (enveloppe de clé):                    │
│     fileKey + KEK → wrappedKey                          │
│     AAD: "filekey:{fileId}:v{version}"                  │
│     key_envelope = envIv || envTag || wrappedKey        │
│                                                         │
│  4. Checksum: SHA-256(ciphertext)                       │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  STOCKAGE                                               │
│                                                         │
│  BDD (file_versions):                                   │
│  - iv (12 bytes)                                        │
│  - auth_tag (16 bytes)                                  │
│  - key_envelope (envIv||envTag||wrappedKey)             │
│  - checksum (SHA-256)                                   │
│                                                         │
│  Disque (storage/files/...):                            │
│  - ciphertext (fichier chiffré)                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  DOWNLOAD                                               │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  FileCrypto::decryptFromStorage()                       │
│                                                         │
│  1. Parse key_envelope → envIv, envTag, wrappedKey      │
│                                                         │
│  2. Unwrap fileKey:                                     │
│     wrappedKey + KEK + envIv + envTag → fileKey         │
│     AAD: "filekey:{fileId}:v{version}"                  │
│                                                         │
│  3. Déchiffrement AES-256-GCM:                          │
│     ciphertext + fileKey + iv + auth_tag → plaintex     │
│     AAD: "file:{fileId}:v{version}"                     │
│                                                         │
│  4. Vérification checksum: SHA-256(ciphertext)          │
└──────────────┬──────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────┐
│  Fichier original (plaintext) renvoyé au client         │
└─────────────────────────────────────────────────────────┘
```

#### Points clés de sécurité

- **AES-256-GCM** : Chiffrement authentifié (confidentialité + intégrité)  
- **IV unique** : 12 bytes aléatoires pour chaque chiffrement  
- **Auth Tag** : 16 bytes garantissant l'intégrité  
- **AAD** : Additional Authenticated Data lie le chiffrement au contexte (fileId, version)  
- **Key Wrapping** : fileKey protégée par KEK serveur (AES-256-GCM)  
- **Checksum SHA-256** : Vérification supplémentaire du ciphertext  
- **Clé par fichier** : Isolation complète entre fichiers

#### Configuration (.env)

```ini
# Clé de chiffrement principale (≥32 caractères)
KEY_ENCRYPTION_KEY="votre_kek_de_32_octets_minimum_securisee"
```

---

### Tokens de partage sécurisés

**Implémentation** : `src/Security/ShareToken.php`

**Génération** :
```php
// Token base64url (~43 caractères)
$token = ShareToken::randomToken(32);
// Exemple: "abc123def456ghi789jkl012mno345pqr678stu901vwx234"
```

**Caractéristiques** :
-  Base64url (RFC 4648) : URL-safe, pas de caractères spéciaux
-  Aléatoire cryptographiquement : `random_bytes(32)` = 256 bits d'entropie
-  Unique en BDD : contrainte UNIQUE sur `shares.token`
-  Non prévisible

**Validation** :
```php
// Vérifications
if ($share['is_revoked']) {
    // 403 Forbidden
}
if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
    // 410 Gone : expiré
}
if ($share['remaining_uses'] !== null && $share['remaining_uses'] <= 0) {
    // 410 Gone : usages épuisés
}
```

**Décrément atomique** :
```php
// Décrémenter remaining_uses de manière atomique (évite race conditions)
$db->update('shares', [
    'remaining_uses[-]' => 1
], [
    'id' => $shareId,
    'remaining_uses[>]' => 0
]);
```

---

### Suppression CASCADE sécurisée

Lors de la suppression d'un utilisateur par un admin :

**1. Vérifications préalables** :
```php
if (!$currentUser['is_admin']) {
    throw new \Exception('Accès interdit', 403);
}
if ($currentUser['id'] === $userIdToDelete) {
    throw new \Exception('Vous ne pouvez pas supprimer votre propre compte', 403);
}
```

**2. Suppression fichiers physiques** (AVANT suppression BDD) :
```php
$files = $fileRepo->listFilesByUser($userId);
foreach ($files as $file) {
    $path = $storagePath . '/' . $file['stored_name'];
    if (file_exists($path)) {
        unlink($path);
    }
}
```

**3. Suppression BDD** (CASCADE automatique) :
```sql
DELETE FROM users WHERE id = ?

-- Supprime automatiquement via CASCADE :
-- ├─ folders (ON DELETE CASCADE)
-- │  └─ sous-dossiers (fk_folders_parent)
-- ├─ files (ON DELETE CASCADE)
-- │  └─ file_versions (fk_file_versions_file)
-- └─ shares (ON DELETE CASCADE)
--    └─ downloads_log (fk_downloads_share)
```

---

### Gestion des mots de passe

```php
// Inscription (hachage sécurisé)
$hash = password_hash($password, PASSWORD_DEFAULT);
// Utilise Bcrypt par défaut

// Login (vérification)
if (password_verify($password, $user['pass_hash'])) {
    //  Mot de passe correct
} else {
    //  Mot de passe incorrect
}
```

---

##  Tests

### Collection Postman (38 routes testées)

**Importer la collection** :
```bash
tests/coffre-fort-numerique-projet.postman_collection_V2.json
```

**Variables d'environnement** :
```json
{
  "base_url": "http://localhost:9081",
  "token": "",
  "user_id": "",
  "file_id": "",
  "share_token": ""
}
```

### Scénarios de test

#### 1. Auth - Register & Login
```bash
POST /auth/register  # 201 Created
POST /auth/login     # 200 OK + JWT token
```

#### 2. Folders - CRUD
```bash
POST /folders        # 201 Created
GET /folders         # 200 OK
PUT /folders/{id}    # 200 OK (rename)
DELETE /folders/{id} # 204 No Content
```

#### 3. Files - Upload & Versions (chiffrement automatique)
```bash
POST /files                    # 201 Created (v1, chiffré AES-256-GCM)
POST /files/{id}/versions      # 201 Created (v2)
GET /files/{id}/versions       # 200 OK (liste)
DELETE /files/{id}/versions/1  # 409 si dernière version
```

#### 4. Shares - Create & Download
```bash
POST /shares                # 201 Created
GET /shares                 # 200 OK
POST /shares/{id}/revoke    # 204 No Content
GET /s/{token}              # 200 OK (public)
POST /s/{token}/download    # 200 OK + décrément remaining_uses
```

#### 5. Admin - Delete User
```bash
DELETE /admin/users/{id}  # 204 No Content (si admin)
                          # 403 si non admin
                          # 403 si auto-suppression
```

### Cas d'erreurs testés

| Test              | Requête                            | Code | Message                                       |
|-------------------|------------------------------------|------|-----------------------------------------------|
| Token manquant    | GET /folders sans Auth             | 401  | "Token manquant"                              |
| Token invalide    | GET /folders (mauvais token)       | 401  | "Token invalide"                              |
| Non admin         | DELETE /admin/users/{id} (user)    | 403  | "Accès interdit"                              |
| Auto-suppression  | DELETE /admin/users/{self}         | 403  | "Impossible de supprimer votre propre compte" |
| Dernière version  | DELETE /files/{id}/versions/{last} | 409  | "Impossible de supprimer la dernière version" |
| Parent inexistant | POST /folders (parent_id=999)      | 404  | "Dossier parent introuvable"                  |
| Fichier trop gros | POST /files (>100Mo)               | 413  | "Fichier trop volumineux"                     |

---

##  Déploiement

### Docker Compose (développement et production)

#### Démarrage

```bash
# Build et démarrage
docker-compose up -d --build

# Vérifier les logs
docker-compose logs -f web
docker logs coffreFort-web

# Vérifier les conteneurs actifs
docker ps
```

#### Services disponibles

- **API Backend** : `http://localhost:9081`
- **phpMyAdmin** : `http://localhost:8081`
- **MySQL** : `localhost:3306` (accessible depuis l'hôte)

#### Arrêt et nettoyage

```bash
# Arrêter les conteneurs
docker-compose down

# Arrêter ET supprimer les volumes (⚠️ perte de données)
docker-compose down -v
```

---

##  Contribution

### Workflow Git

```bash
# Créer une branche feature
git checkout -b feature/nom-fonctionnalite

# Faire vos modifications
git add .
git commit -m "feat: description de la fonctionnalité"

# Pousser
git push origin feature/nom-fonctionnalite
```

### Commandes Git utiles

```bash
# Retirer fichiers du staging
git restore --staged <fichier>

# Voir les branches
git branch -a

# Supprimer vendor et lock (si problème Composer)
rm -rf vendor composer.lock
composer install
```

### Conventions de commit

- `feat:` nouvelle fonctionnalité
- `fix:` correction de bug
- `docs:` documentation
- `refactor:` refactoring
- `test:` ajout/modification de tests
- `chore:` maintenance

---

##  Problèmes connus et solutions

### 1. Medoo non reconnu après `composer init`

**Problème** : Erreur "Class 'Medoo\Medoo' not found"

**Solution** :
```bash
rm -rf vendor composer.lock
composer require slim/slim:"4.12" slim/psr7:"1.8" catfan/medoo:"2.2"
composer update
```

Vérifier `composer.json` :
```json
{
  "require": {
    "slim/slim": "4.12",
    "slim/psr7": "1.8",
    "catfan/medoo": "2.2",
    "firebase/php-jwt": "^6.11"
  }
}
```

### 2. Upload fonctionne en `test-upload.html` mais pas dans Postman

**Problème** : Erreur `400 Bad Request` d'Apache

**Cause** : Limite de taille d'upload

**Solution** :

Dans `FileController.php` :
```php
private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 Mo
```

Ou créer `docker/php.ini` :
```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
```

Modifier `docker-compose.yml` :
```yaml
web:
  volumes:
    - ./docker/php.ini:/usr/local/etc/php/conf.d/uploads.ini
```

Rebuild :
```bash
docker-compose down
docker-compose up -d --build
```

### 3. Contrainte FOREIGN KEY sur `parent_id`
#### Cette fonctionnalité n'est pas implimenté encore (v2)
**Problème** : Erreur lors de création de dossier racine

**Solution** : `parent_id` doit être `NULL` pour les dossiers racines

```json
{
  "name": "Racine",
  "parent_id": null  // ← NULL, pas 0
}
```

### 4. Token JWT expire trop vite

**Problème** : Déconnexion après 15 minutes

**Solution** :

Modifier dans `UserController::login()` :
```php
'exp' => time() + 3600,  // 1 heure au lieu de 900 secondes
```

Ou implémenter un refresh token (v2).

### 5. Triggers et accès root MySQL (Docker)

**Problème** : Impossible de créer des triggers sans root

**Solution** :

Utiliser `root` dans `.env` :
```ini
DB_USER=root
DB_PASSWORD=root
```

Se connecter au conteneur :
```bash
docker exec -it coffreFort-mysql mysql -uroot -proot coffreFort
```

---

##  Ressources

### Documentation officielle

- [Slim Framework 4](https://www.slimframework.com/docs/v4/)
- [Medoo](https://medoo.in/doc)
- [Firebase PHP-JWT](https://github.com/firebase/php-jwt)
- [PHP Manual](https://www.php.net/manual/en/)
- [OpenSSL PHP](https://www.php.net/manual/en/book.openssl.php)

### Tutoriels et guides

- [REST API Best Practices](https://restfulapi.net/)
- [JWT.io](https://jwt.io/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [AES-GCM Explained](https://en.wikipedia.org/wiki/Galois/Counter_Mode)

### Outils

- [Postman](https://www.postman.com/)
- [Swagger Editor](https://editor.swagger.io/)
- [Docker Hub](https://hub.docker.com/)
- [phpMyAdmin](https://www.phpmyadmin.net/)

---

## Équipe

- **Backend Lead** : Klaudia Juhasz
- **JavaFX Developer** : Klaudia Juhasz
- **Web Developer** : Denys LYULCHAK

---

##  Roadmap

### Version 1.0 (MVP) 
- [x] Auth JWT (register, login)
- [x] CRUD dossiers/fichiers
- [x] Chiffrement AES-256-GCM
- [x] Versionnage fichiers
- [x] Partages contrôlés (token base64url)
- [x] Quotas utilisateurs
- [x] Rôle admin (suppression users)
- [x] Journalisation téléchargements
- [x] Table audit_logs
- [x] 38 routes Postman testées
- [x] CASCADE automatique

### Version 2.0 (En cours)
- [ ] Protection bruteforce (rate limiting)
- [ ] Mentions légales JavaFX
- [ ] Déplacement dossiers/fichiers
- [ ] Amélioration barre de progression JavaFX
- [ ] Triggers supplémentaires pour audit_logs
- [ ] Fonctionnalité "Mot de passe oublié" (réinitialisation par email)
- [ ] Renforcement politique mots de passe

### Version 3.0 (Futur)
- [ ] Déchiffrement côté client (zero-knowledge)
- [ ] 2FA (TOTP)
- [ ] Prévisualisation fichiers
- [ ] Notifications email
- [ ] Webhooks
- [ ] API de statistiques avancées
- [ ] Export audit_logs (CSV/JSON)

---

##  Support

Pour toute question ou problème :

- **Issues GitHub** : [https://github.com/PlumCreativ/coffreFort/issues](https://github.com/PlumCreativ/coffreFort/issues)

---

**Dernière mise à jour** : 12 février 2026  
**Version API** : 1.0.0  
**Routes testées** : 38/38 
