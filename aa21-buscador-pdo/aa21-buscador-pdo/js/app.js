document.addEventListener('DOMContentLoaded', () => {
    const formBuscar  = document.querySelector('#form-buscar');
    const inputBuscar = document.querySelector('#input-buscar');
    const contenedor  = document.querySelector('#resultados');
    const formAlta    = document.querySelector('#form-alta');
    const mensaje     = document.querySelector('#mensaje');
    const indicador   = document.querySelector('#modo');

    // Por defecto se usa el endpoint SEGURO (consulta preparada con PDO).
    // Abriendo index.html?modo=vulnerable se usa el endpoint vulnerable del
    // profesor, solo para analizar el ataque de inyección SQL.
    const modoVulnerable = new URLSearchParams(window.location.search).get('modo') === 'vulnerable';
    const API_BUSCAR = modoVulnerable ? 'api/buscador-vulnerable.php' : 'api/buscar.php';
    const API_CREAR  = 'api/crear.php';

    indicador.textContent = modoVulnerable
        ? 'Modo: VULNERABLE (concatenación de strings, solo para la demostración)'
        : 'Modo: seguro (consultas preparadas con PDO)';
    indicador.classList.toggle('vulnerable', modoVulnerable);

    // ---------- LECTURA: búsqueda ----------
    async function buscar(termino) {
        contenedor.textContent = "Buscando en la base de datos...";

        try {
            // Petición GET con parámetro Query String en la URL
            const res = await fetch(`${API_BUSCAR}?q=${encodeURIComponent(termino)}`);
            const resultado = await res.json();

            if (res.ok && resultado['estado'] === 'exito') {
                renderizarProductos(resultado['data']);
            } else {
                contenedor.textContent = resultado['mensaje'] || "No se encontraron productos.";
            }
        } catch (error) {
            contenedor.textContent = "Error de conexión con el servidor.";
        }
    }

    formBuscar.addEventListener('submit', (e) => {
        e.preventDefault(); // Previene la recarga del navegador
        buscar(inputBuscar.value.trim());
    });

    function formatearPrecio(valor) {
        const numero = Number(valor);
        return Number.isFinite(numero)
            ? numero.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })
            : String(valor);
    }

    function renderizarProductos(productos) {
        contenedor.innerHTML = ''; // Limpiamos el contenedor

        if (productos.length === 0) {
            contenedor.textContent = "Sin resultados.";
            return;
        }

        const fragmento = document.createDocumentFragment();
        productos.forEach(prod => {
            const card = document.createElement('div');
            card.className = 'card-producto';

            // Renderizado seguro en el DOM con textContent (evita XSS)
            const titulo = document.createElement('h4');
            titulo.textContent = prod['nombre'];

            const precio = document.createElement('p');
            precio.textContent = formatearPrecio(prod['precio']);

            const detalle = document.createElement('p');
            detalle.className = 'detalle';
            detalle.textContent = `${prod['categoria']} · Stock: ${prod['stock']}`;

            card.appendChild(titulo);
            card.appendChild(precio);
            card.appendChild(detalle);
            fragmento.appendChild(card);
        });

        contenedor.appendChild(fragmento);
    }

    // ---------- ESCRITURA: alta de producto ----------
    function limpiarErrores() {
        formAlta.querySelectorAll('.error').forEach(s => (s.textContent = ''));
    }

    function marcarErrores(errores) {
        Object.entries(errores).forEach(([campo, texto]) => {
            const span = formAlta.querySelector(`[data-error="${campo}"]`);
            if (span) span.textContent = texto;
        });
    }

    function mostrarMensaje(texto, tipo) {
        mensaje.textContent = texto;
        mensaje.className = tipo; // "ok" o "fallo"
    }

    formAlta.addEventListener('submit', async (e) => {
        e.preventDefault();
        limpiarErrores();
        mostrarMensaje('', '');

        const datos = {
            nombre:    formAlta.nombre.value.trim(),
            categoria: formAlta.categoria.value.trim(),
            precio:    formAlta.precio.value,
            stock:     formAlta.stock.value,
        };

        // Validación en el cliente: solo mejora la experiencia; el servidor vuelve a validar
        const errores = {};
        if (!datos.nombre)    errores.nombre    = 'Ingresá el nombre.';
        if (!datos.categoria) errores.categoria = 'Ingresá la categoría.';
        if (datos.precio === '' || Number(datos.precio) < 0) errores.precio = 'Ingresá un precio válido.';
        if (datos.stock === '' || Number(datos.stock) < 0)   errores.stock  = 'Ingresá un stock válido.';

        if (Object.keys(errores).length > 0) {
            marcarErrores(errores);
            return;
        }

        try {
            const res = await fetch(API_CREAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos),
            });
            const resultado = await res.json();

            if (!res.ok || resultado['estado'] !== 'exito') {
                if (resultado['errores']) marcarErrores(resultado['errores']);
                throw new Error(resultado['mensaje'] || 'No se pudo guardar el producto.');
            }

            mostrarMensaje(resultado['mensaje'], 'ok');
            formAlta.reset();
            inputBuscar.value = '';
            buscar('');
        } catch (error) {
            mostrarMensaje(error.message, 'fallo');
        }
    });

    // Al abrir la página se muestra el catálogo completo
    buscar('');
});
