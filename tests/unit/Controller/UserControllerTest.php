<?php

namespace Tests\Controller;

use Tests\BaseTestCase;
use App\Controller\UserController;
use Firebase\JWT\JWT;
use Mockery as m;

/**
 * Tests pour UserController
 * Routes couvertes :
 * - POST /auth/register
 * - POST /auth/login
 * - GET /users (admin only)
 * - GET /users/{id} (admin only)
 * - GET /dashboard
 */
class UserControllerTest extends BaseTestCase
{
    private $database;
    private $userController;
    private $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = m::mock('Medoo\Medoo');
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'qkdfjlqgjlqgjldk2345_fklqjglq6678';
        $this->userController = new UserController($this->database);
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
     * Test : Inscription d'un nouvel utilisateur
     * POST /auth/register
     */
    public function testRegisterSuccess(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'SecurePassword123'
        ]);

        // Mock - pas d'utilisateur existant
        $this->database->shouldReceive('get')
            ->andReturn(null)
            ->zeroOrMoreTimes();

        // Mock - compter les utilisateurs (0 pour le premier)
        $this->database->shouldReceive('count')
            ->andReturn(0);

        // Mock - insérer l'utilisateur
        $this->database->shouldReceive('insert')
            ->andReturn(null);

        $this->database->shouldReceive('id')
            ->andReturn(1);

        $result = $this->userController->register($request, $response);

        $this->assertEquals(201, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('is_admin', $data);
        $this->assertTrue($data['is_admin']); // Premier utilisateur = admin
    }

    /**
     * Test : Inscription avec email invalide
     * POST /auth/register
     */
    public function testRegisterInvalidEmail(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/register', [
            'email' => 'invalid-email',
            'password' => 'SecurePassword123'
        ]);

        $result = $this->userController->register($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Inscription avec mot de passe trop court
     * POST /auth/register
     */
    public function testRegisterShortPassword(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'short'
        ]);

        $result = $this->userController->register($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Inscription avec email existant
     * POST /auth/register
     */
    public function testRegisterEmailExists(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'SecurePassword123'
        ]);

        // Mock - utilisateur déjà existant
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'existing@example.com'
            ]);

        $result = $this->userController->register($request, $response);

        $this->assertEquals(409, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Connexion réussie
     * POST /auth/login
     */
    public function testLoginSuccess(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/login', [
            'email' => 'user@example.com',
            'password' => 'SecurePassword123'
        ]);

        // Mock - trouver l'utilisateur
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'user@example.com',
                'pass_hash' => password_hash('SecurePassword123', PASSWORD_DEFAULT),
                'is_admin' => 0
            ]);

        $result = $this->userController->login($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('jwt', $data);
    }

    /**
     * Test : Connexion avec email invalide
     * POST /auth/login
     */
    public function testLoginInvalidEmail(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/login', [
            'email' => 'invalid-email',
            'password' => 'SecurePassword123'
        ]);

        $result = $this->userController->login($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Connexion avec utilisateur inexistant
     * POST /auth/login
     */
    public function testLoginUserNotFound(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SecurePassword123'
        ]);

        // Mock - utilisateur non trouvé
        $this->database->shouldReceive('get')
            ->andReturn(null);

        $result = $this->userController->login($request, $response);

        $this->assertEquals(401, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Connexion avec mot de passe incorrect
     * POST /auth/login
     */
    public function testLoginWrongPassword(): void
    {
        $response = $this->createResponse();
        $request = $this->createPostRequest('/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword123'
        ]);

        // Mock - trouver l'utilisateur avec un hash différent
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'user@example.com',
                'pass_hash' => password_hash('CorrectPassword123', PASSWORD_DEFAULT),
                'is_admin' => 0
            ]);

        $result = $this->userController->login($request, $response);

        $this->assertEquals(401, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Lister les utilisateurs (admin)
     * GET /users
     */
    public function testListUsersAsAdmin(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/users')
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
                    'is_admin' => 1
                ],
                [
                    'id' => 2,
                    'email' => 'user@example.com',
                    'is_admin' => 0
                ]
            ]);

        $result = $this->userController->list($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertIsArray($data);
    }

    /**
     * Test : Lister les utilisateurs sans droits admin
     * GET /users
     */
    public function testListUsersAsNonAdmin(): void
    {
        $token = $this->createUserJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/users')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur normal
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        $result = $this->userController->list($request, $response);

        $this->assertEquals(403, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Afficher un utilisateur (admin)
     * GET /users/{id}
     */
    public function testShowUserAsAdmin(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/users/2')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur admin
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner l'utilisateur demandé
        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 2,
                'email' => 'user@example.com',
                'is_admin' => 0
            ]]);

        $result = $this->userController->show($request, $response, ['id' => '2']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('id', $data);
    }

    /**
     * Test : Afficher un utilisateur inexistant (admin)
     * GET /users/{id}
     */
    public function testShowUserNotFound(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/users/999')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'admin (une fois)
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'admin@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'admin@example.com',
                'is_admin' => 1
            ])
            ->once();

        // Mock - utilisateur non trouvé via find(999)
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['id' => 999])
            ->andReturn(null)
            ->once();

        $result = $this->userController->show($request, $response, ['id' => '999']);

        $this->assertEquals(404, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Dashboard avec JWT valide
     * GET /dashboard
     */
    public function testDashboardSuccess(): void
    {
        $token = $this->createAdminJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/dashboard?jwt=' . $token);

        $result = $this->userController->dashboard($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('email', $data);
    }

    /**
     * Test : Dashboard sans JWT
     * GET /dashboard
     */
    public function testDashboardNoToken(): void
    {
        $response = $this->createResponse();
        $request = $this->createGetRequest('/dashboard');

        $result = $this->userController->dashboard($request, $response);

        $this->assertEquals(401, $result->getStatusCode());
    }

    /**
     * Test : Dashboard avec JWT invalide
     * GET /dashboard
     */
    public function testDashboardInvalidToken(): void
    {
        $response = $this->createResponse();
        $request = $this->createGetRequest('/dashboard')
            ->withHeader('Authorization', 'Bearer invalid.token.here');

        $result = $this->userController->dashboard($request, $response);

        $this->assertEquals(403, $result->getStatusCode());
    }
}
