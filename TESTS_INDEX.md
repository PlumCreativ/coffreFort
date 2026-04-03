# 📑 Index des Tests Unitaires

## 📍 Navigation rapide

### 📊 Résumés
- [TEST_SUMMARY_EXECUTIVE.md](TEST_SUMMARY_EXECUTIVE.md) - Résumé exécutif complet
- [TESTS_SUMMARY.md](TESTS_SUMMARY.md) - Résumé détaillé des tests
- [docs/TESTS.md](docs/TESTS.md) - Documentation technique complète

### 🚀 Guides d'utilisation
- [QUICK_START_TESTS.sh](QUICK_START_TESTS.sh) - Guide de démarrage rapide (exécutable)
- [tests-helper.sh](tests-helper.sh) - Helper de commandes (source ./tests-helper.sh)
- [run-tests.sh](run-tests.sh) - Script d'exécution principal

### 📝 Fichiers de test
```
tests/unit/
├── BaseTestCase.php                    # ← Classe de base à utiliser pour tous les tests
└── Controller/
    ├── UserControllerTest.php          # 12 tests
    ├── FileControllerTest.php          # 18 tests
    ├── ShareControllerTest.php         # 15 tests
    └── AdminControllerTest.php         # 14 tests
```

### ⚙️ Configuration
- [phpunit.xml](phpunit.xml) - Configuration PHPUnit
- [composer.json](composer.json) - Dépendances (PHPUnit + Mockery)

---

## 🎯 Démarrage rapide en 3 étapes

### 1. Installation
```bash
composer install
```

### 2. Exécution
```bash
./vendor/bin/phpunit
```

### 3. Résultats
```
✅ 59 tests
✅ 4 contrôleurs
✅ 23+ routes
```

---

## 📚 Documentation par sujet

### Installation et configuration
1. Lire [QUICK_START_TESTS.sh](QUICK_START_TESTS.sh)
2. Exécuter `composer install`
3. Vérifier `phpunit.xml`

### Utilisation des tests
1. Lire le guide dans [docs/TESTS.md](docs/TESTS.md)
2. Regarder les exemples dans les fichiers de test
3. Utiliser le [tests-helper.sh](tests-helper.sh) pour les commandes

### Extension des tests
1. Consulter [tests/unit/BaseTestCase.php](tests/unit/BaseTestCase.php)
2. Copier un test existant comme template
3. Adapter les mocks pour votre cas

---

## 🛠️ Commandes courantes

```bash
# Tous les tests
./vendor/bin/phpunit

# Un contrôleur spécifique
./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php

# Un test spécifique
./vendor/bin/phpunit --filter testLoginSuccess

# Avec couverture
./vendor/bin/phpunit --coverage-html coverage

# Helper (plus facile)
source tests-helper.sh
test_all
test_controller UserController
test_specific testLoginSuccess
```

---

## 📊 Statistiques

| Élément | Nombre |
|---------|--------|
| Tests créés | 59 |
| Contrôleurs testés | 4 |
| Routes couvertes | 23+ |
| Fichiers de test | 5 |
| Fichiers de configuration | 2 |
| Fichiers de documentation | 4 |
| Scripts utilitaires | 3 |

---

## ✅ Checklist

- [x] PHPUnit et Mockery installés
- [x] Tests créés pour tous les contrôleurs
- [x] BaseTestCase avec utilitaires
- [x] Documentation complète
- [x] Scripts d'exécution
- [x] Prêt pour CI/CD

---

## 🔗 Structure du projet

```
coffreFort/
├── src/
│   ├── Controller/
│   │   ├── UserController.php      ← Tests: UserControllerTest.php
│   │   ├── FileController.php      ← Tests: FileControllerTest.php
│   │   ├── ShareController.php     ← Tests: ShareControllerTest.php
│   │   └── AdminController.php     ← Tests: AdminControllerTest.php
│   ├── Model/
│   ├── Security/
│   └── Helpers/
├── tests/
│   └── unit/
│       ├── BaseTestCase.php        ← Base pour tous les tests
│       └── Controller/
│           ├── UserControllerTest.php
│           ├── FileControllerTest.php
│           ├── ShareControllerTest.php
│           └── AdminControllerTest.php
├── docs/
│   └── TESTS.md                    ← Documentation technique
├── phpunit.xml                     ← Configuration
├── composer.json                   ← Dépendances
├── TEST_SUMMARY_EXECUTIVE.md       ← Résumé exécutif
├── TESTS_SUMMARY.md                ← Résumé détaillé
├── QUICK_START_TESTS.sh            ← Guide rapide
├── tests-helper.sh                 ← Helper de commandes
└── run-tests.sh                    ← Script d'exécution
```

---

## 🔍 Trouver un test spécifique

### Par fonctionnalité
- **Authentification** → `UserControllerTest.php`
- **Gestion des fichiers** → `FileControllerTest.php`
- **Partages** → `ShareControllerTest.php`
- **Administration** → `AdminControllerTest.php`

### Par route
```bash
grep -r "route_name" tests/unit/
grep -r "GET /files" tests/unit/Controller/FileControllerTest.php
```

### Par mot-clé
```bash
# Trouver tous les tests de validation
grep -r "Invalid\|validation" tests/unit/

# Trouver tous les tests d'authentification
grep -r "jwt\|token\|auth" tests/unit/
```

---

## 💡 Tips & Tricks

### Exécuter rapidement un test
```bash
source tests-helper.sh
test_specific testName
```

### Voir la couverture
```bash
./vendor/bin/phpunit --coverage-html coverage
open coverage/index.html
```

### Debug un test
```bash
./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php --verbose
```

### Ajouter un nouveau test
1. Copier un test existant
2. Renommer la méthode `testXxx`
3. Adapter les mocks et assertions
4. Exécuter : `./vendor/bin/phpunit --filter testXxx`

---

## 📖 Ressources supplémentaires

- [PHPUnit Docs](https://docs.phpunit.de/)
- [Mockery Docs](https://docs.mockery.io/)
- [Slim Framework](https://www.slimframework.com/)
- [PSR-7 HTTP Message](https://www.php-fig.org/psr/psr-7/)

---

**Prêt à tester! 🚀**

Commencez par :
```bash
composer install && ./vendor/bin/phpunit
```
