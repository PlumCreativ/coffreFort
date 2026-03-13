<?php

namespace Tests\Controller;

use Tests\BaseTestCase;
use App\Controller\AdminController;
use Firebase\JWT\JWT;
use Mockery as m;

/**
 * Tests pour AdminController
 * Routes couvertes :
 * - GET /admin/users/quotas
 * - PUT /admin/users/{id}/quota
 * - DELETE /admin/users/{id}
 */
class AdminControllerTest extends BaseTestCase
{
    private $database;
    private $adminController;
    private $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = m::mock('Medoo\Medoo');
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'qkdfjlqgjlqgjldk2345_fklqjglq6678';
        $this->adminController = new AdminController($this->database);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    /**
     * Crée un JWT valide pour un administrateur
     */
    private function createAdminJwt(int $userId = 1): string
    {
        $payload = [
            'iss' => 'coffre-fort',
            'aud' => 'coffre-fort-users',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_id' => $userId,
            'email' => 'admin@example.com',
            'is_admin' => 1
        ];
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Crée un JWT valide pour un utilisateur normal
     */
    private function createUserJwt(int $userId = 2): string
    {
        $payload = [
            'iss' => 'coffre-fort',
            'aud' => 'coffre-fort-users',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_id' => $userId,
            'email' => 'user@example.com',
            'is_admin' => 0
        ];
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Test : Lister les utilisateurs avec quotas (admin)
     * GET /admin/users/quotas
     */
    public function testListUsersWithQuotaAsAdmin(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/admin/users/quotas')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner la liste de tous les utilisateurs
        $this->database->shouldReceive('select')
            ->andReturn([
                [
                    'id' => 1,
                    'email' => 'admin@example.com',
                    'quota_total' => 1073741824,
                    'is_admin' => 1
                ],
                [
                    'id' => 2,
                    'email' => 'user1@example.com',
                    'quota_total' => 536870912,
                    'is_admin' => 0
                ],
                [
                    'id' => 3,
                    'email' => 'user2@example.com',
                    'quota_total' => 1073741824,
                    'is_admin' => 0
                ]
            ]);

        // Mock - sum() pour totalSizeByUser pour chaque utilisateur
        $this->database->shouldReceive('sum')
            ->andReturn(268435456)
            ->andReturn(134217728)
            ->andReturn(0);

        $result = $this->adminController->listUsersWithQuota($request, $response);

        $data = $this->getResponseData($result);
        $this->assertIsArray($data['users']);
        $this->assertGreaterThan(0, count($data['users']));
        $this->assertEquals(200, $result->getStatusCode());
    }

    /**
     * Test : Lister les quotas - Non-admin refusé
     * GET /admin/users/quotas
     */
    public function testListUsersWithQuotaAsNonAdmin(): void
    {
        $token = $this->createUserJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/admin/users/quotas')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur non-admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner l'utilisateur non-admin
        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ]]);

