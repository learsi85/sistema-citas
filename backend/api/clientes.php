<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener todos los clientes
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($clientes);
}

// POST - Crear o buscar cliente
elseif ($method === 'POST') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['nombre', 'email']);
    
    if (!validarEmail($datos['email'])) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    
    if (isset($datos['telefono']) && !empty($datos['telefono'])) {
        if (!validarTelefono($datos['telefono'])) {
            jsonResponse(['error' => 'Teléfono inválido'], 400);
        }
    }
    
    // Buscar si el cliente ya existe por email
    $stmt = $pdo->prepare("SELECT id, nombre, email, telefono FROM clientes WHERE email = ?");
    $stmt->execute([$datos['email']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente) {
        // Cliente existente - actualizar datos si es necesario
        $stmt = $pdo->prepare("
            UPDATE clientes 
            SET nombre = ?, telefono = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $datos['nombre'],
            $datos['telefono'] ?? '',
            $cliente['id']
        ]);
        
        jsonResponse([
            'success' => true,
            'id' => $cliente['id'],
            'mensaje' => 'Cliente encontrado',
            'nuevo' => false
        ]);
    } else {
        // Crear nuevo cliente
        $stmt = $pdo->prepare("
            INSERT INTO clientes (nombre, email, telefono) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $datos['nombre'],
            $datos['email'],
            $datos['telefono'] ?? ''
        ]);
        
        jsonResponse([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'mensaje' => 'Cliente registrado correctamente',
            'nuevo' => true
        ], 201);
    }
}

// PUT - Actualizar cliente
elseif ($method === 'PUT') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['id', 'nombre', 'email']);
    
    if (!validarEmail($datos['email'])) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    
    if (isset($datos['telefono']) && !empty($datos['telefono'])) {
        if (!validarTelefono($datos['telefono'])) {
            jsonResponse(['error' => 'Teléfono inválido'], 400);
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE clientes 
        SET nombre = ?, email = ?, telefono = ? 
        WHERE id = ?
    ");
    
    $stmt->execute([
        $datos['nombre'],
        $datos['email'],
        $datos['telefono'] ?? '',
        $datos['id']
    ]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Cliente actualizado correctamente'
    ]);
}

// DELETE - Eliminar cliente
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID del cliente es requerido'], 400);
    }
    
    // Verificar si tiene citas activas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM citas 
        WHERE cliente_id = ? AND estado != 'cancelada'
    ");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        jsonResponse([
            'error' => 'No se puede eliminar el cliente porque tiene citas activas',
            'citas_activas' => $count
        ], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Cliente eliminado correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>