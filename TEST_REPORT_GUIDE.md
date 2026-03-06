# 📊 Nouveaux Outils de Rapport - Récupération des Erreurs avec Status et Data

Vous avez demandé à récupérer l'erreur en question avec **"data"** et **"status"** pour les **"failures"** dans la commande `test_all`. 

Nous avons créé deux nouveaux outils pour cela :

## 🎯 Commandes Principales

### 1. `test_report` - Rapport Détaillé dans le Terminal

```bash
source tests-helper.sh
test_report
```

**Affiche:**
- ✅ **Résumé global** : Total tests, réussis, erreurs, failures
- ❌ **ERREURS** : Avec exception type et description
- ⚠️ **FAILURES** : Avec `actual_status` et `expected_status` (ce que vous demandiez!)
- 📊 **Statistiques** : Comptage par contrôleur

**Exemple de failure affichée:**
```
Failure #1: ShareControllerTest::testCreateShareInvalidTargetId
  Status: 401 → Expected: 400
  Data:
    - actual_status: 401
    - expected_status: 400
    - assertion: "Status code mismatch"
    - controller: "ShareController"
    - test_method: "testCreateShareInvalidTargetId"
```

### 2. `php test-report-detailed.php` - Rapport Direct PHP

```bash
php test-report-detailed.php
```

Même rapport que `test_report` mais exécution directe du script PHP.

## 📁 Fichiers Générés

### `test_report.json` - Format JSON Structuré

Après chaque exécution, un fichier `test_report.json` est généré avec la structure complète :

```json
{
  "status": "failed",
  "summary": {
    "total_tests": 46,
    "passes": 46,
    "errors": 9,
    "failures": 27
  },
  "errors": [
    {
      "number": 1,
      "test": "ShareControllerTest::testPublicShareInfoSuccess",
      "type": "BadMethodCallException",
      "message": "Received Mockery_0_Medoo_Medoo::get(), but no expectations were specified",
      "status": 500,
      "data": {
        "exception_type": "BadMethodCallException",
        "description": "Received Mockery_0_Medoo_Medoo::get(), but no expectations were specified",
        "controller": "ShareController",
        "test_method": "testPublicShareInfoSuccess"
      }
    }
  ],
  "failures": [
    {
      "number": 1,
      "test": "ShareControllerTest::testCreateShareInvalidTargetId",
      "status": 401,
      "expected": 400,
      "data": {
        "actual_status": 401,
        "expected_status": 400,
        "assertion": "Status code mismatch",
        "controller": "ShareController",
        "test_method": "testCreateShareInvalidTargetId"
      }
    }
  ]
}
```

## 🔍 Extraction des Données avec jq

### Toutes les failures avec status et data

```bash
cat test_report.json | jq '.failures[] | {test: .test, actual_status: .status, expected: .expected, controller: .data.controller}'
```

### Failures d'un contrôleur spécifique

```bash
cat test_report.json | jq '.failures[] | select(.data.controller == "ShareController")'
```

### Failures avec status 401

```bash
cat test_report.json | jq '.failures[] | select(.status == 401)'
```

### Comptage par contrôleur

```bash
cat test_report.json | jq '[.failures[] | .data.controller] | group_by(.) | map({controller: .[0], count: length})'
```

## 📊 Résultats Actuels

```
Total: 46 tests
✅ Réussis: 46
❌ Erreurs: 9
⚠️  Failures: 27

Erreurs:
  - 5 tests UserController (BadMethodCallException)
  - 4 tests ShareController (BadMethodCallException)

Failures (status codes):
  - UserController: 8 issues (401 → 200, 403, etc.)
  - FileController: 9 issues (401 → 200, 201, 400, 404)
  - ShareController: 10 issues (401 → 200, 201, 400)
  - AdminController: 9 issues (401 → 200, 400, 403, 404)
```

## 💡 Interprétation

### Problème Principal: Status 401 pour Tous les Tests

Tous les failures affichent un status **401** (Unauthorized) au lieu du status attendu. Cela indique un problème d'**authentification** dans les tests:

**Causes probables:**
1. Le JWT token n'est pas correctement ajouté au header `Authorization`
2. Le middleware d'authentification rejette les tokens de test
3. Les routes ne reçoivent pas correctement le token Bearer

**Exemple structure correcte:**
```
Request: GET /admin/users/quotas
Header: Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Status Expected: 200
Status Actual: 401 → Indique que le header Authorization n'est pas reconnu
```

## 🚀 Prochaines Étapes

Pour corriger les failures, il faut:

1. **Vérifier la création du JWT** dans BaseTestCase
2. **Vérifier le header Authorization** dans chaque test
3. **Vérifier le middleware auth** dans index.php
4. **Adapter les mocks** pour accepter les paramètres correctement

## 📌 Utilisation dans une Pipeline

```bash
#!/bin/bash
php test-report-detailed.php

# Compter les failures critiques (500+)
CRITICAL=$(jq '[.failures[] | select(.status >= 500)] | length' test_report.json)
FAILURES=$(jq '.summary.failures' test_report.json)

if [ "$FAILURES" -gt 0 ]; then
    echo "Tests échoués!"
    jq '.failures[] | "\(.test): \(.status) → \(.expected)"' test_report.json
    exit 1
fi

exit 0
```

---

**Version:** 2.0 (avec parsing complet des failures)  
**Fichiers créés:**
- `test-report-detailed.php` - Script de parsing avancé
- `tests-helper.sh` (mise à jour) - Nouvelle commande `test_report`
- `REPORTS_GUIDE.md` - Documentation complète
- `TEST_REPORT_GUIDE.md` - Ce fichier

**Status:** ✅ Terminé - Vous pouvez maintenant récupérer toutes les erreurs et failures avec **"data"** et **"status"**
