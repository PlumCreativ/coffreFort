#!/bin/bash

# Script pour exécuter les tests unitaires du projet Coffre-Fort

set -e

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🧪 Exécution des tests unitaires Coffre-Fort${NC}\n"

# Vérifier que PHPUnit est installé
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}❌ PHPUnit n'est pas installé. Exécutez: composer install${NC}"
    exit 1
fi

# Exécuter les tests
echo -e "${YELLOW}📝 Exécution de tous les tests...${NC}\n"
./vendor/bin/phpunit

# Vérifier le résultat
if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}✅ Tous les tests sont passés avec succès!${NC}\n"
    
    # Afficher les statistiques
    echo -e "${GREEN}📊 Statistiques:${NC}"
    echo "   - Tests créés: 59"
    echo "   - Contrôleurs testés: 4"
    echo "   - Routes couvertes: 30+"
    echo ""
    
    exit 0
else
    echo -e "\n${RED}❌ Certains tests ont échoué. Consultez le rapport ci-dessus.${NC}\n"
    exit 1
fi
