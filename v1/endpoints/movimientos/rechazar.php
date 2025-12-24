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
   POST - Rechazar solicitud
   ========================= */
function handleRejectRequest(): void
{
    /* n_movimiento desde query */
    $nMovimiento = getIdFromQuery('n_movimiento');

    if (!validatePositiveInt($nMovimiento)) {
        sendJsonResponse(400, null, "n_movimiento inválido");
        return;
    }

    /* Body JSON (opcional) */
    $data = validateJsonInput();

    $motivo = null;

    if (isset($data['motivo'])) {
        if (!validateString($data['motivo'], 3, 255)) {
            sendJsonResponse(400, null, "Motivo de rechazo inválido");
            return;
        }
        $motivo = trim($data['motivo']);
    }

    try {
        $model = new MovimientosModel();
        $model->rechazarSolicitud((int)$nMovimiento, $motivo);

        sendJsonResponse(
            200,
            null,
            "Solicitud rechazada correctamente"
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

handleRejectRequest();