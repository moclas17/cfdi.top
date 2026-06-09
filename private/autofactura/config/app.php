<?php
/**
 * AutoFactura - Configuración de la aplicación
 */

return [
    'name'    => env('APP_NAME', 'AutoFactura'),
    'url'     => env('APP_URL', 'http://localhost/autofactura'),
    'env'     => env('APP_ENV', 'development'),
    'debug'   => env('APP_DEBUG', true),
    'cipher'  => 'AES-256-CBC',
    'base_path' => dirname(__DIR__),
];
