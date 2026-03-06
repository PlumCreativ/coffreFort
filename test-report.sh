#!/bin/bash

# Script de rapport détaillé pour les tests
# Affiche les erreurs et failures avec status et data

echo "🧪 Exécution des tests et génération du rapport..."
echo ""

# Exécuter les tests et capturer la sortie
./vendor/bin/phpunit --no-coverage 2>&1 > test_full_report.txt

# Compter les tests et erreurs
total_tests=$(grep -o "[0-9]\+ / [0-9]\+ (" test_full_report.txt | head -1)
errors=$(grep "^There were [0-9]* errors:" test_full_report.txt | grep -o "[0-9]*")
failures=$(grep "^There were [0-9]* failures:" test_full_report.txt | grep -o "[0-9]*")

echo "════════════════════════════════════════════════════════════════════════════"
echo "📊 RÉSUMÉ GLOBAL"
echo "════════════════════════════════════════════════════════════════════════════"
echo "Tests: $total_tests"
echo "Erreurs: ${errors:-0}"
echo "Failures: ${failures:-0}"
echo ""

# Parser les erreurs avec détails
echo "════════════════════════════════════════════════════════════════════════════"
echo "❌ ERREURS DÉTAILLÉES"
echo "════════════════════════════════════════════════════════════════════════════"

# Extraire les erreurs
awk '/^There were [0-9]+ errors:$/,/^$/' test_full_report.txt | grep -A 10 "^[0-9]\+)" | while IFS= read -r line; do
    if [[ $line =~ ^[0-9]+\) ]]; then
        echo ""
        echo "  $line"
    elif [[ -n "$line" && ! $line =~ ^-- ]]; then
        echo "  │ $line"
    fi
done

echo ""
echo "════════════════════════════════════════════════════════════════════════════"
echo "⚠️  FAILURES DÉTAILLÉES (Status & Data)"
echo "════════════════════════════════════════════════════════════════════════════"

# Extraire les failures avec contexte
awk '/^There were [0-9]+ failures:$/,/^FAILURES:/{if (/^There were/) next; print}' test_full_report.txt | \
while IFS= read -r line; do
    if [[ $line =~ ^[0-9]+\) ]]; then
        echo ""
        echo "  $line"
    elif [[ $line =~ "Failed asserting" ]]; then
        # Extraire le status et expected
        test_name=$(echo "$line" | grep -o "test[A-Za-z]*")
        status=$(echo "$line" | grep -o "[0-9]\+" | head -1)
        expected=$(echo "$line" | grep -o "expected [0-9]\+" | sed 's/expected //')
        
        echo "  │ Status Code: $status → Expected: $expected"
        echo "  │ $line"
    elif [[ -n "$line" && ! $line =~ ^-- ]]; then
        echo "  │ $line"
    fi
done

echo ""
echo "════════════════════════════════════════════════════════════════════════════"
echo "📈 STATISTIQUES PAR CONTRÔLEUR"
echo "════════════════════════════════════════════════════════════════════════════"

# Compter les tests par contrôleur
for controller in UserController FileController ShareController AdminController; do
    count=$(grep "Tests\\\\Controller\\\\${controller}Test" test_full_report.txt | wc -l)
    echo "  $controller: $count"
done

echo ""
echo "════════════════════════════════════════════════════════════════════════════"
echo "✅ Rapport complet sauvegardé dans: test_full_report.txt"
echo "════════════════════════════════════════════════════════════════════════════"
