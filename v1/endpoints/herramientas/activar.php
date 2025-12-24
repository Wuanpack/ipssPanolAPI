<?php

require_once __DIR__ . '/../../../config.php';
require_once BASE_PATH . '/common/database.php';
require_once BASE_PATH . '/common/validators.php';
require_once BASE_PATH . '/common/response.php';
require_once __DIR__ . '/herramientas.model.php';

/* =========================
   CONFIG
   ========================= */
define('ALLOWED_METHODS', ['PATCH']);

header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
header("Access-Control-Allow-Methods: " . implode(', ', ALLOWED_METHODS));
header("Content-Type: " . DEFAULT_CONTENT_TYPE);

/* =========================
   VALIDACIONES GENERALES
   ========================= */
validateMethod(ALLOWED_METHODS);

if (!validateAuth()) exit;

/* =========================
   OBTENER ID DE LA HERRAMIENTA
   ========================= */
$id = getIdFromQuery('id');

if (!validatePositiveInt($id)) {
    sendJsonResponse(400, null, "ID de herramienta inválido");
    exit;
}

try {
    $model = new HerramientasModel();
    $model->setEstadoHerramienta((int)$id, 1); // 1 = activar

    sendJsonResponse(
        200,
        null,
        "Herramienta activada correctamente"
    );

} catch (Throwable $e) {
    sendJsonResponse(400, null, $e->getMessage());
}