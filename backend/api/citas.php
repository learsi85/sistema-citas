<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Obtener citas
if ($method === 'GET') {
    $fecha = $_GET['fecha'] ?? null;
    $servicio_id = $_GET['servicio_id'] ?? null;
    $cliente_id = $_GET['cliente_id'] ?? null;
    $estado = $_GET['estado'] ?? null;
    
    $query = "
        SELECT 
            c.*,
            s.nombre as servicio_nombre,
            s.max_integrantes,
            s.precio as servicio_precio,
            cl.nombre as cliente_nombre,
            cl.email as cliente_email,
            cl.telefono as cliente_telefono,
            p.nombre as proveedor_nombre,
            p.id as proveedor_id
        FROM citas c
        JOIN servicios s ON c.servicio_id = s.id
        JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN proveedores p ON c.proveedor_id = p.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($fecha) {
        $query .= " AND c.fecha = ?";
        $params[] = $fecha;
    }
    
    if ($servicio_id) {
        $query .= " AND c.servicio_id = ?";
        $params[] = $servicio_id;
    }
    
    if ($cliente_id) {
        $query .= " AND c.cliente_id = ?";
        $params[] = $cliente_id;
    }
    
    $proveedor_id = $_GET['proveedor_id'] ?? null;
    if ($proveedor_id) {
        $query .= " AND c.proveedor_id = ?";
        $params[] = $proveedor_id;
    }
    
    if ($estado) {
        $query .= " AND c.estado = ?";
        $params[] = $estado;
    } /* else {
        $query .= " AND c.estado != 'cancelada'";
    } */
    
    $query .= " ORDER BY c.fecha DESC, c.hora_inicio DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($citas);
}

