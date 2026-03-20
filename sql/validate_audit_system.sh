#!/bin/bash

# ============================================================================
# SCRIPT DE VALIDATION DU SYSTÈME D'AUDIT
# ============================================================================
# 
# Ce script vérifie que tous les triggers et procédures stockées sont 
# correctement installés et fonctionnels.
#
# Usage: bash validate_audit_system.sh
#
# ============================================================================

set -e

# Configuration
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:---password}"
DB_NAME="${DB_NAME:-coffreFort}"
DB_PORT="${DB_PORT:-3306}"

# Couleurs pour l'output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Compteurs
PASSED=0
FAILED=0
WARNINGS=0

echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}VALIDATION DU SYSTÈME D'AUDIT${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""

# Fonction pour tester une requête MySQL
run_query() {
    local query="$1"
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -N -s -e "$query" 2>/dev/null
}

# Fonction pour afficher un résultat
check_result() {
    local name="$1"
    local result="$2"
    
    if [ -z "$result" ]; then
        echo -e "${RED}✗ FAIL:${NC} $name"
        ((FAILED++))
        return 1
    else
        echo -e "${GREEN}✓ PASS:${NC} $name"
        ((PASSED++))
        return 0
    fi
}

echo -e "${BLUE}1. Vérification de la table audit_logs${NC}"
echo ""

# Vérifier l'existence de la table
TABLE_EXISTS=$(run_query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='audit_logs'")
if [ "$TABLE_EXISTS" -eq 1 ]; then
    echo -e "${GREEN}✓${NC} Table audit_logs existe"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Table audit_logs manquante"
    ((FAILED++))
    exit 1
fi

# Vérifier les colonnes
COLUMNS=$(run_query "SELECT GROUP_CONCAT(COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='audit_logs'")
check_result "Colonne: id" "$(echo "$COLUMNS" | grep -o 'id')"
check_result "Colonne: user_id" "$(echo "$COLUMNS" | grep -o 'user_id')"
check_result "Colonne: action" "$(echo "$COLUMNS" | grep -o 'action')"
check_result "Colonne: table_name" "$(echo "$COLUMNS" | grep -o 'table_name')"
check_result "Colonne: record_id" "$(echo "$COLUMNS" | grep -o 'record_id')"
check_result "Colonne: details" "$(echo "$COLUMNS" | grep -o 'details')"
check_result "Colonne: ip_address" "$(echo "$COLUMNS" | grep -o 'ip_address')"
check_result "Colonne: user_agent" "$(echo "$COLUMNS" | grep -o 'user_agent')"
check_result "Colonne: created_at" "$(echo "$COLUMNS" | grep -o 'created_at')"

echo ""
echo -e "${BLUE}2. Vérification des Indexes${NC}"
echo ""

INDEXES=$(run_query "SELECT GROUP_CONCAT(INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='audit_logs' AND INDEX_NAME != 'PRIMARY'")
echo "Indexes trouvés: $INDEXES"
check_result "Index sur user_id" "$(echo "$INDEXES" | grep -o 'idx_user_id')"
check_result "Index sur action" "$(echo "$INDEXES" | grep -o 'idx_action')"
check_result "Index sur created_at" "$(echo "$INDEXES" | grep -o 'idx_created_at')"

echo ""
echo -e "${BLUE}3. Vérification de la Procédure Stockée${NC}"
echo ""

SP_EXISTS=$(run_query "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$DB_NAME' AND ROUTINE_NAME='sp_audit_insert'")
if [ "$SP_EXISTS" -eq 1 ]; then
    echo -e "${GREEN}✓${NC} Procédure sp_audit_insert existe"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} Procédure sp_audit_insert non trouvée (optionnel si triggers directs)"
    ((WARNINGS++))
fi

echo ""
echo -e "${BLUE}4. Vérification des Triggers${NC}"
echo ""

TRIGGERS=$(run_query "SELECT GROUP_CONCAT(TRIGGER_NAME) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DB_NAME' AND EVENT_MANIPULATION IN ('INSERT', 'UPDATE', 'DELETE')")
echo "Triggers trouvés:"
echo "$TRIGGERS" | tr ',' '\n' | sort | sed 's/^/  - /'

