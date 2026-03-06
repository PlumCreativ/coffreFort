#!/bin/bash

# Makefile-like helper pour les tests du projet Coffre-Fort
# Utilisation: source ./tests-helper.sh puis utiliser les commandes

# ============================================
# COMMANDES DE BASE
# ============================================

# Exécuter tous les tests avec rapport détaillé des erreurs
test_all() {
    echo "🧪 Exécution de tous les tests..."
    ./vendor/bin/phpunit --no-coverage 2>&1 | tee test_output.txt
    
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "📋 RAPPORT D'ERREURS ET FAILURES"
    echo "════════════════════════════════════════════════════════════════"
    
    # Extraire et afficher les erreurs
    echo ""
    echo "❌ ERREURS (Exceptions non gérées):"
    echo "─────────────────────────────────────────────────────────────────"
    grep -A 4 "^[0-9]\+) Tests" test_output.txt | grep -B 1 "Exception\|BadMethodCallException" || echo "Aucune erreur"
    
    # Extraire et afficher les failures
    echo ""
    echo "⚠️  FAILURES (Assertions échouées):"
    echo "─────────────────────────────────────────────────────────────────"
    grep "Failed asserting" test_output.txt | while read line; do
        echo "  • $line"
    done || echo "Aucune failure"
    
    # Résumé avec compteurs
    local error_count=$(grep -c "^[0-9]\+) Tests.*Exception" test_output.txt 2>/dev/null || echo 0)
    local failure_count=$(grep -c "Failed asserting" test_output.txt 2>/dev/null || echo 0)
    
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "📊 RÉSUMÉ"
    echo "════════════════════════════════════════════════════════════════"
    echo "Erreurs:   $error_count"
    echo "Failures:  $failure_count"
    echo ""
    
    # Afficher la ligne de statut finale
    tail -5 test_output.txt
}

# Exécuter les tests d'un contrôleur
test_controller() {
    local controller=$1
    if [ -z "$controller" ]; then
        echo "Usage: test_controller <UserController|FileController|ShareController|AdminController>"
        return 1
    fi
    echo "🧪 Tests de ${controller}..."
    ./vendor/bin/phpunit "tests/unit/Controller/${controller}Test.php"
}

# Exécuter un test spécifique
test_specific() {
    local test=$1
    if [ -z "$test" ]; then
        echo "Usage: test_specific <test_name>"
        echo "Exemple: test_specific testLoginSuccess"
        return 1
    fi
    echo "🧪 Test: $test"
    ./vendor/bin/phpunit --filter "$test"
}

# ============================================
# ANALYSE ET RAPPORTS
# ============================================

# Générer un rapport détaillé avec status et data
test_report() {
    echo "📊 Génération du rapport détaillé..."
    php test-report-detailed.php
}

# Générer le rapport de couverture
test_coverage() {
    echo "📊 Génération du rapport de couverture..."
    ./vendor/bin/phpunit --coverage-html coverage
    echo "✅ Rapport généré dans le dossier 'coverage/'"
    echo "   Ouvrez 'coverage/index.html' pour voir le rapport"
}

# Afficher le résumé rapide
test_summary() {
    echo ""
    echo "📊 RÉSUMÉ DES TESTS UNITAIRES"
    echo "=============================="
    echo ""
    echo "UserController:   12 tests"
    echo "  - Authentification (register, login)"
    echo "  - Gestion des utilisateurs (list, show)"
    echo "  - Dashboard (JWT)"
    echo ""
    echo "FileController:   18 tests"
    echo "  - Listing et détails"
    echo "  - Gestion des dossiers"
    echo "  - Gestion des versions"
    echo "  - Quota utilisateur"
    echo ""
    echo "ShareController:  15 tests"
    echo "  - Création et gestion des partages"
    echo "  - Accès public"
    echo "  - Révocation"
    echo ""
    echo "AdminController:  14 tests"
    echo "  - Gestion des quotas"
    echo "  - Suppression d'utilisateurs"
    echo "  - Droits d'accès"
    echo ""
    echo "TOTAL: 59 tests"
    echo ""
}

# ============================================
# UTILITAIRES
# ============================================

# Afficher l'aide
test_help() {
    cat << EOF
🧪 Helper de tests Coffre-Fort

Commandes disponibles:
  test_all                    Exécuter tous les tests
  test_controller [name]      Exécuter les tests d'un contrôleur
  test_specific [name]        Exécuter un test spécifique
  test_report                 Générer un rapport détaillé (status & data)
  test_coverage               Générer un rapport de couverture
  test_summary                Afficher le résumé des tests
  test_help                   Afficher cette aide

Exemples:
  test_all
  test_controller UserController
  test_specific testLoginSuccess
  test_report                 # Affiche les erreurs avec status et data

Note: Assurez-vous que les dépendances sont installées:
  composer install

EOF
}

# ============================================
# ALIAS COURTS
# ============================================

# Alias court pour l'aide
h() {
    test_help
}

