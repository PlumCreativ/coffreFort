# Tests Unitaires - Coffre-Fort Digital

## 📋 Vue d'ensemble

Ce projet inclut une suite complète de tests unitaires couvrant les 4 contrôleurs principaux :
- **UserController** - Authentification et gestion des utilisateurs
- **FileController** - Gestion des fichiers et des dossiers
- **ShareController** - Gestion des partages publics et privés
- **AdminController** - Outils d'administration

## 🎯 Routes testées

### UserController (12 tests)
```
POST   /auth/register         ✅ Inscription réussie / Email invalide / Mot de passe court / Email existant
POST   /auth/login            ✅ Connexion réussie / Email non trouvé / Mot de passe incorrect
GET    /users                 ✅ Admin OK / Non-admin refusé
GET    /users/{id}            ✅ Admin accès
GET    /dashboard             ✅ JWT valide / Sans JWT
```

### FileController (18 tests)
```
GET    /files                 ✅ Lister avec authentification / Sans authentification
GET    /files/{id}            ✅ Détails du fichier / Propriétaire uniquement
GET    /folders               ✅ Lister les dossiers
POST   /folders               ✅ Créer dossier / Validation nom
DELETE /folders/{id}          ✅ Supprimer dossier
PUT    /folders/{id}          ✅ Renommer dossier
GET    /files/{id}/versions   ✅ Lister les versions
GET    /me/quota              ✅ Récupérer le quota utilisateur
```

### ShareController (15 tests)
```
POST   /shares                ✅ Créer partage / Validation kind / Validation target_id
GET    /shares                ✅ Lister les partages
GET    /shares/{id}           ✅ Détails partage / ID invalide
DELETE /shares/{id}           ✅ Supprimer partage
PATCH  /shares/{id}/revoke    ✅ Révoquer partage
GET    /s/{token}             ✅ Infos publiques / Token invalide / Token vide
GET    /s/{token}/versions    ✅ Versions publiques / Versions non autorisées
```

### AdminController (14 tests)
```
GET    /admin/users/quotas              ✅ Admin OK / Non-admin refusé / Sans authentification
PUT    /admin/users/{id}/quota          ✅ Modifier quota / Quota < espace utilisé / Non-admin
DELETE /admin/users/{id}                ✅ Supprimer / Protéger compte admin / Non-admin
```

## 🚀 Installation et exécution

### 1. Installation des dépendances
```bash
composer install
```

Cela installe :
- **PHPUnit 11** - Framework de test
- **Mockery 1.6** - Mocking library pour simuler les objets

### 2. Exécuter tous les tests
```bash
./vendor/bin/phpunit
```

### 3. Exécuter les tests d'une classe spécifique
```bash
./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/FileControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/ShareControllerTest.php
./vendor/bin/phpunit tests/unit/Controller/AdminControllerTest.php
```

### 4. Exécuter un test spécifique
```bash
./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php --filter testRegisterSuccess
```

### 5. Afficher la couverture de code
```bash
./vendor/bin/phpunit --coverage-html coverage
```

Puis ouvrir `coverage/index.html` dans le navigateur.

## 📊 Structure des tests

```
tests/
├── unit/
│   ├── BaseTestCase.php                 # Classe de base avec utilitaires
│   └── Controller/
│       ├── UserControllerTest.php       # 12 tests
│       ├── FileControllerTest.php       # 18 tests
│       ├── ShareControllerTest.php      # 15 tests
│       └── AdminControllerTest.php      # 14 tests
```

## 🛠️ Utilitaires de test (BaseTestCase)

### Créer des requêtes
```php
// Requête GET
$request = $this->createGetRequest('/files');

// Requête POST avec JSON
$request = $this->createPostRequest('/auth/login', [
    'email' => 'test@example.com',
    'password' => 'password123'
]);

// Requête PUT
$request = $this->createPutRequest('/folders/1', ['name' => 'New Name']);

// Requête DELETE
$request = $this->createDeleteRequest('/files/1');

// Requête PATCH
$request = $this->createPatchRequest('/shares/100/revoke', []);
```

### Ajouter un token JWT
```php
$token = $this->createValidJwt();
$request = $this->createRequestWithToken($request, $token);
```

### Créer une réponse
```php
$response = $this->createResponse();
```

### Décoder le JSON de la réponse
```php
$data = $this->getResponseData($response);
// Maintenant $data est un tableau PHP
```

## 🔐 Authentification dans les tests

### Token administrateur
```php
$token = $this->createAdminJwt(userId: 1);
// Crée un JWT valide pour un admin avec l'ID 1
```

### Token utilisateur normal
```php
$token = $this->createUserJwt(userId: 2);
// Crée un JWT valide pour un utilisateur normal avec l'ID 2
```

## 📝 Exemple de test complet

```php
public function testLoginSuccess(): void
{
    // Données de test
    $email = 'test@example.com';
    $password = 'SecurePassword123!';
    
    // Créer la requête
    $request = $this->createPostRequest('/auth/login', [
        'email' => $email,
        'password' => $password
    ]);
    
    // Créer la réponse (vide)
    $response = $this->createResponse();
    
    // Exécuter le contrôleur
    $result = $this->userController->login($request, $response);
    
    // Vérifications
    $this->assertEquals(200, $result->getStatusCode());
    
    // Décoder la réponse JSON
    $data = $this->getResponseData($result);
    
    // Vérifier les champs
    $this->assertArrayHasKey('jwt', $data);
    $this->assertNotEmpty($data['jwt']);
}
```

## ⚠️ Notes importantes

### Mocking de la base de données
Les tests utilisent **Mockery** pour simuler la base de données :

```php
// Mock Medoo
$this->database = m::mock('Medoo\Medoo');

// Configurer le mock
$this->database->shouldReceive('select')
    ->andReturn([[
        'id' => 1,
        'email' => 'test@example.com'
    ]]);
```

### Conventions
- Tous les tests héritent de `BaseTestCase`
- Les noms des tests commencent par `test`
- Les noms décrivent ce qui est testé
- Chaque test teste un seul comportement

### Variables d'environnement
Les variables suivantes sont configurées dans `phpunit.xml` :
- `JWT_SECRET` = "test-secret-key-for-testing-only"
- `SHARE_SECRET` = "test-share-secret"
- `KEY_ENCRYPTION_KEY` = "0123456789abcdef0123456789abcdef"
- `APP_PUBLIC_BASE_URL` = "http://localhost:8080"

## 🔄 Intégration CI/CD

Pour intégrer dans GitHub Actions (créer `.github/workflows/tests.yml`) :

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3']
    
    steps:
    - uses: actions/checkout@v3
    - uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
    
    - run: composer install
    - run: ./vendor/bin/phpunit
```

## 📚 Ressources

- [PHPUnit Documentation](https://docs.phpunit.de/en/11.0/)
- [Mockery Documentation](https://docs.mockery.io/)
- [Slim Framework Testing Guide](https://www.slimframework.com/)

## 👨‍💻 Maintien des tests

Quand ajouter/modifier des tests :
1. **Ajouter une route** → Ajouter un test pour la route
2. **Modifier une route** → Mettre à jour les tests existants
3. **Corriger un bug** → Créer un test qui reproduit le bug, puis corriger le code

## 📞 Questions?

Pour plus d'informations sur les tests, consultez :
- La classe `BaseTestCase.php` pour les utilitaires
- Les fichiers de test dans `tests/unit/Controller/`
- Le fichier de configuration `phpunit.xml`
