<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Configuración de la BD bluehost
/* define('DB_HOST', 'localhost');
define('DB_NAME', 'acciont1_sistema_citas');
define('DB_USER', 'acciont1_agenda');
define('DB_PASS', '25.AgendaAT'); */
// Configuracion de la BD local
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_citas');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuración de correo
define('SMTP_HOST', 'mail.acciontic.com.mx');
define('SMTP_PORT', 465);
define('SMTP_USER', 'ventas1@acciontic.com.mx');
define('SMTP_PASS', '4982$Adpi_');
define('EMAIL_FROM', 'ventas1@acciontic.com.mx');
define('EMAIL_FROM_NAME', 'Tu Empresa');
define('SMTP_SECURE','ssl');

// Conexión a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión a la base de datos']);
        exit;
    }
}

// Headers CORS
function setCorsHeaders() {
    // Remover cualquier header previo
    header_remove('Access-Control-Allow-Origin');
    header_remove('Access-Control-Allow-Methods');
    header_remove('Access-Control-Allow-Headers');
    
    // Establecer headers CORS
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    
    // Manejar peticiones OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Función para responder con JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    return;// json_encode($data);
}

// Enviar correo electrónico
function enviarCorreo($para, $asunto, $mensaje) {
    // Usando la función mail() básica de PHP
    // Para producción, recomiendo usar PHPMailer
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = SMTP_HOST;
    $mailer->SMTPAuth = true;
    $mailer->Username = SMTP_USER;
    $mailer->Password = SMTP_PASS;
    $mailer->SMTPSecure = SMTP_SECURE;
    $mailer->Port = SMTP_PORT;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $mensajeHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
            .content { background: #f9fafb; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$asunto</h1>
            </div>
            <div class='content'>
                $mensaje
            </div>
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        $mailer->addAddress($para);
        $mailer->Subject = $asunto;
        $mailer->isHTML(true);
        $mailer->Body = $mensajeHTML;

        $mailer->send();
        return true;

    } catch (Exception $e) {
        error_log("Error enviando correo: " . $mailer->ErrorInfo);
        $error = $mailer->ErrorInfo;
        throw new Exception("No se pudo enviar el correo: " . $mailer->ErrorInfo);
    }
    
    /* // Para desarrollo: solo registrar en log
    error_log("Correo a $para: $asunto");
    
    // Para producción: descomentar la siguiente línea
    // return mail($para, $asunto, $mensajeHTML, $headers);
    
    return true; */
}
?>