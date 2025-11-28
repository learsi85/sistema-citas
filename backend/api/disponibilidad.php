<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener horarios de disponibilidad
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT * FROM disponibilidad 
        WHERE activo = 1 
        ORDER BY dia_semana, hora_inicio
    ");
    $disponibilidad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($disponibilidad);
}

// POST - Crear nuevo horario de disponibilidad
elseif ($method === 'POST') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita']);
    
    // Validar día de la semana (0-6)
    if ($datos['dia_semana'] < 0 || $datos['dia_semana'] > 6) {
        jsonResponse(['error' => 'Día de la semana inválido (debe estar entre 0 y 6)'], 400);
    }
    
    // Validar que hora_fin sea mayor que hora_inicio
    if (strtotime($datos['hora_fin']) <= strtotime($datos['hora_inicio'])) {
        jsonResponse(['error' => 'La hora de fin debe ser posterior a la hora de inicio'], 400);
    }
    
    // Validar duración de la cita
    if ($datos['duracion_cita'] <= 0 || $datos['duracion_cita'] > 480) {
        jsonResponse(['error' => 'La duración de la cita debe estar entre 1 y 480 minutos'], 400);
    }
    
    // Verificar que no haya solapamiento de horarios para el mismo día
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM disponibilidad 
        WHERE dia_semana = ? 
        AND activo = 1
        AND (
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio >= ? AND hora_fin <= ?)
        )
    ");
    $stmt->execute([
        $datos['dia_semana'],
        $datos['hora_fin'], $datos['hora_inicio'],
        $datos['hora_fin'], $datos['hora_fin'],
        $datos['hora_inicio'], $datos['hora_fin']
    ]);
    
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(['error' => 'Ya existe un horario que se solapa con el especificado'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO disponibilidad (dia_semana, hora_inicio, hora_fin, duracion_cita) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $datos['dia_semana'],
        $datos['hora_inicio'],
        $datos['hora_fin'],
        $datos['duracion_cita']
    ]);
    
    jsonResponse([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'mensaje' => 'Horario de disponibilidad creado correctamente'
    ], 201);
}

// DELETE - Eliminar horario de disponibilidad
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID del horario es requerido'], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM disponibilidad WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Horario no encontrado'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Horario eliminado correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>