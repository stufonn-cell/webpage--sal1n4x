<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

use PsiClinic\Core\HttpException;
use PsiClinic\Core\Log;
use PsiClinic\Core\OutboundUrl;

/**
 * Client for the Ministry of Health's single validation mechanism (MUV) in
 * its Docker API flavour, which the reporting party runs on its own
 * infrastructure (Minsalud "Manual de consumo API Docker FEV-RIPS").
 *
 *   POST {base}/api/Auth/LoginSISPRO                          -> token
 *   POST {base}/api/PaquetesFevRips/CargarRipsSinFactura      -> CUV or errors
 *
 * SISPRO credentials arrive with every submission and are never stored or
 * written to the log.
 */
final class MuvClient
{
    /** @var callable(string, string, array, ?string): array{0:int,1:string} */
    private $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly bool $verifyTls = true,
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? $this->curl(...);
    }

    public function login(string $documentType, string $documentNumber, string $password, string $nit): string
    {
        [$status, $body] = ($this->transport)('POST', '/api/Auth/LoginSISPRO', [
            'persona' => ['identificacion' => ['tipo' => $documentType, 'numero' => $documentNumber]],
            'clave' => $password,
            'nit' => $nit,
        ], null);

        $data = json_decode($body, true);
        $token = is_array($data) ? ($data['token'] ?? $data['Token'] ?? $data['access_token'] ?? null) : null;

        if ($status >= 400 || !is_string($token) || $token === '') {
            throw HttpException::unprocessable('SISPRO did not accept the credentials. Check the document, the password and the NIT.');
        }

        return $token;
    }

    /**
     * Sends a RIPS without invoice. Returns whether it was accepted, the CUV
     * and the list of rejections and notices exactly as the Ministry returns them.
     */
    public function sendWithoutInvoice(string $token, array $rips): array
    {
        [$status, $body] = ($this->transport)('POST', '/api/PaquetesFevRips/CargarRipsSinFactura', [
            'rips' => $rips,
            'xmlFevFile' => null,
        ], $token);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new HttpException(502, sprintf('The validator sent an unexpected response (HTTP %d). Check that the Docker API is running.', $status));
        }

        $results = [];
        foreach ((array) ($data['ResultadosValidacion'] ?? []) as $result) {
            if (!is_array($result)) {
                continue;
            }
            $results[] = [
                'class' => (string) ($result['Clase'] ?? ''),
                'code' => (string) ($result['Codigo'] ?? ''),
                'description' => (string) ($result['Descripcion'] ?? ''),
                'notes' => (string) ($result['Observaciones'] ?? ''),
                'path' => (string) ($result['PathFuente'] ?? ''),
            ];
        }

        $cuv = $data['CodigoUnicoValidacion'] ?? null;

        return [
            'accepted' => ($data['ResultState'] ?? false) === true && is_string($cuv) && $cuv !== '',
            'cuv' => is_string($cuv) && $cuv !== '' ? $cuv : null,
            'processId' => $data['ProcesoId'] ?? null,
            'receivedAt' => $data['FechaRadicacion'] ?? null,
            'results' => $results,
        ];
    }

    /** @return array{0:int,1:string} */
    private function curl(string $method, string $path, array $payload, ?string $token): array
    {
        if (!function_exists('curl_init')) {
            throw new HttpException(500, 'The server is missing the PHP cURL extension.');
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $url = rtrim($this->baseUrl, '/') . $path;
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            // Only plain HTTP(S), never redirects: a redirect could send the
            // SISPRO token or the clinical payload somewhere else.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXFILESIZE => 20 * 1024 * 1024,
            // By default the Docker API uses its own certificate on
            // https://localhost:9443; verification can be turned off in the
            // settings for that case only.
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ];

        // Pin the address that was checked against reserved networks, so a DNS
        // answer that changes between the check and the request (rebinding)
        // cannot redirect the call.
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host !== '' && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            $ip = gethostbyname($host);
            if ($ip === $host || OutboundUrl::isBlockedIp($ip)) {
                throw new HttpException(502, 'We could not reach the Ministry validator. Check that the Docker API is running and that its address is correct.');
            }
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
            $options[CURLOPT_RESOLVE] = [sprintf('%s:%d:%s', $host, $port, $ip)];
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            Log::error('MUV unavailable', ['error' => $error]);
            throw new HttpException(502, 'We could not reach the Ministry validator. Check that the Docker API is running and that its address is correct.');
        }

        return [$status, (string) $body];
    }
}
