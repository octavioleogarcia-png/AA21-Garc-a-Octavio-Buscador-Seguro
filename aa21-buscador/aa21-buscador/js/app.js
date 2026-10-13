// Frontend en JavaScript Vanilla: consume la API PHP con fetch + async/await.
const API = 'api/productos.php';

const cuerpoTabla = document.getElementById('cuerpo-tabla');
const formulario = document.getElementById('formulario-producto');
const buscador = document.getElementById('buscador');
const mensaje = document.getElementById('mensaje');

const formatoPeso = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
});

function mostrarMensaje(texto, tipo) {
    mensaje.textContent = texto;
    mensaje.className = tipo; // "ok" o "fallo"
}

function limpiarErrores() {
    formulario.querySelectorAll('.error').forEach((s) => (s.textContent = ''));
}

function marcarErrores(errores) {
    Object.entries(errores).forEach(([campo, texto]) => {
        const span = formulario.querySelector(`[data-error="${campo}"]`);
        if (span) span.textContent = texto;
    });
}

// Se usa textContent (no innerHTML): ningún dato de la base puede inyectar HTML/JS (XSS)
function crearCelda(texto, clase) {
    const td = document.createElement('td');
    td.textContent = texto;
    if (clase) td.className = clase;
    return td;
}

function renderizarTabla(productos) {
    cuerpoTabla.replaceChildren();

    if (productos.length === 0) {
        const fila = document.createElement('tr');
        const celda = crearCelda('No se encontraron productos.', 'vacio');
        celda.colSpan = 5;
        fila.appendChild(celda);
        cuerpoTabla.appendChild(fila);
        return;
    }

    productos.forEach((p) => {
        const fila = document.createElement('tr');
        fila.append(
            crearCelda(p.id),
            crearCelda(p.nombre),
            crearCelda(p.categoria),
            crearCelda(formatoPeso.format(p.precio), 'num'),
            crearCelda(p.stock, p.stock <= 5 ? 'num stock-bajo' : 'num')
        );
        cuerpoTabla.appendChild(fila);
    });
}

// ---------- LECTURA / BÚSQUEDA (GET) ----------
let ultimaBusqueda = 0; // evita que una respuesta lenta pise a una más reciente

async function cargarProductos(termino = '') {
    const numero = ++ultimaBusqueda;
    try {
        const url = termino ? `${API}?q=${encodeURIComponent(termino)}` : API;
        const respuesta = await fetch(url);
        const resultado = await respuesta.json();

        if (numero !== ultimaBusqueda) return; // llegó una búsqueda más nueva
        if (!respuesta.ok || !resultado.ok) {
            throw new Error(resultado.error || 'No se pudo cargar el listado.');
        }
        renderizarTabla(resultado.datos);
    } catch (error) {
        mostrarMensaje(error.message, 'fallo');
    }
}

// ---------- ESCRITURA (POST) ----------
async function guardarProducto(evento) {
    evento.preventDefault();
    limpiarErrores();
    mensaje.className = '';

    // Validación en el cliente: solo mejora la experiencia; el servidor vuelve a validar
    const datos = {
        nombre: formulario.nombre.value.trim(),
        categoria: formulario.categoria.value.trim(),
        precio: formulario.precio.value,
        stock: formulario.stock.value,
    };

    const errores = {};
    if (!datos.nombre) errores.nombre = 'Ingresá el nombre.';
    if (!datos.categoria) errores.categoria = 'Ingresá la categoría.';
    if (datos.precio === '' || Number(datos.precio) < 0) errores.precio = 'Ingresá un precio válido.';
    if (datos.stock === '' || Number(datos.stock) < 0) errores.stock = 'Ingresá un stock válido.';

    if (Object.keys(errores).length > 0) {
        marcarErrores(errores);
        return;
    }

    try {
        const respuesta = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos),
        });
        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.ok) {
            if (resultado.errores) marcarErrores(resultado.errores);
            throw new Error(resultado.error || 'No se pudo guardar el producto.');
        }

        mostrarMensaje(resultado.mensaje, 'ok');
        formulario.reset();
        formulario.stock.value = 0;
        buscador.value = '';
        cargarProductos();
    } catch (error) {
        mostrarMensaje(error.message, 'fallo');
    }
}

// ---------- Eventos (todo desacoplado del HTML) ----------
let temporizador;

document.addEventListener('DOMContentLoaded', () => cargarProductos());
formulario.addEventListener('submit', guardarProducto);

// Pequeña espera (debounce) para no consultar al servidor en cada tecla
buscador.addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => cargarProductos(buscador.value.trim()), 250);
});
