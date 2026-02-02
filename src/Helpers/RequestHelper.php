<?php
namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface as Request;

class RequestHelper
{

    /**
     * Récupère l'IP réelle du client (gère les proxies)
     */
    public static function getClientIp(Request $request): string
    {

        $serverParams = $request->getServerParams();

        // Vérifier les headers de proxy courants
        $headers = [
            'HTTP_X_FORWARDED_FOR', //=> standard proxy/load balancer
            'HTTP_X_REAL_IP',       //=> Nginx
            'HTTP_CLIENT_IP',       //=> certains proxies
            'REMOTE_ADDR'           //=> connexion directe
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {

                // X-Forwarded-For peut contenir plusieurs IPs séparées par des virgules
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return substr($ip, 0, 45); // Limiter à 45 caractères (IPv6 max)
                }
            }
        }
        return 'Unknown';
    }

    /**
     * Récupère le User-Agent du client
     */
    public static function getUserAgent(Request $request): string
    {
        return $request->getHeaderLine('User-Agent') ?: 'Unknown';
    }

}