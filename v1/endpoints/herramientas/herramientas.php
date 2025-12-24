<?php

require_once __DIR__ . '/../../../config.php';
require_once BASE_PATH . '/common/database.php';
require_once BASE_PATH . '/common/validators.php';
require_once BASE_PATH . '/common/response.php';

require_once __DIR__ . '/herramientas.model.php';

/* =========================
   CONFIG
   ========================= */

define('ALLOWED_METHODS', ['GET']);

header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
header("Access-Control-Allow-Methods: " . implode(', ', ALLOWED_METHODS));
header("Content-Type: " . DEFAULT_CONTENT_TYPE);

/* =========================
   GET - Inventario de herramientas
   ========================= */

function handleGetInventario(): void
{
    try {
        $model = new HerramientasModel();
        $inventario = $model->getInventario();

        sendJsonResponse(
            200,
            $inventario,
            "Inventario de herramientas cargado correctamente"
        );
    } catch (Throwable $e) {
        sendJsonResponse(500, null, $e->getMessage());
    }
}

/* =========================
   ROUTING
   ========================= */

validateMethod(ALLOWED_METHODS);

if (!validateAuth()) {
    exit;
}

handleGetInventario();