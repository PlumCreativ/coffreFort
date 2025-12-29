# Corrections des Contraintes SQL

## Date
2025-12-29

## Problèmes identifiés

### 1. Incohérences de types entre clés étrangères et colonnes référencées

Les types de données entre les colonnes de clés étrangères et les colonnes référencées ne correspondaient pas, ce qui causait des erreurs lors de la création des contraintes.

**Problèmes spécifiques:**
- `users.id` était `INT UNSIGNED` alors que `folders.user_id` et `files.user_id` étaient `BIGINT UNSIGNED`
- `shares.id` était `INT UNSIGNED` alors que `downloads_log.share_id` devrait être du même type
- `shares.target_id` était `INT UNSIGNED` mais devait référencer des IDs de `files` et `folders` qui sont `BIGINT UNSIGNED`
- `file_versions.id` était `BIGINT UNSIGNED` mais `downloads_log.version_id` était `INT UNSIGNED`

### 2. Index dupliqué

L'index `idx_shares_token` était créé deux fois:
- Une fois à la ligne 70 après un `CREATE INDEX`
- Une deuxième fois à la ligne 90 dans la section des index utiles

### 3. ALTER TABLE immédiatement après CREATE TABLE

La colonne `token_sig` était ajoutée via un `ALTER TABLE` immédiatement après la création de la table `shares`, ce qui est une mauvaise pratique.

### 4. Incompatibilité avec Docker

Le script SQL contenait `CREATE DATABASE` et `USE` alors que Docker crée automatiquement la base de données via la variable `MYSQL_DATABASE`.

## Solutions appliquées

### 1. Uniformisation des types de données

Tous les IDs ont été uniformisés en `BIGINT UNSIGNED` pour assurer la cohérence:

```sql
-- Table users
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ...
);

-- Table shares
CREATE TABLE IF NOT EXISTS `shares` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  ...
);

-- Table downloads_log
CREATE TABLE `downloads_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `share_id` BIGINT UNSIGNED NOT NULL,
  `version_id` BIGINT UNSIGNED NULL,
  ...
);
```

### 2. Suppression de l'index dupliqué

L'index `idx_shares_token` est maintenant créé une seule fois, directement dans la définition de la table `shares`:

```sql
CREATE TABLE IF NOT EXISTS `shares` (
  ...
  INDEX idx_shares_token (token)
);
```

### 3. Intégration de token_sig dans CREATE TABLE

La colonne `token_sig` est maintenant définie directement dans le `CREATE TABLE`:

```sql
CREATE TABLE IF NOT EXISTS `shares` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `kind` ENUM('file', 'folder') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL UNIQUE,
  `token_sig` CHAR(64) NOT NULL,  -- Ajouté directement
  ...
);
```

### 4. Adaptation pour Docker

Les commandes `CREATE DATABASE` et `USE` ont été commentées car Docker les gère automatiquement:

```sql
-- Base de données créée automatiquement par Docker via MYSQL_DATABASE
-- USE `coffreFort`;
```

### 5. Ajout de CREATE TABLE IF NOT EXISTS

Pour rendre le script idempotent et éviter les erreurs si les tables existent déjà:

```sql
CREATE TABLE IF NOT EXISTS `users` (...);
CREATE TABLE IF NOT EXISTS `folders` (...);
-- etc.
```

## Tests effectués

### Test de création du schéma
✅ Toutes les tables ont été créées avec succès

### Test des contraintes de clés étrangères
✅ Toutes les 8 contraintes de clés étrangères ont été créées correctement:
- `fk_folders_user` (folders.user_id → users.id)
- `fk_folders_parent` (folders.parent_id → folders.id)
- `fk_files_user` (files.user_id → users.id)
- `fk_files_folder` (files.folder_id → folders.id)
- `fk_file_versions_file` (file_versions.file_id → files.id)
- `fk_shares_user` (shares.user_id → users.id)
- `fk_downloads_share` (downloads_log.share_id → shares.id)
- `fk_downloads_version` (downloads_log.version_id → file_versions.id)

### Test de cohérence des types
✅ Tous les types de colonnes correspondent entre les clés étrangères et les colonnes référencées (tous en `bigint unsigned`)

### Test CASCADE DELETE
✅ La suppression d'un utilisateur supprime en cascade:
- Ses dossiers
- Ses fichiers
- Les versions de fichiers
- Ses partages
- Les logs de téléchargement

## Impact sur le code PHP

### Compatibilité
✅ Le code PHP existant utilise des cast `(int)` partout, ce qui fonctionne correctement avec `BIGINT`:
- PHP PDO gère automatiquement la conversion entre BIGINT MySQL et int/string PHP
- Medoo supporte les types BIGINT sans problème
- Les valeurs `BIGINT UNSIGNED` de MySQL (jusqu'à 2^64-1) sont gérées en PHP comme des entiers ou des strings selon la taille

### Aucune modification nécessaire
Aucun changement n'est requis dans les fichiers PHP car:
1. Les signatures de méthode utilisent déjà `int` comme type hint (ce qui est compatible)
2. Les casts `(int)` sont présents dans tout le code
3. PDO/Medoo gèrent la conversion automatiquement

## Bénéfices

1. **Cohérence**: Tous les IDs utilisent le même type de données
2. **Scalabilité**: `BIGINT UNSIGNED` permet de stocker jusqu'à 18,446,744,073,709,551,615 enregistrements
3. **Performance**: Les index fonctionnent mieux avec des types cohérents
4. **Fiabilité**: Les contraintes d'intégrité référentielle peuvent être appliquées correctement
5. **Maintenabilité**: Le script SQL est plus propre et plus facile à maintenir
6. **Compatibilité Docker**: Le script fonctionne directement avec docker-compose

## Recommandations

1. **Sauvegarde**: Toujours faire une sauvegarde avant d'appliquer ce script sur une base existante
2. **Migration**: Si des données existent, créer un script de migration pour:
   - Modifier les types de colonnes
   - Recréer les contraintes de clés étrangères
3. **Documentation**: Mettre à jour la documentation du modèle de données avec les nouveaux types