check_result "Trigger: trg_files_after_insert" "$(echo "$TRIGGERS" | grep -o 'trg_files_after_insert')"
check_result "Trigger: trg_files_after_rename" "$(echo "$TRIGGERS" | grep -o 'trg_files_after_rename')"
check_result "Trigger: trg_files_before_delete" "$(echo "$TRIGGERS" | grep -o 'trg_files_before_delete')"
check_result "Trigger: trg_folders_after_insert" "$(echo "$TRIGGERS" | grep -o 'trg_folders_after_insert')"
check_result "Trigger: trg_folders_after_rename" "$(echo "$TRIGGERS" | grep -o 'trg_folders_after_rename')"
check_result "Trigger: trg_folders_before_delete" "$(echo "$TRIGGERS" | grep -o 'trg_folders_before_delete')"
check_result "Trigger: trg_shares_after_insert" "$(echo "$TRIGGERS" | grep -o 'trg_shares_after_insert')"
check_result "Trigger: trg_shares_after_revoke" "$(echo "$TRIGGERS" | grep -o 'trg_shares_after_revoke')"
check_result "Trigger: trg_shares_before_delete" "$(echo "$TRIGGERS" | grep -o 'trg_shares_before_delete')"
check_result "Trigger: trg_file_versions_after_insert" "$(echo "$TRIGGERS" | grep -o 'trg_file_versions_after_insert')"
check_result "Trigger: trg_file_versions_before_delete" "$(echo "$TRIGGERS" | grep -o 'trg_file_versions_before_delete')"
check_result "Trigger: trg_users_before_delete" "$(echo "$TRIGGERS" | grep -o 'trg_users_before_delete')"
check_result "Trigger: trg_users_after_insert" "$(echo "$TRIGGERS" | grep -o 'trg_users_after_insert')"

echo ""
echo -e "${BLUE}5. Vérification des Données d'Audit${NC}"
echo ""

AUDIT_COUNT=$(run_query "SELECT COUNT(*) FROM audit_logs")
echo "Nombre d'enregistrements d'audit: $AUDIT_COUNT"

if [ "$AUDIT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Audits existent"
    ((PASSED++))
    
    # Vérifier les actions uniques
    UNIQUE_ACTIONS=$(run_query "SELECT COUNT(DISTINCT action) FROM audit_logs")
    echo "Nombre d'actions uniques: $UNIQUE_ACTIONS"
    
    # Vérifier la répartition
    echo ""
    echo "Répartition des audits par action:"
    run_query "SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action ORDER BY count DESC" | \
        awk '{print "  " $1 ": " $2}'
else
    echo -e "${YELLOW}⚠${NC} Aucun audit trouvé (normal si DB fraîche)"
    ((WARNINGS++))
fi

echo ""
echo -e "${BLUE}6. Vérification des Permissions${NC}"
echo ""

# Vérifier que la base de données peut insérer dans audit_logs
TEST_INSERT=$(run_query "SHOW GRANTS FOR CURRENT_USER" | grep -o 'ALL PRIVILEGES' || echo '')
if [ -n "$TEST_INSERT" ]; then
    echo -e "${GREEN}✓${NC} Permissions de modification OK"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} Permissions à vérifier manuellement"
    ((WARNINGS++))
fi

echo ""
echo -e "${BLUE}7. Vérification de l'Intégrité des Données${NC}"
echo ""

# Vérifier qu'il n'y a pas trop de NULL critiques
NULL_DETAILS=$(run_query "SELECT COUNT(*) FROM audit_logs WHERE details IS NULL")
if [ "$NULL_DETAILS" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Pas de détails NULL"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} $NULL_DETAILS enregistrements avec détails NULL"
    ((WARNINGS++))
fi

# Vérifier les dates
FUTURE_DATES=$(run_query "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_ADD(NOW(), INTERVAL 1 HOUR)")
if [ "$FUTURE_DATES" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Pas de dates futures"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} $FUTURE_DATES enregistrements avec dates futures"
    ((FAILED++))
fi

echo ""
echo -e "${BLUE}8. Statistiques de Performance${NC}"
echo ""

TABLE_SIZE=$(run_query "SELECT ROUND(((data_length+index_length)/1024/1024), 2) FROM information_schema.TABLES WHERE table_schema='$DB_NAME' AND table_name='audit_logs'")
echo "Taille de la table: ${TABLE_SIZE}MB"

# Estimé du temps d'une requête
QUERY_TIME=$(run_query "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)")
echo "Requête optimisée: $QUERY_TIME audits du jour"

echo ""
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}RÉSUMÉ${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo -e "Vérifications réussies: ${GREEN}$PASSED${NC}"
echo -e "Vérifications échouées: ${RED}$FAILED${NC}"
echo -e "Avertissements: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ Système d'audit OPÉRATIONNEL${NC}"
    exit 0
else
    echo -e "${RED}✗ Problèmes détectés - Veuillez corriger${NC}"
    exit 1
fi
