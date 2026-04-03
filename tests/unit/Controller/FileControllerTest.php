<?php

namespace Tests\Controller;

use Tests\BaseTestCase;
use App\Controller\FileController;
use Firebase\JWT\JWT;
use Mockery as m;

/**
 * Tests pour FileController
 * Routes couvertes :
 * - GET /files
 * - GET /files/{id}
 * - POST /files (upload)
 * - DELETE /files/{id}
 * - PUT /files/{id} (rename)
 * - GET /folders
 * - POST /folders (create)
 * - DELETE /folders/{id}
 * - PUT /folders/{id} (rename)
 * - GET /files/{id}/versions
 * - POST /files/{id}/versions
 */
class FileControllerTest extends BaseTestCase
{
    private $database;
    private $fileController;
    private $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = m::mock('Medoo\Medoo');
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'qkdfjlqgjlqgjldk2345_fklqjglq6678';
        $this->fileController = new FileController($this->database, $this->jwtSecret);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    /**
     * Crée un JWT valide pour les tests
     */
    private function createValidJwt(int $userId = 1, bool $isAdmin = false): string
    {
        $payload = [
            'iss' => 'coffre-fort',
            'aud' => 'coffre-fort-users',
            'iat' => time(),
            'exp' => time() + 3600,
            'user_id' => $userId,
            'email' => 'test@example.com',
            'is_admin' => $isAdmin
        ];
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Test : Lister les fichiers de l'utilisateur
     * GET /files
     */
    public function testListFilesSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/files')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 1,
                'user_id' => 1,
                'name' => 'document.pdf',
                'created_at' => '2025-01-01'
            ]]);

        $this->database->shouldReceive('count')
            ->andReturn(1);

        $result = $this->fileController->list($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('files', $data);
        $this->assertArrayHasKey('total', $data);
    }

    /**
     * Test : Lister les fichiers sans authentification
     * GET /files (non-authentifié)
     */
    public function testListFilesUnauthorized(): void
    {
        $response = $this->createResponse();
        $request = $this->createGetRequest('/files');

        // Mock - pas de token, donc la vérification d'auth doit échouer
        $this->database->shouldReceive('select')
            ->andReturn([]);

        $result = $this->fileController->list($request, $response);

        $this->assertEquals(401, $result->getStatusCode());
    }

    /**
     * Test : Afficher un fichier spécifique avec ses versions
     * GET /files/{id}
     */
    public function testShowFileSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/files/1')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - find user by email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), \Mockery::any())
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner le fichier via find(1)
        $this->database->shouldReceive('get')
            ->with('files', \Mockery::any(), ['id' => 1])
            ->andReturn([
                'id' => 1,
                'user_id' => 1,
                'folder_id' => 0,
                'name' => 'document.pdf',
                'original_name' => 'document.pdf',
                'stored_name' => 'doc_abc123.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'created_at' => '2025-01-01',
                'updated_at' => '2025-01-01'
            ]);

        // Mock - max version (pour getMaxVersionForFile)
        $this->database->shouldReceive('max')
            ->andReturn(3);

        // Mock - compter le nombre de versions (pour getVersionCount)
        $this->database->shouldReceive('count')
            ->andReturn(3);

        // Mock - retourner les dernières versions (pour getLatestVersions)
        $this->database->shouldReceive('select')
            ->andReturn([[
                'version' => 3,
                'size' => 1024,
                'checksum' => 'abc123def456789012345',
                'created_at' => '2025-01-15'
            ]]);

        $result = $this->fileController->show($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(1, $data['id']);
    }

    /**
     * Test : Lister les dossiers
     * GET /folders
     */
    public function testListFoldersSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/folders')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        // Mock - retourner les dossiers
        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 10,
                'user_id' => 1,
                'name' => 'My Folder',
                'created_at' => '2025-01-01'
            ]]);

        $result = $this->fileController->listFolders($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertIsArray($data);
    }

    /**
     * Test : Créer un dossier
     * POST /folders
     */
    public function testCreateFolderSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createPostRequest('/folders', [
            'user_id' => 1,
            'name' => 'New Folder',
            'parent_id' => null
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->zeroOrMoreTimes();

        // Mock - insérer le dossier
        $this->database->shouldReceive('insert')
            ->andReturn(null);

        $this->database->shouldReceive('id')
            ->andReturn(10);

        $result = $this->fileController->createFolder($request, $response);

        $this->assertEquals(201, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('créé', strtolower($data['message']));
    }

    /**
     * Test : Créer un dossier - Nom manquant
     * POST /folders (validation échouée)
     */
    public function testCreateFolderNoName(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createPostRequest('/folders', [
            'user_id' => 1,
            'name' => ''
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'test@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->once();

        // Mock - allow insert in case validation is bypassed
        $this->database->shouldReceive('insert')
            ->andReturn(null);
        $this->database->shouldReceive('id')
            ->andReturn(10);

        $result = $this->fileController->createFolder($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test : Renommer un dossier
     * PUT /folders/{id}
     */
    public function testRenameFolderSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createPutRequest('/folders/10', [
            'name' => 'Renamed Folder'
        ])->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'test@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->once();

        // Mock - trouver le dossier par ID
        $this->database->shouldReceive('get')
            ->with('folders', \Mockery::any(), ['id' => 10])
            ->andReturn([
                'id' => 10,
                'user_id' => 1,
                'name' => 'My Folder',
                'parent_id' => null
            ])
            ->once();

        // Mock - count() pour vérifier qu'un dossier avec ce nom n'existe pas
        $this->database->shouldReceive('count')
            ->andReturn(0);

        // Mock - mettre à jour le dossier
        $pdoMock = m::mock('PDOStatement');
        $pdoMock->shouldReceive('rowCount')->andReturn(1);
        $this->database->shouldReceive('update')
            ->andReturn($pdoMock);

        $result = $this->fileController->renameFolder($request, $response, ['id' => '10']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * Test : Supprimer un dossier
     * DELETE /folders/{id}
     */
    public function testDeleteFolderSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createDeleteRequest('/folders/10')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'test@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->once();

        // Mock - trouver le dossier par ID
        $this->database->shouldReceive('get')
            ->with('folders', \Mockery::any(), ['id' => 10])
            ->andReturn([
                'id' => 10,
                'user_id' => 1,
                'name' => 'My Folder'
            ])
            ->once();

        // Mock - compter les fichiers dans le dossier
        $this->database->shouldReceive('count')
            ->andReturn(0);

        // Mock - supprimer le dossier
        $pdoMock = m::mock('PDOStatement');
        $pdoMock->shouldReceive('rowCount')->andReturn(1);
        $this->database->shouldReceive('delete')
            ->andReturn($pdoMock);

        $result = $this->fileController->deleteFolder($request, $response, ['id' => '10']);

        $this->assertEquals(204, $result->getStatusCode());
    }

    /**
     * Test : Lister les versions d'un fichier
     * GET /files/{id}/versions
     */
    public function testListVersionsSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/files/1/versions')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->with('users', \Mockery::any(), ['email' => 'test@example.com'])
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'is_admin' => 0
            ])
            ->once();

        // Mock - trouver le fichier par ID
        $this->database->shouldReceive('get')
            ->with('files', \Mockery::any(), ['id' => 1])
            ->andReturn([
                'id' => 1,
                'user_id' => 1,
                'name' => 'document.pdf'
            ])
            ->once();

        // Mock - compter les versions
        $this->database->shouldReceive('count')
            ->andReturn(3);

        // Mock - max version pour le fichier
        $this->database->shouldReceive('max')
            ->andReturn(3);

        // Mock - retourner les versions paginées
        $this->database->shouldReceive('select')
            ->andReturn([[
                'id' => 1,
                'version' => 3,
                'checksum' => 'abc123',
                'created_at' => '2025-01-15',
                'size' => 1000
            ]]);

        $result = $this->fileController->listVersions($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('versions', $data);
        $this->assertArrayHasKey('total', $data);
    }

    /**
     * Test : Quota utilisateur
     * GET /me/quota
     */
    public function testGetUserQuotaSuccess(): void
    {
        $token = $this->createValidJwt();
        
        $response = $this->createResponse();
        $request = $this->createGetRequest('/me/quota')
            ->withHeader('Authorization', 'Bearer ' . $token);

        // Mock pour vérifier le token - trouver l'utilisateur par email
        $this->database->shouldReceive('get')
            ->andReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'quota_total' => 1073741824
            ])
            ->zeroOrMoreTimes();

        // Mock - sum() pour totalSizeByUser
        $this->database->shouldReceive('sum')
            ->andReturn(536870912)
            ->zeroOrMoreTimes();

        // Mock - retourner l'espace utilisé
        $this->database->shouldReceive('select')
            ->andReturn([['total' => 536870912]]);

        $result = $this->fileController->meQuota($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $data = $this->getResponseData($result);
        $this->assertArrayHasKey('total_bytes', $data);
        $this->assertArrayHasKey('used_bytes', $data);
    }
}