        $result = $this->adminController->listUsersWithQuota($request, $response);

        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('administrateur requis', $data['error']);
        $this->assertEquals(403, $result->getStatusCode());
    }
    // commentaire test

    /**
     * Test : Modifier le quota d'un utilisateur (admin)
     * PUT /admin/users/{id}/quota
     */
    public function testUpdateUserQuotaAsAdmin(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createPutRequest('/admin/users/2/quota', [
            'quota' => 2147483648  // 2 GB
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->once();

        // Mock - trouver l'utilisateur cible par ID
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['id' => 2])
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'quota_total' => 1073741824
            ])
            ->once();

        // Mock - sum() pour totalSizeByUser
        $this->database->shouldReceive('sum')
            ->andReturn(536870912);

        // Mock - mettre à jour le quota - retourner un mock PDOStatement
        $pdoMock = m::mock('PDOStatement');
        $pdoMock->shouldReceive('rowCount')->andReturn(1);
        $this->database->shouldReceive('update')
            ->andReturn($pdoMock);

        $result = $this->adminController->updateUserQuota($request, $response, ['id' => '2']);
        
        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(2, $data['user_id']);
        $this->assertEquals(2147483648, $data['new_quota']);
    }

    /**
     * Test : Modifier le quota - Quota inférieur à l'espace utilisé
     * PUT /admin/users/{id}/quota
     */
    public function testUpdateUserQuotaBelowUsedSpace(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createPutRequest('/admin/users/2/quota', [
            'quota' => 268435456  // 256 MB (moins que l'espace utilisé)
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->once();

        // Mock - trouver l'utilisateur cible par ID
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['id' => 2])
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'quota_total' => 1073741824
            ])
            ->once();

        // Mock - sum() pour totalSizeByUser (plus que le nouveau quota)
        $this->database->shouldReceive('sum')
            ->andReturn(536870912);

        $result = $this->adminController->updateUserQuota($request, $response, ['id' => '2']);

        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('inférieure', $data['error']);
        $this->assertEquals(400, $result->getStatusCode());
    }

    /**
     * Test : Modifier le quota - Non-admin refusé
     * PUT /admin/users/{id}/quota
     */
    public function testUpdateUserQuotaAsNonAdmin(): void
    {
        $token = $this->createUserJwt();
        
        $response = $this->createResponse();
        $request = $this->createPutRequest('/admin/users/2/quota', [
            'quota' => 2147483648
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur non-admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        $result = $this->adminController->updateUserQuota($request, $response, ['id' => '2']);

        $this->assertEquals(403, $result->getStatusCode());
    }

    /**
     * Test : Modifier le quota - ID utilisateur invalide
     * PUT /admin/users/{id}/quota
     */
    public function testUpdateUserQuotaInvalidId(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createPutRequest('/admin/users/0/quota', [
            'quota' => 2147483648
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->zeroOrMoreTimes();

        $result = $this->adminController->updateUserQuota($request, $response, ['id' => '0']);

        $this->assertEquals(400, $result->getStatusCode());
    }

    /**
     * Test : Supprimer un utilisateur (admin)
     * DELETE /admin/users/{id}
     */
    public function testDeleteUserAsAdmin(): void
    {
        $token = $this->createAdminJwt(1);  // Admin avec ID 1
        
        $response = $this->createResponse();
        $request = $this->createDeleteRequest('/admin/users/2')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->once();

        // Mock - trouver l'utilisateur à supprimer par ID
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['id' => 2])
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ])
            ->once();

        // Mock - lister les fichiers de l'utilisateur
        $this->database->shouldReceive('select')
            ->andReturn([]);

        // Mock - supprimer les logs de téléchargement
        $this->database->shouldReceive('delete')
            ->andReturn(null);

        // Mock - supprimer l'utilisateur
        $this->database->shouldReceive('delete')
            ->andReturn(null);

        $result = $this->adminController->deleteUser($request, $response, ['id' => '2']);

        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals(2, $data['user_id']);
        $this->assertEquals(200, $result->getStatusCode());
    }

    /**
     * Test : Supprimer un utilisateur - Ne pas pouvoir supprimer son propre compte
     * DELETE /admin/users/{id}
     */
    public function testDeleteOwnUserAccount(): void
    {
        $token = $this->createAdminJwt(1);  // Admin avec ID 1
        
        $response = $this->createResponse();
        $request = $this->createDeleteRequest('/admin/users/1')  // Essai de supprimer le même utilisateur
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner l'utilisateur cible (lui-même)
        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ]]);

        $result = $this->adminController->deleteUser($request, $response, ['id' => '1']);

        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('propre compte', $data['error']);
        $this->assertEquals(400, $result->getStatusCode());
    }

    /**
     * Test : Supprimer un utilisateur - Non-admin refusé
     * DELETE /admin/users/{id}
     */
    public function testDeleteUserAsNonAdmin(): void
    {
        $token = $this->createUserJwt();
        
        $response = $this->createResponse();
        $request = $this->createDeleteRequest('/admin/users/2')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur non-admin par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        $result = $this->adminController->deleteUser($request, $response, ['id' => '2']);

        $this->assertEquals(403, $result->getStatusCode());
    }

    /**
     * Test : Supprimer un utilisateur - Utilisateur introuvable
     * DELETE /admin/users/{id}
     */
    public function testDeleteNonexistentUser(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createDeleteRequest('/admin/users/999')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->once();

        // Mock - aucun utilisateur trouvé avec ID 999
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['id' => 999])
            ->andReturn(null)
            ->once();

        $result = $this->adminController->deleteUser($request, $response, ['id' => '999']);

        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('introuvable', $data['error']);
        $this->assertEquals(404, $result->getStatusCode());
    }

    /**
     * Test : Lister les quotas - Sans authentification
     * GET /admin/users/quotas
     */
    public function testListUsersWithoutAuthentication(): void
    {
        $response = $this->createResponse();
        $request = $this->createGetRequest('/admin/users/quotas');

        // Mock - aucun utilisateur trouvé (pas d'authentification)
        $this->database->shouldReceive('select')
            ->andReturn([]);

        $result = $this->adminController->listUsersWithQuota($request, $response);

        $this->assertEquals(401, $result->getStatusCode());
    }
}