// POST - Crear nueva cita
elseif ($method === 'POST') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, [
        'servicio_id', 'cliente_id', 'fecha', 'hora_inicio', 'hora_fin', 'num_integrantes'
    ]);
    
    // Validar que la fecha no sea en el pasado
    if (strtotime($datos['fecha']) < strtotime(date('Y-m-d'))) {
        jsonResponse(['error' => 'No se pueden agendar citas en fechas pasadas'], 400);
    }
    
    // Validar número de integrantes
    if ($datos['num_integrantes'] <= 0) {
        jsonResponse(['error' => 'El número de integrantes debe ser mayor a 0'], 400);
    }
    
    // Manejar asignación de proveedor
    $proveedor_id = null;
    $asignacion_automatica = false;
    
    if (isset($datos['proveedor_id']) && !empty($datos['proveedor_id'])) {
        // Proveedor específico seleccionado
        $proveedor_id = $datos['proveedor_id'];
        
        // Verificar que el proveedor esté asignado al servicio
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM servicios_proveedores 
            WHERE servicio_id = ? AND proveedor_id = ?
        ");
        $stmt->execute([$datos['servicio_id'], $proveedor_id]);
        
        if ($stmt->fetchColumn() == 0) {
            jsonResponse(['error' => 'El proveedor seleccionado no está asignado a este servicio'], 400);
        }
    } elseif (isset($datos['asignacion_automatica']) && $datos['asignacion_automatica'] === true) {
        // Asignación automática (al azar)
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM proveedores p
            INNER JOIN servicios_proveedores sp ON p.id = sp.proveedor_id
            WHERE sp.servicio_id = ? AND p.activo = 1
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute([$datos['servicio_id']]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($proveedor) {
            $proveedor_id = $proveedor['id'];
            $asignacion_automatica = true;
        }
    }
    
    // Verificar disponibilidad del servicio
    $stmt = $pdo->prepare("
        SELECT SUM(num_integrantes) as total_integrantes
        FROM citas
        WHERE servicio_id = ?
        AND fecha = ?
        AND hora_inicio = ?
        AND estado != 'cancelada'
    ");
    $stmt->execute([$datos['servicio_id'], $datos['fecha'], $datos['hora_inicio']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_actual = $result['total_integrantes'] ?? 0;
    
    // Obtener máximo de integrantes del servicio
    $stmt = $pdo->prepare("SELECT max_integrantes, nombre FROM servicios WHERE id = ?");
    $stmt->execute([$datos['servicio_id']]);
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicio) {
        jsonResponse(['error' => 'Servicio no encontrado'], 404);
    }
    
    $nuevo_total = $total_actual + $datos['num_integrantes'];
    
    if ($nuevo_total > $servicio['max_integrantes']) {
        jsonResponse([
            'error' => 'Cupo excedido',
            'mensaje' => "Esta cita ya tiene {$total_actual} integrantes. El máximo permitido es {$servicio['max_integrantes']}.",
            'disponible' => $servicio['max_integrantes'] - $total_actual,
            'solicitado' => $datos['num_integrantes']
        ], 400);
    }
    
    // Verificar que el cliente existe
    $stmt = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
    $stmt->execute([$datos['cliente_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    // Crear la cita
    $stmt = $pdo->prepare("
        INSERT INTO citas (
            servicio_id, proveedor_id, cliente_id, fecha, hora_inicio, hora_fin,
            num_integrantes, estado, notas
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
    ");
    
    $stmt->execute([
        $datos['servicio_id'],
        $datos['proveedor_id'],
        $datos['cliente_id'],
        $datos['fecha'],
        $datos['hora_inicio'],
        $datos['hora_fin'],
        $datos['num_integrantes'],
        $datos['notas'] ?? ''
    ]);
    
    $cita_id = $pdo->lastInsertId();
    
    // Enviar correo de confirmación al cliente
    $mensaje = "
        <h2>Confirmación de Cita</h2>
        <p>Hola <strong>{$cliente['nombre']}</strong>,</p>
        <p>Tu cita ha sido agendada exitosamente con los siguientes detalles:</p>
        <ul>
            <li><strong>Servicio:</strong> {$servicio['nombre']}</li>
            <li><strong>Fecha:</strong> {$datos['fecha']}</li>
            <li><strong>Hora:</strong> {$datos['hora_inicio']}</li>
            <li><strong>Integrantes:</strong> {$datos['num_integrantes']}</li>
        </ul>
        <p>Recibirás un recordatorio 30 minutos antes de tu cita.</p>
        <p>¡Te esperamos!</p>
    ";
    
    enviarCorreo($cliente['email'], 'Confirmación de Cita', $mensaje);
    
    // Obtener email de la empresa para notificar
    $stmt_emp = $pdo->query("SELECT email, nombre FROM empresa LIMIT 1");
    $empresa = $stmt_emp->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa && $empresa['email']) {
        $mensajeAdmin = "
            <h2>Nueva Cita Agendada</h2>
            <ul>
                <li><strong>Cliente:</strong> {$cliente['nombre']} ({$cliente['email']})</li>
                <li><strong>Servicio:</strong> {$servicio['nombre']}</li>
                <li><strong>Fecha:</strong> {$datos['fecha']}</li>
                <li><strong>Hora:</strong> {$datos['hora_inicio']}</li>
                <li><strong>Integrantes:</strong> {$datos['num_integrantes']}</li>
            </ul>
        ";
        enviarCorreo($empresa['email'], 'Nueva Cita Agendada', $mensajeAdmin);
    }
    
    jsonResponse([
        'success' => true,
        'id' => $cita_id,
        'mensaje' => 'Cita agendada correctamente'
    ], 201);
}

// PUT - Actualizar estado de la cita
elseif ($method === 'PUT') {
    $datos = obtenerDatosJSON();
    
    validarCamposRequeridos($datos, ['id', 'estado']);
    
    $estados_validos = ['pendiente', 'confirmada', 'cancelada', 'completada'];
    if (!in_array($datos['estado'], $estados_validos)) {
        jsonResponse(['error' => 'Estado inválido'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
    $stmt->execute([$datos['estado'], $datos['id']]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Cita no encontrada'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Estado de la cita actualizado correctamente'
    ]);
}

// DELETE - Cancelar cita
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID de la cita es requerido'], 400);
    }
    
    // Obtener información de la cita antes de cancelarla
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, s.nombre as servicio_nombre
        FROM citas c
        JOIN clientes cl ON c.cliente_id = cl.id
        JOIN servicios s ON c.servicio_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cita) {
        jsonResponse(['error' => 'Cita no encontrada'], 404);
    }
    
    // Cancelar la cita
    $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
    $stmt->execute([$id]);
    
    // Enviar notificación de cancelación
    $mensaje = "
        <h2>Cita Cancelada</h2>
        <p>Hola <strong>{$cita['cliente_nombre']}</strong>,</p>
        <p>Tu cita ha sido cancelada:</p>
        <ul>
            <li><strong>Servicio:</strong> {$cita['servicio_nombre']}</li>
            <li><strong>Fecha:</strong> {$cita['fecha']}</li>
            <li><strong>Hora:</strong> {$cita['hora_inicio']}</li>
        </ul>
        <p>Si tienes alguna duda, por favor contáctanos.</p>
    ";
    
    enviarCorreo($cita['cliente_email'], 'Cita Cancelada', $mensaje);
    
    jsonResponse([
        'success' => true,
        'mensaje' => 'Cita cancelada correctamente'
    ]);
}

else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
?>