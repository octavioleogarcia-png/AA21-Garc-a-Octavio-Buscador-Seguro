<?php
// API de productos: GET (lectura/búsqueda) y POST (escritura)
// con PDO y consultas preparadas. Ningún dato del usuario se concatena en el SQL.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php'; // define $pdo

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    // ---------- LECTURA / BÚSQUEDA ----------
    if ($metodo === 'GET') {
        $busqueda = trim($_GET['q'] ?? '');

        if ($busqueda !== '') {
            // Se escapan los comodines propios de LIKE (% _ \) para que se busquen como texto
            $patron = '%' . addcslashes($busqueda, '%_\\') . '%';

            // Con prepares reales (EMULATE_PREPARES = false) un mismo marcador no puede
            // repetirse, por eso se usan dos: :q_nombre y :q_categoria
            $stmt = $pdo->prepare(
                'SELECT id, nombre, categoria, precio, stock
                   FROM productos
                  WHERE nombre LIKE :q_nombre OR categoria LIKE :q_categoria
               ORDER BY id'
            );
            $stmt->execute([
                ':q_nombre'    => $patron,
                ':q_categoria' => $patron,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, categoria, precio, stock FROM productos ORDER BY id'
            );
            $stmt->execute();
        }

        $productos = $stmt->fetchAll();
        foreach ($productos as &$p) {          // tipos numéricos reales en el JSON
            $p['id']     = (int) $p['id'];
            $p['precio'] = (float) $p['precio'];
            $p['stock']  = (int) $p['stock'];
        }
        unset($p);

        responder(200, ['ok' => true, 'datos' => $productos]);
    }

    // ---------- ESCRITURA ----------
    if ($metodo === 'POST') {
        $entrada = json_decode(file_get_contents('php://input'), true);
        if (!is_array($entrada)) {
            responder(400, ['ok' => false, 'error' => 'El cuerpo de la petición no es un JSON válido.']);
        }

        // Validación del lado del servidor (no se confía en el cliente)
        $nombre    = trim((string) ($entrada['nombre'] ?? ''));
        $categoria = trim((string) ($entrada['categoria'] ?? ''));
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
            responder(422, ['ok' => false, 'error' => 'Datos inválidos.', 'errores' => $errores]);
        }

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
            'ok'      => true,
            'mensaje' => 'Producto creado correctamente.',
            'id'      => (int) $pdo->lastInsertId(),
        ]);
    }

    // Cualquier otro método
    header('Allow: GET, POST');
    responder(405, ['ok' => false, 'error' => 'Método no permitido.']);

} catch (PDOException $e) {
    error_log('Error en productos.php: ' . $e->getMessage());
    responder(500, ['ok' => false, 'error' => 'Error interno del servidor.']);
}
