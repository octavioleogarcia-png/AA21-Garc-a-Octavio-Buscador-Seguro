<?php
// Conexión PDO a MySQL (XAMPP).
// Ajustá estos 4 valores si tu base o tu usuario son distintos.
$db      = 'clase_backend';   // base que ya creaste en phpMyAdmin
$user    = 'root';
$pass    = '';                // XAMPP por defecto no tiene contraseña
$host    = 'localhost';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opciones de configuración del driver PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lanza PDOException ante errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // devuelve arreglos asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // fuerza prepared statements reales
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // El detalle técnico queda en el log del servidor; al cliente solo un mensaje genérico
    error_log('Error de conexión: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No se pudo conectar a la base de datos.']);
    exit;
}
