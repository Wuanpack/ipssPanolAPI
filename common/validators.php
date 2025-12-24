<?php

/* =========================
   QUERY PARAMS
   ========================= */

function getIdFromQuery(string $paramName = 'id'): ?string
{
    return $_GET[$paramName] ?? null;
}

/* =========================
   AUTH
   ========================= */

function validateAuth(): bool
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? null;

    if (!$auth) {
        sendJsonResponse(401, null, "Token no enviado");
        return false;
    }

    if ($auth !== 'Bearer ' . AUTH_TOKEN) {
        sendJsonResponse(403, null, "Token inválido");
        return false;
    }

    return true;
}

/* =========================
   METHODS
   ========================= */
function validateMethod(array $allowed): void
{
    $method = $_SERVER['REQUEST_METHOD'];

    if (!in_array($method, $allowed, true)) {
        sendJsonResponse(405, null, "Método no permitido");
        exit;
    }
}

/* =========================
   JSON
   ========================= */

function validateJsonInput(): array
{
    $data = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendJsonResponse(400, null, "JSON inválido");
        exit;
    }

    return $data;
}

/* =========================
   TIPOS BÁSICOS
   ========================= */

function validatePositiveInt($value): bool
{
    return is_numeric($value) && (int)$value > 0;
}

function validateString(string $value, int $min = 1, int $max = 255): bool
{
    $len = mb_strlen(trim($value));
    return $len >= $min && $len <= $max;
}

/* =========================
   RUT
   ========================= */

/**
 * Valida un RUT chileno utilizando el algoritmo oficial de dígito verificador
 * (módulo 11).
 *
 * La validación NO se basa solo en el formato, sino en un cálculo matemático
 * que permite detectar errores de digitación y números inventados.
 */
function validateRut(string $rut): bool
{
    /**
     * 1. Normalización del RUT
     * Se eliminan puntos, guiones y cualquier carácter no válido,
     * dejando únicamente números y la letra K/k.
     *
     * Ejemplo:
     *  "12.345.678-5" → "123456785"
     */
    $rut = preg_replace('/[^0-9kK]/', '', $rut);

    /**
     * 2. Validación mínima de longitud
     * Un RUT debe tener al menos:
     * - un número base
     * - un dígito verificador
     */
    if (strlen($rut) < 2) {
        return false;
    }

    /**
     * 3. Separación de componentes
     * - $numero: parte numérica del RUT
     * - $dv: dígito verificador ingresado por el usuario
     */
    $dv = strtoupper(substr($rut, -1));
    $numero = substr($rut, 0, -1);

    /**
     * 4. Validación de la parte numérica
     * El cuerpo del RUT debe contener solo dígitos.
     * Si contiene letras u otros caracteres, es inválido.
     */
    if (!ctype_digit($numero)) {
        return false;
    }

    /**
     * 5. Cálculo del dígito verificador (módulo 11)
     *
     * Se recorren los dígitos del número de derecha a izquierda,
     * multiplicándolos por factores cíclicos que van del 2 al 7.
     *
     * Este esquema garantiza que:
     * - cambiar un dígito altere el resultado
     * - se detecten errores de digitación y transposición
     */
    $suma = 0;
    $multiplo = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $multiplo;
        $multiplo = ($multiplo === 7) ? 2 : $multiplo + 1;
    }

    /**
     * 6. Obtención del valor base del dígito verificador
     * Se aplica la operación módulo 11, que produce un valor
     * entre 0 y 11.
     */
    $resto = 11 - ($suma % 11);

    /**
     * 7. Conversión del resultado al formato oficial del DV
     *
     * Reglas:
     * - 11 → '0'
     * - 10 → 'K'
     * - cualquier otro valor → el número correspondiente
     */
    $dvCalculado = match ($resto) {
        11 => '0',
        10 => 'K',
        default => (string)$resto
    };

    /**
     * 8. Comparación final
     * El RUT es válido solo si el dígito verificador calculado
     * coincide exactamente con el ingresado.
     */
    return $dv === $dvCalculado;
}

/* =========================
   DOMINIO PAÑOL
   ========================= */

function validateNumeroParte(string $nParte): bool
{
    return preg_match('/^[A-Z0-9\-]{3,50}$/i', $nParte) === 1;
}
