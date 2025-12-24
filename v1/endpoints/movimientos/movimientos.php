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
   POST - Crear solicitud
   ========================= */
function handlePostRequest(): void
{
    $input = validateJsonInput();

    /* Campos requeridos */
    $required = ['rut', 'lugar_id', 'n_parte', 'cantidad'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendJsonResponse(400, null,  "Campo requerido: $field");
            return;
        }
    }

    /* Validaciones */
    if (!validateRut($input['rut'])) {
        sendJsonResponse(400, null,  "Formato de RUT inválido");
        return;
    }

    if (!validatePositiveInt($input['lugar_id'])) {
        sendJsonResponse(400, null,  "Lugar inválido");
        return;
    }

    $nParte = strtoupper(trim($input['n_parte']));
    if (!validateNumeroParte($nParte)) {
        sendJsonResponse(400, null,  "Número de parte inválido");
        return;
    }

    if (!validatePositiveInt($input['cantidad'])) {
        sendJsonResponse(400, null,  "Cantidad inválida");
        return;
    }

    try {
        $model = new MovimientosModel();

        $nMovimiento = $model->crearSolicitud(
            $input['rut'],
            (int)$input['lugar_id'],
            $input['n_parte'],
            (int)$input['cantidad']
        );

        sendJsonResponse(
            201,
            ['n_movimiento' => $nMovimiento],
            "Solicitud registrada correctamente"
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

handlePostRequest();