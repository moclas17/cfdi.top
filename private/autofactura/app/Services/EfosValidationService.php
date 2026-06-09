<?php
/**
 * Servicio: EfosValidationService
 * Valida el RFC contra el endpoint EFOS de efectosfiscales.mx.
 */

class EfosValidationService
{
    private const ENDPOINT = 'https://efectosfiscales.mx/efos/';

    public static function validateRfc(string $rfc): array
    {
        $normalizedRfc = strtoupper(trim($rfc));
        if ($normalizedRfc === '') {
            return self::emptyResult($normalizedRfc);
        }

        if (!function_exists('curl_init')) {
            return self::emptyResult($normalizedRfc, 'cURL no disponible para validar EFOS.');
        }

        $url = self::ENDPOINT . '?rfc=' . rawurlencode($normalizedRfc);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/html, text/plain;q=0.9, */*;q=0.8',
                'User-Agent: AutoFactura/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return self::emptyResult($normalizedRfc, 'No se pudo consultar EFOS.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return self::emptyResult($normalizedRfc, 'Respuesta EFOS no disponible.');
        }

        return self::parseResponse($normalizedRfc, (string) $response);
    }

    private static function parseResponse(string $rfc, string $response): array
    {
        $raw = trim($response);
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return self::parseJsonResponse($rfc, $json, $raw);
        }

        $plain = strtolower(self::normalizeWhitespace(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

        if ($plain === '' || self::containsAny($plain, [
            'no se encontro',
            'no se encontró',
            'sin resultados',
            'no existe',
            'no localizado',
        ])) {
            return self::emptyResult($rfc, null, $raw);
        }

        $status = null;
        $severity = null;

        if (self::containsAny($plain, ['sentencia favorable'])) {
            $status = 'Sentencia Favorable';
            $severity = 'warning';
        } elseif (self::containsAny($plain, ['desvirtuado', 'desvirtuada'])) {
            $status = 'Desvirtuado';
            $severity = 'warning';
        } elseif (self::containsAny($plain, ['definitivo', 'definitiva'])) {
            $status = 'Definitivo';
            $severity = 'blocked';
        } elseif (self::containsAny($plain, ['presunto', 'presunta'])) {
            $status = 'Presunto';
            $severity = 'blocked';
        }

        if ($status === null) {
            return self::emptyResult($rfc, null, $raw);
        }

        $message = $severity === 'blocked'
            ? 'No es posible generar la factura porque el RFC aparece con estatus EFOS ' . $status . '.'
            : 'Advertencia EFOS: el RFC aparece con estatus ' . $status . '. Puedes continuar.';

        return [
            'checked' => true,
            'found' => true,
            'rfc' => $rfc,
            'status' => $status,
            'severity' => $severity,
            'allow_continue' => $severity !== 'blocked',
            'message' => $message,
            'raw' => $raw,
        ];
    }

    private static function parseJsonResponse(string $rfc, array $json, string $raw): array
    {
        $found = !empty($json['encontrado']);
        $status = trim((string) ($json['datos']['situacion'] ?? $json['situacion'] ?? ''));

        if (!$found || $status === '') {
            return self::emptyResult($rfc, null, $raw);
        }

        $normalizedStatus = strtolower(self::normalizeWhitespace($status));
        $severity = null;

        if (self::containsAny($normalizedStatus, ['sentencia favorable'])) {
            $status = 'Sentencia Favorable';
            $severity = 'warning';
        } elseif (self::containsAny($normalizedStatus, ['desvirtuado', 'desvirtuada'])) {
            $status = 'Desvirtuado';
            $severity = 'warning';
        } elseif (self::containsAny($normalizedStatus, ['definitivo', 'definitiva'])) {
            $status = 'Definitivo';
            $severity = 'blocked';
        } elseif (self::containsAny($normalizedStatus, ['presunto', 'presunta'])) {
            $status = 'Presunto';
            $severity = 'blocked';
        }

        if ($severity === null) {
            return self::emptyResult($rfc, null, $raw);
        }

        $message = $severity === 'blocked'
            ? 'No es posible generar la factura porque el RFC aparece con estatus EFOS ' . $status . '.'
            : 'Advertencia EFOS: el RFC aparece con estatus ' . $status . '. Puedes continuar.';

        return [
            'checked' => true,
            'found' => true,
            'rfc' => $rfc,
            'status' => $status,
            'severity' => $severity,
            'allow_continue' => $severity !== 'blocked',
            'message' => $message,
            'raw' => $raw,
        ];
    }

    private static function emptyResult(string $rfc, ?string $message = null, ?string $raw = null): array
    {
        return [
            'checked' => $message === null,
            'found' => false,
            'rfc' => $rfc,
            'status' => null,
            'severity' => null,
            'allow_continue' => true,
            'message' => $message,
            'raw' => $raw,
        ];
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
