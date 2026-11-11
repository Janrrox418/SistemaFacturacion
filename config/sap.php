<?php

$sapApiUrl = getenv('SAP_API_URL');
if ($sapApiUrl === false || $sapApiUrl === '') {
    error_log('SAP_API_URL is required.');
    http_response_code(500);
    exit('La configuración del servicio SAP está incompleta.');
}

define('SAP_API_URL', $sapApiUrl);
define('SAP_API_CLIENT', getenv('SAP_API_CLIENT') ?: '401');
define('SAP_API_USERNAME', getenv('SAP_API_USERNAME') ?: null);
define('SAP_API_PASSWORD', getenv('SAP_API_PASSWORD') ?: null);
