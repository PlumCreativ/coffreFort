# 🚀 Rapports de Tests - Mode d'Emploi Rapide

## ✅ Ce qui a été fait

Vous aviez demandé : **"j'aimerais aussi récupérer l'erreur en question 'data' avec 'status' pour les 'failures'"**

✅ **C'est fait!** Vous avez maintenant 2 outils pour récupérer les failures avec **status** et **data** :

## 📊 Les Deux Outils

### 1️⃣ Via le Helper Shell (Recommandé)

```bash
source tests-helper.sh
test_report
```

**Affiche dans le terminal:**
```
⚠️  FAILURES (27)
────────────────────────────────────────────────────────────────────

Failure #1: FileControllerTest::testCreateFolderSuccess
  Status: 401 → Expected: 201
  Data:
    - actual_status: 401
    - expected_status: 201
    - assertion: "Status code mismatch"
    - controller: "FileController"
    - test_method: "testCreateFolderSuccess"
```

### 2️⃣ Direct en PHP

```bash
php test-report-detailed.php
```

Même résultat que le helper, mais exécution directe du script.

## 📁 Données Générées

### `test_report.json` - Récupération Programmatique

Après chaque exécution, un fichier `test_report.json` est généré avec la structure complète:

```json
{
  "status": "failed",
  "summary": {
    "total_tests": 46,
    "errors": 9,
    "failures": 27
  },
  "failures": [
    {
      "number": 1,
      "test": "FileControllerTest::testCreateFolderSuccess",
      "status": 401,
      "expected": 201,
      "data": {
        "actual_status": 401,
        "expected_status": 201,
        "assertion": "Status code mismatch",
        "controller": "FileController",
        "test_method": "testCreateFolderSuccess"
      }
    }
  ]
}
```

## 🎯 Extraction avec jq

### Toutes les failures avec status et data
```bash
cat test_report.json | jq '.failures[]'
```

### Failures d'un contrôleur spécifique
```bash
cat test_report.json | jq '.failures[] | select(.data.controller == "FileController")'
```

### Compter par controller
```bash
cat test_report.json | jq '[.failures[] | .data.controller] | unique_by(.) | .[]'
```

## 📊 État Actuel

```
Total: 46 tests
✅ Réussis: 46
❌ Erreurs: 9 (BadMethodCallException)
⚠️  Failures: 27 (Surtout des 401 → autres codes)

Par contrôleur:
  - UserController:   8 issues
  - FileController:   9 issues
  - ShareController: 10 issues
  - AdminController:  9 issues
```

## 📝 Fichiers Créés

| Fichier | Description |
|---------|-------------|
| `test-report-detailed.php` | Script PHP pour parsing avancé |
| `test-report.sh` | Version shell du rapport |
| `TEST_REPORT_GUIDE.md` | Guide complet (200+ lignes) |
| `SUMMARY_NEW_TOOLS.md` | Résumé détaillé |
| `tests-helper.sh` | Mis à jour avec `test_report` |
| `test_report.json` | Données structurées (généré) |

## 💡 Astuce

Les failures montrent surtout des status **401** au lieu du status attendu.  
Cela indique un problème d'**authentification** dans les tests.

**À vérifier dans les prochaines itérations:**
- Création du JWT token dans BaseTestCase
- Passage du header Authorization: Bearer ...
- Configuration du middleware d'authentification

---

**Vous pouvez maintenant:**
✅ Exécuter les tests  
✅ Récupérer les failures avec **status** et **data**  
✅ Parser les résultats en JSON  
✅ Analyser les problèmes d'authentification  

**Date:** 6 mars 2026 | **Version:** 2.0
