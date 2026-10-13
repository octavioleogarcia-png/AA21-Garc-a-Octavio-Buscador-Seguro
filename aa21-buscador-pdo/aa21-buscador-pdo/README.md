# AA 21 – Buscador seguro contra inyección SQL (PHP + MySQL + PDO)

**Alumno:** De Olivera Jesús · **Carrera:** Ingeniería en Sistemas de Información (3° año), UCP Posadas
**Materia:** Paradigmas y Lenguajes de Programación III · **Docente:** Sicardi Dante

Buscador de productos con frontend en HTML/CSS/JavaScript (fetch) y backend en PHP con PDO. Incluye el endpoint **vulnerable** del profesor (para analizar el ataque) y un endpoint **seguro** con consultas preparadas, además de un alta de productos también segura.

## Estructura

```
aa21-buscador-pdo/
├── index.html                    Interfaz (búsqueda + alta de productos)
├── css/styles.css                Estilos
├── js/app.js                     fetch al backend; render con textContent
├── config/conexion.php           Conexión PDO (XAMPP: root, sin contraseña)
├── api/
│   ├── buscador-vulnerable.php   VULNERABLE (del profesor, sin modificar)
│   ├── buscar.php                SEGURO: SELECT con consulta preparada
│   └── crear.php                 SEGURO: INSERT con consulta preparada
└── sql/productos.sql             Tabla productos + datos de ejemplo
```

## Cómo ejecutarlo en XAMPP

1. Iniciar **Apache** y **MySQL** en el panel de XAMPP.
2. En phpMyAdmin, base `clase_backend`, ejecutar `sql/productos.sql` (si la tabla ya existe, no hace falta).
3. Copiar la carpeta `aa21-buscador-pdo` dentro de `htdocs`.
4. Abrir:
   - Modo seguro: <http://localhost/aa21-buscador-pdo/>
   - Modo vulnerable (solo para la demostración): <http://localhost/aa21-buscador-pdo/?modo=vulnerable>

Si tu MySQL usa otra contraseña o base, se cambia en `config/conexion.php`.

## Paso 3 – Análisis del buscador vulnerable

`buscador-vulnerable.php` arma la consulta concatenando la entrada del usuario:

```php
$sql = "SELECT * FROM productos WHERE nombre LIKE '%" . $q . "%'";
$resultado = $pdo->query($sql);
```

El motor recibe **una sola cadena** y la analiza completa, de modo que lo que escribe el usuario puede alterar la estructura de la consulta.

| Entrada en el buscador | SQL que recibe MySQL | Resultado esperado en el modo vulnerable |
|---|---|---|
| `mouse` | `... LIKE '%mouse%'` | 1 producto (comportamiento normal) |
| `' OR '1'='1` | `... LIKE '%' OR '1'='1%'` | Devuelve **todo** el catálogo |
| `%' OR 1=1 -- ` | `... LIKE '%%' OR 1=1 -- %'` | Devuelve **todo** el catálogo |
| `' UNION SELECT 1,2,3,4,5 -- ` | `... UNION SELECT 1,2,3,4,5 -- %'` | Agrega una fila inventada; permite leer otras tablas |
| `'; DROP TABLE productos; -- ` | consulta apilada | `query()` en PDO/MySQL puede ejecutar varias sentencias según la configuración |
| `' OR SLEEP(3) -- ` | `... OR SLEEP(3) -- %'` | La respuesta demora (inyección ciega por tiempo) |

La comilla simple cierra el literal del `LIKE`; lo que sigue ya es código SQL. Además, el endpoint filtra información al devolver el mensaje de excepción de la base de datos.

> Los resultados de la tabla son los **esperados** según el análisis; verificarlos en tu MySQL y agregar las capturas de pantalla abajo.

## Paso 4 – Cómo lo resuelve PDO

`api/buscar.php` usa una consulta preparada, que separa dos fases:

1. **Preparación (compilación):** el motor recibe solo la estructura, con marcadores en lugar de valores:
   `SELECT ... WHERE nombre LIKE :q_nombre ESCAPE '!' OR categoria LIKE :q_categoria ESCAPE '!'`.
   Aquí se analiza y planifica la consulta; su forma queda fija.
2. **Ejecución:** recién después viajan los valores. Se tratan siempre como **datos**, nunca como código, aunque contengan comillas, `OR`, `UNION` o `--`.

Medidas adicionales:

- `PDO::ATTR_EMULATE_PREPARES => false`: fuerza consultas preparadas reales en el servidor, sin armar el SQL en PHP.
- Los comodines `%` y `_` que escriba el usuario se escapan (carácter `!`) para que se busquen como texto.
- Validación en el servidor: `q` debe ser texto y tener como máximo 100 caracteres; método HTTP correcto (405 si no).
- `display_errors` desactivado y mensajes genéricos al cliente; el detalle técnico va a `error_log`.
- En el navegador, los datos se muestran con `textContent` (evita XSS).
- `crear.php` valida tipos y rangos en el servidor y hace el `INSERT` con consulta preparada.

## Pruebas realizadas

Se corrió una batería de 10 entradas maliciosas contra ambos endpoints, más pruebas de robustez y de navegador:

| Prueba | `buscador-vulnerable.php` | `buscar.php` |
|---|---|---|
| 10 payloads de inyección | 9 devolvieron filas de más; 1 rompió la respuesta | 0 filas en todos |
| `q[]=a`, `q` de 20.000 caracteres | sin control | 400 con JSON limpio |
| Alta con SQL en el nombre | — | se guarda como texto; la tabla queda intacta |
| Alta con tipos inválidos / 101 caracteres | — | 422 con detalle por campo |
| Navegador: `' OR '1'='1` | muestra todo el catálogo | "Sin resultados." |

Estas pruebas se hicieron con un entorno de pruebas que reemplaza MySQL por SQLite; el comportamiento de las consultas preparadas es el mismo, pero conviene repetirlas en tu XAMPP.

## Capturas

_(Agregar aquí: ataque en `?modo=vulnerable`, el mismo ataque en modo seguro, y las filas en phpMyAdmin.)_

## Limitaciones

- No hay autenticación: cualquiera puede crear productos con `crear.php`. En un sistema real se agregaría login y permisos.
- `buscador-vulnerable.php` se deja a propósito sin corregir, solo para el análisis; no debe usarse en producción.
