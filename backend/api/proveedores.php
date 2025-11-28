<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener proveedores
if ($method === 'GET') {
    $activo = $_GET['activo'] ?? null;
    $servicio_id = $_GET['servicio_id'] ?? null;
    
    if ($servicio_id) {
        // Obtener proveedores de un servicio específico
        $query = "
            SELECT p.* 
            FROM proveedores p
            INNER JOIN servicios_proveedores sp ON p.id = sp.proveedor_id
            WHERE sp.servicio_id = ?
        ";
        
        $params = [$servicio_id];
        
        if ($activo !== null) {
            $query .= " AND p.activo = ?";
            $params[] = ($activo === 'true' ? 1 : 0);
        }
        
        $query .= " ORDER BY p.nombre";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        // Obtener todos los proveedores
        $query = "SELECT * FROM proveedores";
        
        if ($activo !== null) {
            $query .= " WHERE activo = " . ($activo === 'true' ? 1 : 0);
        }
        
        $query .= " ORDER BY nombre";
        
        $stmt = $pdo->query($query);
    }
    
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar los servicios de cada proveedor
    foreach ($proveedores as &$proveedor) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nombre, s.clave 
            FROM servicios s
            INNER JOIN servicios_proveedores sp ON s.id = sp.servicio_id
            WHERE sp.proveedor_id = ?
        ");
        $stmt->execute([$proveedor['id']]);
        $proveedor['servicios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    jsonResponse($proveedores);
}

// POST - Crear nuevo proveedor
elseif ($method === 'POST') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['nombre']);
    
    if (isset($datos['email']) && !empty($datos['email']) && !validarEmail($datos['email'])) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Crear proveedor
        $stmt = $pdo->prepare("
            INSERT INTO proveedores (nombre, email, telefono, especialidad) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $datos['nombre'],
            $datos['email'] ?? '',
            $datos['telefono'] ?? '',
            $datos['especialidad'] ?? ''
        ]);
        
        $proveedor_id = $pdo->lastInsertId();
        
        // Asignar servicios si se proporcionaron
        if (isset($datos['servicios']) && is_array($datos['servicios'])) {
            $stmt = $pdo->prepare("
                INSERT INTO servicios_proveedores (servicio_id, proveedor_id) 
                VALUES (?, ?)
            ");
            
            foreach ($datos['servicios'] as $servicio_id) {
                $stmt->execute([$servicio_id, $proveedor_id]);
            }
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'id' => $proveedor_id,
            'mensaje' => 'Proveedor creado correctamente'
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al crear proveedor: ' . $e->getMessage()], 500);
    }
}

// PUT - Actualizar proveedor
elseif ($method === 'PUT') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['id', 'nombre']);
    
    if (isset($datos['email']) && !empty($datos['email']) && !validarEmail($datos['email'])) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar proveedor
        $stmt = $pdo->prepare("
            UPDATE proveedores 
            SET nombre = ?, 
                email = ?, 
                telefono = ?, 
                especialidad = ?, 
                activo = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $datos['nombre'],
            $datos['email'] ?? '',
            $datos['telefono'] ?? '',
            $datos['especialidad'] ?? '',
            $datos['activo'] ?? 1,
            $datos['id']
        ]);
        
        // Actualizar servicios asignados
        if (isset($datos['servicios'])) {
            // Eliminar asignaciones actuales
            $stmt = $pdo->prepare("DELETE FROM servicios_proveedores WHERE proveedor_id = ?");
            $stmt->execute([$datos['id']]);
            
            // Agregar nuevas asignaciones
            if (is_array($datos['servicios']) && count($datos['servicios']) > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO servicios_proveedores (servicio_id, proveedor_id) 
                    VALUES (?, ?)
                ");
                
                foreach ($datos['servicios'] as $servicio_id) {
                    $stmt->execute([$servicio_id, $datos['id']]);
                }
            }
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'mensaje' => 'Proveedor actualizado correctamente'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al actualizar proveedor: ' . $e->getMessage()], 500);
    }
}

// DELETE - Eliminar proveedor
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID del proveedor es requerido'], 400);
    }
    
    // Verificar si tiene citas activas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM citas 
        WHERE proveedor_id = ? AND estado != 'cancelada'
    ");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        jsonResponse([
            'error' => 'No se puede eliminar el proveedor porque tiene citas activas',
            'citas_activas' => $count
        ], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Proveedor eliminado correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>