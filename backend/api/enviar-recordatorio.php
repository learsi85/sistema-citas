<?php
require_once '../config.php';
require_once '../utils.php';

setCorsHeaders();
$pdo = getDBConnection();

// Este script debe ser ejecutado por un cron job cada hora
// Ejemplo: 0 * * * * /usr/bin/php /ruta/api/enviar-recordatorios.php

// Calcular el rango de tiempo (30 minutos antes de la cita)
$ahora = new DateTime();
$limite_superior = clone $ahora;
$limite_superior->modify('+30 minutes');

// Buscar citas que deben recibir recordatorio
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        cl.nombre as cliente_nombre,
        cl.email as cliente_email,
        cl.telefono as cliente_telefono,
        s.nombre as servicio_nombre,
        s.descripcion as servicio_descripcion
    FROM citas c
    JOIN clientes cl ON c.cliente_id = cl.id
    JOIN servicios s ON c.servicio_id = s.id
    WHERE c.email_recordatorio_enviado = 0
    AND c.estado = 'pendiente'
    AND CONCAT(c.fecha, ' ', c.hora_inicio) > NOW()
    AND CONCAT(c.fecha, ' ', c.hora_inicio) <= ?
");

$stmt->execute([$limite_superior->format('Y-m-d H:i:s')]);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enviados = 0;
$errores = 0;
$log = [];

// Obtener información de la empresa
$stmt_emp = $pdo->query("SELECT * FROM empresa LIMIT 1");
$empresa = $stmt_emp->fetch(PDO::FETCH_ASSOC);

foreach ($citas as $cita) {
    try {
        // Formatear fecha y hora
        $fecha_formateada = date('d/m/Y', strtotime($cita['fecha']));
        $hora_formateada = date('H:i', strtotime($cita['hora_inicio']));
        
        // Mensaje para el cliente
        $mensajeCliente = "
            <h2>Recordatorio de Cita</h2>
            <p>Hola <strong>{$cita['cliente_nombre']}</strong>,</p>
            <p>Este es un recordatorio de tu próxima cita:</p>
            <div style='background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Servicio:</strong> {$cita['servicio_nombre']}</p>
                <p style='margin: 5px 0;'><strong>Fecha:</strong> {$fecha_formateada}</p>
                <p style='margin: 5px 0;'><strong>Hora:</strong> {$hora_formateada}</p>
                <p style='margin: 5px 0;'><strong>Integrantes:</strong> {$cita['num_integrantes']}</p>
            </div>
            <p>Te esperamos 10 minutos antes de tu cita.</p>
        ";
        
        if ($empresa) {
            $mensajeCliente .= "
                <hr style='margin: 20px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    <strong>{$empresa['nombre']}</strong><br>
                    {$empresa['direccion']}<br>
                    📞 {$empresa['telefono']}<br>
                    📧 {$empresa['email']}
                </p>
            ";
        }
        
        // Enviar recordatorio al cliente
        $resultadoCliente = enviarCorreo(
            $cita['cliente_email'],
            'Recordatorio de Cita - ' . $cita['servicio_nombre'],
            $mensajeCliente
        );
        
        // Mensaje para el administrador
        if ($empresa && $empresa['email']) {
            $mensajeAdmin = "
                <h2>Recordatorio: Cita Próxima</h2>
                <p>Tienes una cita programada en los próximos 30 minutos:</p>
                <div style='background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Cliente:</strong> {$cita['cliente_nombre']}</p>
                    <p style='margin: 5px 0;'><strong>Email:</strong> {$cita['cliente_email']}</p>
                    <p style='margin: 5px 0;'><strong>Teléfono:</strong> {$cita['cliente_telefono']}</p>
                    <p style='margin: 5px 0;'><strong>Servicio:</strong> {$cita['servicio_nombre']}</p>
                    <p style='margin: 5px 0;'><strong>Fecha:</strong> {$fecha_formateada}</p>
                    <p style='margin: 5px 0;'><strong>Hora:</strong> {$hora_formateada}</p>
                    <p style='margin: 5px 0;'><strong>Integrantes:</strong> {$cita['num_integrantes']}</p>
                </div>
            ";
            
            if (!empty($cita['notas'])) {
                $mensajeAdmin .= "
                    <p><strong>Notas del cliente:</strong></p>
                    <p style='background: #fef3c7; padding: 10px; border-radius: 5px;'>{$cita['notas']}</p>
                ";
            }
            
            $resultadoAdmin = enviarCorreo(
                $empresa['email'],
                'Recordatorio: Cita Próxima - ' . $cita['cliente_nombre'],
                $mensajeAdmin
            );
        }
        
        // Marcar como enviado
        $stmt_update = $pdo->prepare("
            UPDATE citas 
            SET email_recordatorio_enviado = 1 
            WHERE id = ?
        ");
        $stmt_update->execute([$cita['id']]);
        
        $enviados++;
        $log[] = [
            'cita_id' => $cita['id'],
            'cliente' => $cita['cliente_nombre'],
            'fecha_hora' => $cita['fecha'] . ' ' . $cita['hora_inicio'],
            'estado' => 'enviado'
        ];
        
    } catch (Exception $e) {
        $errores++;
        $log[] = [
            'cita_id' => $cita['id'],
            'cliente' => $cita['cliente_nombre'],
            'fecha_hora' => $cita['fecha'] . ' ' . $cita['hora_inicio'],
            'estado' => 'error',
            'mensaje' => $e->getMessage()
        ];
        
        error_log("Error enviando recordatorio para cita {$cita['id']}: " . $e->getMessage());
    }
}

// Registrar en log
$log_message = sprintf(
    "[%s] Recordatorios procesados: %d enviados, %d errores de %d citas encontradas",
    date('Y-m-d H:i:s'),
    $enviados,
    $errores,
    count($citas)
);
error_log($log_message);

jsonResponse([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'citas_encontradas' => count($citas),
    'enviados' => $enviados,
    'errores' => $errores,
    'detalles' => $log
]);
?>