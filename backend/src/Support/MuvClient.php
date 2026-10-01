<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

use PsiClinic\Core\HttpException;

/**
 * Cliente del Mecanismo Unico de Validacion (MUV) en su version API Docker,
 * que el obligado instala en su propia infraestructura (Manual de consumo API
 * Docker FEV-RIPS, Minsalud).
 *
 *   POST {base}/api/Auth/LoginSISPRO                          -> token
 *   POST {base}/api/PaquetesFevRips/CargarRipsSinFactura      -> CUV o errores
 *
 * Las credenciales SISPRO llegan en cada envio y nunca se guardan ni se
 * escriben en el log.
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
            throw HttpException::unprocessable('SISPRO no aceptó las credenciales. Revisa el documento, la contraseña y el NIT.');
        }

        return $token;
    }

    /**
     * Envia un RIPS sin factura. Devuelve si fue aceptado, el CUV y la lista
     * de rechazos y notificaciones tal como la entrega el Ministerio.
     */
    public function sendWithoutInvoice(string $token, array $rips): array
    {
        [$status, $body] = ($this->transport)('POST', '/api/PaquetesFevRips/CargarRipsSinFactura', [
            'rips' => $rips,
            'xmlFevFile' => null,
        ], $token);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new HttpException(502, __('El validador respondió algo inesperado (HTTP %d). Revisa que el API Docker esté en ejecución.', $status));
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
            throw new HttpException(500, 'El servidor no tiene la extensión cURL de PHP.');
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $handle = curl_init(rtrim($this->baseUrl, '/') . $path);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            // El API Docker usa por defecto un certificado propio en
            // https://localhost:9443; la verificacion se puede desactivar
            // solo para ese caso desde la configuracion.
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            error_log('MUV no disponible: ' . $error);
            throw new HttpException(502, 'No pudimos conectar con el validador del Ministerio. Revisa que el API Docker esté en ejecución y la dirección configurada.');
        }

        return [$status, (string) $body];
    }
}
