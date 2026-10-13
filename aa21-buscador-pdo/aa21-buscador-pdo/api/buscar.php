<?php
// ✅ BUSCADOR SEGURO (lectura): consulta preparada con PDO.
// Mismo formato de respuesta que buscador-vulnerable.php: estado / total / data.
header('Content-Type: application/json; charset=utf-8');

// Los errores de PHP no se muestran al cliente (revelan rutas y rompen el JSON);
// quedan registrados en el log de Apache/PHP.
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/conexion.php'; // define $pdo

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    responder(405, ["estado" => "error", "mensaje" => "Método no permitido"]);
}

// ---------- Validación de la entrada ----------
$q = $_GET['q'] ?? '';
if (!is_string($q)) {                       // ej.: ?q[]=a llega como arreglo
    responder(400, ["estado" => "error", "mensaje" => "El parámetro q debe ser texto."]);
}
$q = trim($q);
if (mb_strlen($q) > 100) {
    responder(400, ["estado" => "error", "mensaje" => "La búsqueda no puede superar los 100 caracteres."]);
}

try {
    // Los comodines propios de LIKE (% y _) que escriba el usuario se buscan como texto.
    // Se usa '!' como carácter de escape: funciona igual en cualquier configuración de MySQL.
    $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';

    // FASE 1 - PREPARACIÓN: el motor recibe SOLO la estructura de la consulta,
    // con marcadores (:q_nombre, :q_categoria) en lugar de los valores.
    // Con prepares reales un mismo marcador no puede repetirse, por eso hay dos.
    $stmt = $pdo->prepare(
        "SELECT id, nombre, categoria, precio, stock
           FROM productos
          WHERE nombre LIKE :q_nombre ESCAPE '!'
             OR categoria LIKE :q_categoria ESCAPE '!'
       ORDER BY id"
    );

    // FASE 2 - EJECUCIÓN: recién ahora viajan los valores, ya como DATOS.
    // Aunque contengan comillas o SQL, el motor jamás los interpreta como código.
    $stmt->execute([
        ':q_nombre'    => $patron,
        ':q_categoria' => $patron,
    ]);

    $resultado = $stmt->fetchAll();
    foreach ($resultado as &$p) {           // tipos numéricos reales en el JSON
        $p['id']     = (int) $p['id'];
        $p['precio'] = (float) $p['precio'];
        $p['stock']  = (int) $p['stock'];
    }
    unset($p);

    responder(200, [
        "estado" => "exito",
        "total"  => count($resultado),
        "data"   => $resultado,
    ]);
} catch (PDOException $e) {
    error_log('Error en buscar.php: ' . $e->getMessage());
    responder(500, ["estado" => "error", "mensaje" => "Error interno del servidor."]);
}
