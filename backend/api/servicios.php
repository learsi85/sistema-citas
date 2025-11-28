<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener todos los servicios
if ($method === 'GET') {
    $activo = $_GET['activo'] ?? null;
    
    $query = "SELECT * FROM servicios";
    if ($activo !== null) {
        $query .= " WHERE activo = " . ($activo === 'true' ? 1 : 0);
    }
    $query .= " ORDER BY nombre";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $servicios = $stmt->fetchAll();
    http_response_code(200);
    echo json_encode($servicios);
    //jsonResponse($servicios);
}

// POST - Crear nuevo servicio
elseif ($method === 'POST') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['clave', 'nombre', 'precio', 'max_integrantes']);
    
    // Validar que el precio sea positivo
    if ($datos['precio'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'El precio debe ser mayor a 0']);
        //jsonResponse(['error' => 'El precio debe ser mayor a 0'], 400);
    }
    
    // Validar que max_integrantes sea positivo
    if ($datos['max_integrantes'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'El número de integrantes debe ser mayor a 0']);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO servicios (clave, nombre, descripcion, precio, max_integrantes) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $datos['clave'],
            $datos['nombre'],
            $datos['descripcion'] ?? '',
            $datos['precio'],
            $datos['max_integrantes']
        ]);
        
        jsonResponse([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'mensaje' => 'Servicio creado correctamente'
        ], 201);
        
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) { // Violación de clave única
            jsonResponse(['error' => 'La clave del servicio ya existe'], 400);
        }
        throw $e;
    }
}

// PUT - Actualizar servicio existente
elseif ($method === 'PUT') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['id', 'clave', 'nombre', 'precio', 'max_integrantes']);
    
    // Validar que el precio sea positivo
    if ($datos['precio'] <= 0) {
        jsonResponse(['error' => 'El precio debe ser mayor a 0'], 400);
    }
    
    // Validar que max_integrantes sea positivo
    if ($datos['max_integrantes'] <= 0) {
        jsonResponse(['error' => 'El número de integrantes debe ser mayor a 0'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE servicios 
            SET clave = ?, 
                nombre = ?, 
                descripcion = ?, 
                precio = ?, 
                max_integrantes = ?, 
                activo = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $datos['clave'],
            $datos['nombre'],
            $datos['descripcion'] ?? '',
            $datos['precio'],
            $datos['max_integrantes'],
            $datos['activo'] ?? 1,
            $datos['id']
        ]);
        
        jsonResponse([
            'success' => true,
            'mensaje' => 'Servicio actualizado correctamente'
        ]);
        
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) { // Violación de clave única
            jsonResponse(['error' => 'La clave del servicio ya existe'], 400);
        }
        throw $e;
    }
}

// DELETE - Eliminar servicio
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID del servicio es requerido'], 400);
    }
    
    // Verificar si hay citas asociadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE servicio_id = ? AND estado != 'cancelada'");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        jsonResponse([
            'error' => 'No se puede eliminar el servicio porque tiene citas activas',
            'citas_activas' => $count
        ], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM servicios WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Servicio eliminado correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>