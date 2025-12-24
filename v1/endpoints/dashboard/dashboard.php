<?php

require_once __DIR__ . '/../../../config.php';

require_once BASE_PATH . '/common/database.php';
require_once BASE_PATH . '/common/validators.php';
require_once BASE_PATH . '/common/response.php';

require_once __DIR__ . '/dashboard.model.php';

/* Configuración */
define('ALLOWED_METHODS', ['GET']);

/* Headers */
header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
header("Access-Control-Allow-Methods: " . implode(', ', ALLOWED_METHODS));
header("Content-Type: " . DEFAULT_CONTENT_TYPE);

/* =========================
   HANDLER GET
   ========================= */
function handleGetRequest(): void
{
    $model = new DashboardModel();
    $data = $model->getDashboardData();
    sendJsonResponse(200, $data);
}

/* =========================
   ROUTING
   ========================= */
$method = $_SERVER['REQUEST_METHOD'];

if (!validateAuth()) {
    exit;
}

switch ($method) {
    case 'GET':
        handleGetRequest();
        break;

    default:
        sendJsonResponse(501, null, "Método [ " . $method . " ] no implementado");
        break;
}