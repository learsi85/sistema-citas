<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$servicio_id = $_GET['servicio_id'] ?? null;
$fecha = $_GET['fecha'] ?? null;

if (!$servicio_id || !$fecha) {
    jsonResponse(['error' => 'servicio_id y fecha son requeridos'], 400);
}

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    jsonResponse(['error' => 'Formato de fecha inválido (use YYYY-MM-DD)'], 400);
}

// Validar que la fecha no sea en el pasado
if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
    jsonResponse(['error' => 'No se puede consultar disponibilidad para fechas pasadas'], 400);
}

// Obtener el servicio
$stmt = $pdo->prepare("SELECT * FROM servicios WHERE id = ? AND activo = 1");
$stmt->execute([$servicio_id]);
$servicio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$servicio) {
    jsonResponse(['error' => 'Servicio no encontrado o inactivo'], 404);
}

// Obtener día de la semana (0 = Domingo, 6 = Sábado)
$dia_semana = date('w', strtotime($fecha));

// Obtener disponibilidad para ese día
$stmt = $pdo->prepare("
    SELECT * FROM disponibilidad 
    WHERE dia_semana = ? AND activo = 1
    ORDER BY hora_inicio
");
$stmt->execute([$dia_semana]);
$disponibilidad = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($disponibilidad)) {
    jsonResponse([
        'mensaje' => 'No hay disponibilidad para este día',
        'horarios' => []
    ]);
}

$horarios = [];

foreach ($disponibilidad as $disp) {
    $hora_actual = strtotime($disp['hora_inicio']);
    $hora_fin = strtotime($disp['hora_fin']);
    $duracion = $disp['duracion_cita'] * 60; // convertir a segundos
    
    while ($hora_actual < $hora_fin) {
        $hora_str = date('H:i:s', $hora_actual);
        $hora_fin_cita = date('H:i:s', $hora_actual + $duracion);
        
        // Verificar si ya pasó la hora (solo para hoy)
        $es_hoy = date('Y-m-d') === $fecha;
        $hora_pasada = false;
        
        if ($es_hoy) {
            $ahora = time();
            $hora_cita = strtotime($fecha . ' ' . $hora_str);
            $hora_pasada = $ahora >= $hora_cita;
        }
        
        // Obtener cuántos integrantes hay agendados en este horario
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(num_integrantes), 0) as total
            FROM citas
            WHERE servicio_id = ?
            AND fecha = ?
            AND hora_inicio = ?
            AND estado != 'cancelada'
        ");
        $stmt->execute([$servicio_id, $fecha, $hora_str]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ocupados = (int)$result['total'];
        
        $disponibles = $servicio['max_integrantes'] - $ocupados;
        $disponible = $disponibles > 0 && !$hora_pasada;
        
        $horarios[] = [
            'hora_inicio' => $hora_str,
            'hora_fin' => $hora_fin_cita,
            'ocupados' => $ocupados,
            'disponibles' => max(0, $disponibles),
            'max_integrantes' => (int)$servicio['max_integrantes'],
            'disponible' => $disponible,
            'razon' => !$disponible ? ($hora_pasada ? 'Hora pasada' : 'Sin cupo') : null
        ];
        
        $hora_actual += $duracion;
    }
}

jsonResponse([
    'fecha' => $fecha,
    'dia_semana' => $dia_semana,
    'servicio' => [
        'id' => $servicio['id'],
        'nombre' => $servicio['nombre'],
        'max_integrantes' => $servicio['max_integrantes']
    ],
    'horarios' => $horarios
]);
?>