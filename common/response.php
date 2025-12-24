<?php
/**
 * Envía una respuesta JSON
 */
function sendJsonResponse(
    int $statusCode = 200,
    ?array $data = null,
    ?string $customMessage = null
): void {
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=UTF-8");

    $isError = $statusCode >= 400;
    $response = [
        'status' => $statusCode,
        'message' => $customMessage ?? ($isError ? getErrorText($statusCode) : getSuccessText($statusCode)),
        'data' => $isError ? null : $data
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function getSuccessText(int $statusCode): string {
    return match($statusCode) {
        200 => 'Operación exitosa',
        201 => 'Recurso creado exitosamente',
        204 => 'Recurso eliminado exitosamente',
        default => 'Operación completada',
    };
}

function getErrorText(int $statusCode): string {
    return match($statusCode) {
        400 => 'Petición inválida',
        401 => 'No autorizado',
        403 => 'Permisos insuficientes',
        404 => 'No encontrado',
        405 => 'Método no permitido',
        409 => 'Conflicto en la petición',
        501 => 'Método no implementado',
        default => 'Error desconocido',
    };
}

?>