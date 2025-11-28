<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener datos de la empresa
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        jsonResponse(['error' => 'No se encontró información de la empresa'], 404);
    }
    
    jsonResponse($empresa);
}

// PUT - Actualizar datos de la empresa
elseif ($method === 'PUT') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['nombre']);
    
    if (isset($datos['email']) && !validarEmail($datos['email'])) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE empresa 
        SET nombre = ?, 
            direccion = ?, 
            telefono = ?, 
            email = ?, 
            descripcion = ? 
        WHERE id = 1
    ");
    
    $stmt->execute([
        $datos['nombre'],
        $datos['direccion'] ?? '',
        $datos['telefono'] ?? '',
        $datos['email'] ?? '',
        $datos['descripcion'] ?? ''
    ]);
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Datos de empresa actualizados correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>