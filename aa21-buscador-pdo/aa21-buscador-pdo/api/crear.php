<?php
// ✅ ESCRITURA SEGURA: INSERT con consulta preparada y validación en el servidor.
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/conexion.php'; // define $pdo

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ["estado" => "error", "mensaje" => "Método no permitido"]);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    responder(400, ["estado" => "error", "mensaje" => "El cuerpo de la petición no es un JSON válido."]);
}

// ---------- Validación del lado del servidor (no se confía en el cliente) ----------
// Solo se aceptan textos: un arreglo u objeto se trata como dato faltante.
$nombre    = is_string($entrada['nombre'] ?? null) ? trim($entrada['nombre']) : '';
$categoria = is_string($entrada['categoria'] ?? null) ? trim($entrada['categoria']) : '';
$precio    = $entrada['precio'] ?? null;
$stock     = $entrada['stock'] ?? 0;

$errores = [];

if ($nombre === '' || mb_strlen($nombre) > 100) {
    $errores['nombre'] = 'El nombre es obligatorio (máximo 100 caracteres).';
}
if ($categoria === '' || mb_strlen($categoria) > 50) {
    $errores['categoria'] = 'La categoría es obligatoria (máximo 50 caracteres).';
}
if (!is_numeric($precio) || $precio < 0 || $precio > 99999999.99) {
    $errores['precio'] = 'El precio debe ser un número mayor o igual a 0.';
}
if (filter_var($stock, FILTER_VALIDATE_INT) === false || (int) $stock < 0) {
    $errores['stock'] = 'El stock debe ser un entero mayor o igual a 0.';
}

if ($errores) {
    responder(422, ["estado" => "error", "mensaje" => "Datos inválidos.", "errores" => $errores]);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO productos (nombre, categoria, precio, stock)
         VALUES (:nombre, :categoria, :precio, :stock)'
    );
    $stmt->execute([
        ':nombre'    => $nombre,
        ':categoria' => $categoria,
        ':precio'    => round((float) $precio, 2),
        ':stock'     => (int) $stock,
    ]);

    responder(201, [
        "estado"  => "exito",
        "mensaje" => "Producto creado correctamente.",
        "id"      => (int) $pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    error_log('Error en crear.php: ' . $e->getMessage());
    responder(500, ["estado" => "error", "mensaje" => "Error interno del servidor."]);
}
