<?php

require_once __DIR__ . '/../../../config.php';

require_once BASE_PATH . '/common/database.php';
require_once BASE_PATH . '/common/validators.php';
require_once BASE_PATH . '/common/response.php';

require_once __DIR__ . '/movimientos.model.php';

/* =========================
   CONFIG
   ========================= */

define('ALLOWED_METHODS', ['POST']);

header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
header("Access-Control-Allow-Methods: " . implode(', ', ALLOWED_METHODS));
header("Content-Type: " . DEFAULT_CONTENT_TYPE);

/* =========================
   POST - Devolver préstamo
   ========================= */
function handleReturnRequest(): void
{
    $nMovimiento = getIdFromQuery('n_movimiento');

    if (!validatePositiveInt($nMovimiento)) {
        sendJsonResponse(400, null, "n_movimiento inválido");
        return;
    }

    try {
        $model = new MovimientosModel();
        $model->devolverPrestamo((int)$nMovimiento);

        sendJsonResponse(
            200,
            null,
            "Préstamo devuelto correctamente"
        );

    } catch (Throwable $e) {
        sendJsonResponse(400, null, $e->getMessage());
    }
}

/* =========================
   ROUTING
   ========================= */

validateMethod(ALLOWED_METHODS);

if (!validateAuth()) {
    exit;
}

handleReturnRequest();