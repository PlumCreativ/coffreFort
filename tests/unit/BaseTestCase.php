<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Classe de base pour tous les tests
 * Fournit des utilitaires communs pour créer des requêtes/réponses
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Crée une requête GET
     */
    protected function createGetRequest(string $path, array $params = []): Request
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', $path);
        
        if (!empty($params)) {
            $request = $request->withQueryParams($params);
        }
        
        return $request;
    }

    /**
     * Crée une requête POST avec un corps JSON
     */
    protected function createPostRequest(string $path, array $body = []): Request
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json');
        
        $request->getBody()->write(json_encode($body));
        $request->getBody()->rewind();
        
        return $request->withParsedBody($body);
    }

    /**
     * Crée une requête PUT avec un corps JSON
     */
    protected function createPutRequest(string $path, array $body = []): Request
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('PUT', $path)
            ->withHeader('Content-Type', 'application/json');
        
        $request->getBody()->write(json_encode($body));
        $request->getBody()->rewind();
        
        return $request->withParsedBody($body);
    }

    /**
     * Crée une requête DELETE
     */
    protected function createDeleteRequest(string $path, array $body = []): Request
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('DELETE', $path)
            ->withHeader('Content-Type', 'application/json');
        
        if (!empty($body)) {
            $request->getBody()->write(json_encode($body));
            $request->getBody()->rewind();
            $request = $request->withParsedBody($body);
        }
        
        return $request;
    }

    /**
     * Crée une requête PATCH
     */
    protected function createPatchRequest(string $path, array $body = []): Request
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('PATCH', $path)
            ->withHeader('Content-Type', 'application/json');
        
        $request->getBody()->write(json_encode($body));
        $request->getBody()->rewind();
        
        return $request->withParsedBody($body);
    }

    /**
     * Crée une requête avec un Authorization Bearer token
     */
    protected function createRequestWithToken(Request $request, string $token): Request
    {
        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Crée une réponse
     */
    protected function createResponse(): Response
    {
        return (new ResponseFactory())->createResponse();
    }

    /**
     * Décode le JSON d'une réponse
     */
    protected function getResponseData(Response $response): array
    {
        $response->getBody()->rewind();
        $content = $response->getBody()->getContents();
        return json_decode($content, true) ?? [];
    }
}
