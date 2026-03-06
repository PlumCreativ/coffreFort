# 📊 Guide des Rapports de Tests

Ce guide explique comment utiliser les nouveaux outils de rapport pour identifier et corriger les erreurs et failures dans vos tests.

## 🎯 Commandes Disponibles

### 1. `test_all` - Exécution basique avec résumé

```bash
source tests-helper.sh
test_all
```

**Affiche:**
- Sortie complète de PHPUnit
- Résumé des erreurs et failures
- Statistiques par contrôleur

### 2. `test_report` - Rapport détaillé JSON

```bash
source tests-helper.sh
test_report
```

**Affiche:**
- ✅ Résumé global (total, réussis, erreurs, failures)
- ❌ **ERREURS** avec détails (status, exception, description)
- ⚠️ **FAILURES** avec status codes et données attendues
- 📊 Statistiques par contrôleur

**Génère aussi:**
- `test_report.json` - Rapport complet en JSON pour parsing

### 3. Direct PHP - Script de rapport

```bash
php test-report-detailed.php
```

Même résultat que `test_report` mais exécution directe du script PHP.

## 📋 Structure du Rapport

### Résumé
```
Total: 46 tests
✅ Réussis: 46
❌ Erreurs: 9
⚠️  Failures: 27
```

### Erreurs (Exceptions non gérées)
```
Error #1: ShareControllerTest::testPublicShareInfoSuccess
  Status: BadMethodCallException
  Data:
    - Exception: "BadMethodCallException"
    - Description: "Received Mockery_0_Medoo_Medoo::get(), but no expectations were specified"
    - Controller: "ShareController"
    - Method: "testPublicShareInfoSuccess"
```

### Failures (Assertions échouées)
```
Failure #1: AdminControllerTest::testDeleteOwnUserAccount
  Status: 401 → Expected: 400
  Data:
    - actual_status: 401
    - expected_status: 400
    - assertion: "Status code mismatch"
    - controller: "AdminController"
    - test_method: "testDeleteOwnUserAccount"
```

## 🔍 Interprétation des Données

### Types d'Erreurs

| Type | Cause | Solution |
|------|-------|----------|
| `BadMethodCallException` | Mock mal configuré | Vérifier `shouldReceive()` dans le test |
| `Exception` | Code non géré | Ajouter try-catch ou mock |
| Autre exception | Logique métier | Corriger le code ou le test |

### Types de Failures

| Pattern | Problème | Solution |
|---------|----------|----------|
| Status 401 → Expected 400 | Authentification au lieu de validation | Vérifier les headers/token |
| Status 404 → Expected 200 | Ressource introuvable | Vérifier mock de retour |
| Assertion false | Logique incorrecte | Revoir le test ou le code |

## 📁 Fichiers Générés

```
test_report.json       # Rapport JSON structuré pour parsing
test_output.txt        # Sortie brute de PHPUnit
test_full_report.txt   # Rapport complet du shell script
```

## 💡 Exemples d'Utilisation

### Identifier toutes les erreurs d'un contrôleur

```bash
test_report | grep "UserController" -A 5
```

### Exporter les failures en JSON

```bash
php test-report-detailed.php && cat test_report.json | jq '.failures'
```

### Compter les erreurs par type

```bash
cat test_report.json | jq '.errors | group_by(.type) | map({type: .[0].type, count: length})'
```

### Générer un rapport HTML (optionnel)

```bash
# Créer un rapport HTML personnalisé
cat test_report.json | php -r '
$data = json_decode(file_get_contents("php://stdin"), true);
echo "<html><body>";
echo "<h1>Test Report</h1>";
foreach($data["failures"] as $f) {
    echo "<p>Status {$f["status"]} → {$f["expected"]}</p>";
}
echo "</body></html>";
' > report.html
```

## 🚀 Intégration CI/CD

Pour utiliser dans une pipeline:

```bash
#!/bin/bash
php test-report-detailed.php

# Vérifier le statut
if [ -f "test_report.json" ]; then
    ERRORS=$(jq '.summary.errors' test_report.json)
    FAILURES=$(jq '.summary.failures' test_report.json)
    
    if [ "$ERRORS" -gt 0 ] || [ "$FAILURES" -gt 0 ]; then
        echo "Tests failed!"
        exit 1
    fi
fi

exit 0
```

## 📌 Conseils de Dépannage

1. **BadMethodCallException sur `get()`**
   - Problème: Mock Medoo ne simule pas la méthode `get()`
   - Solution: Ajouter `->shouldReceive('get')->andReturn(...)` dans setUp

2. **Status code mismatch (401 au lieu de 400)**
   - Problème: Authentification échouée au lieu de validation
   - Solution: Vérifier le token JWT et les headers Authorization

3. **Erreur sur `select()`**
   - Problème: Plusieurs appels à `select()` non configurés
   - Solution: Utiliser `andReturn()` pour chaque call de chaîne

## 🎓 Pour en Savoir Plus

- Voir `docs/TESTS.md` pour la documentation technique complète
- Consulter `TESTS_INDEX.md` pour naviguer par route
- Lire `IMPLEMENTATION_CHECKLIST.md` pour l'état des implémentations

---

**Dernière mise à jour:** 2026-03-06  
**Version:** 2.0 (avec rapports détaillés)
