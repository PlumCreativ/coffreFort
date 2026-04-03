#!/bin/bash

# ========================================================
# GUIDE RAPIDE D'UTILISATION DES TESTS
# ========================================================

echo "📚 GUIDE RAPIDE DES TESTS UNITAIRES"
echo "===================================="
echo ""

echo "1️⃣  INSTALLATION"
echo "   $ composer install"
echo ""

echo "2️⃣  EXÉCUTER LES TESTS"
echo ""
echo "   Tous les tests:"
echo "   $ ./vendor/bin/phpunit"
echo ""
echo "   Un contrôleur spécifique:"
echo "   $ ./vendor/bin/phpunit tests/unit/Controller/UserControllerTest.php"
echo ""
echo "   Un test spécifique:"
echo "   $ ./vendor/bin/phpunit --filter testLoginSuccess"
echo ""

echo "3️⃣  COUVERTE DE CODE"
echo "   $ ./vendor/bin/phpunit --coverage-html coverage"
echo "   Puis ouvrir coverage/index.html"
echo ""

echo "4️⃣  HELPER DES TESTS (Plus facile)"
echo ""
echo "   Source le helper:"
echo "   $ source tests-helper.sh"
echo ""
echo "   Commandes disponibles:"
echo "   $ test_all                       # Tous les tests"
echo "   $ test_controller UserController # Tests d'un contrôleur"
echo "   $ test_specific testLoginSuccess # Un test spécifique"
echo "   $ test_coverage                  # Rapport de couverture"
echo "   $ test_summary                   # Résumé"
echo "   $ test_help                      # Aide"
echo ""

echo "5️⃣  SCRIPT D'EXÉCUTION"
echo "   $ ./run-tests.sh"
echo ""

echo "📊 STRUCTURE DES TESTS"
echo "======================"
echo ""
echo "tests/unit/"
echo "├── BaseTestCase.php"
echo "│   └── Méthodes utilitaires pour créer requêtes/réponses"
echo "└── Controller/"
echo "    ├── UserControllerTest.php      (12 tests)"
echo "    ├── FileControllerTest.php      (18 tests)"
echo "    ├── ShareControllerTest.php     (15 tests)"
echo "    └── AdminControllerTest.php     (14 tests)"
echo ""

echo "🎯 COUVERTURE DES ROUTES"
echo "======================="
echo ""
echo "✅ Authentication"
echo "   POST /auth/register"
echo "   POST /auth/login"
echo ""
echo "✅ Users"
echo "   GET /users"
echo "   GET /users/{id}"
echo "   GET /dashboard"
echo ""
echo "✅ Files"
echo "   GET /files"
echo "   GET /files/{id}"
echo "   GET /files/{id}/versions"
echo "   GET /me/quota"
echo ""
echo "✅ Folders"
echo "   GET /folders"
echo "   POST /folders"
echo "   PUT /folders/{id}"
echo "   DELETE /folders/{id}"
echo ""
echo "✅ Shares"
echo "   POST /shares"
echo "   GET /shares"
echo "   GET /shares/{id}"
echo "   DELETE /shares/{id}"
echo "   PATCH /shares/{id}/revoke"
echo "   GET /s/{token}"
echo "   GET /s/{token}/versions"
echo ""
echo "✅ Admin"
echo "   GET /admin/users/quotas"
echo "   PUT /admin/users/{id}/quota"
echo "   DELETE /admin/users/{id}"
echo ""

echo "🔐 MOCKING JWT"
echo "==============="
echo ""
echo "Les tests utilisent Mockery pour simuler les tokens JWT"
echo ""
echo "Exemple de test avec authentification:"
echo ""
echo '  public function testListFilesSuccess(): void'
echo '  {'
echo '      // Créer un token JWT valide'
echo '      $token = $this->createValidJwt(userId: 1);'
echo '      '
echo '      // Créer la requête avec le token'
echo '      $request = $this->createGetRequest("/files")'
echo '          ->withHeader("Authorization", "Bearer " . $token);'
echo '      '
echo '      // Créer la réponse vide'
echo '      $response = $this->createResponse();'
echo '      '
echo '      // Exécuter le contrôleur'
echo '      $result = $this->fileController->list($request, $response);'
echo '      '
echo '      // Vérifications'
echo '      $this->assertEquals(200, $result->getStatusCode());'
echo '      $data = $this->getResponseData($result);'
echo '      $this->assertArrayHasKey("files", $data);'
echo '  }'
echo ""

echo "📚 DOCUMENTATION COMPLÈTE"
echo "======================="
echo "Consultez docs/TESTS.md pour une documentation détaillée"
echo ""

echo "✨ C'est tout! Vous êtes prêt à tester votre application!"
