<?php
// Funciones de utilidad

// Validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validar teléfono
function validarTelefono($telefono) {
    return preg_match('/^[0-9\-\+\(\)\s]{10,}$/', $telefono);
}

// Obtener datos del cuerpo de la petición
function obtenerDatosJSON() {
    $datos = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'JSON inválido'], 400);
    }
    return $datos;
}

// Validar campos requeridos
function validarCamposRequeridos($datos, $campos) {
    foreach ($campos as $campo) {
        if (!isset($datos[$campo]) || empty($datos[$campo])) {
            jsonResponse(['error' => "El campo '$campo' es requerido"], 400);
        }
    }
    return true;
}

// Formatear fecha para MySQL
function formatearFecha($fecha) {
    $timestamp = strtotime($fecha);
    return date('Y-m-d', $timestamp);
}

// Formatear hora para MySQL
function formatearHora($hora) {
    $timestamp = strtotime($hora);
    return date('H:i:s', $timestamp);
}
?>