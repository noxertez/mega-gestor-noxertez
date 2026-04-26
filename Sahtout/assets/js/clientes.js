/* Clientes JS - AJAX and Modal Logic */

let currentClientId = null;

function nuevoCliente() {
    currentClientId = null;
    document.getElementById('modalTitle').innerText = 'Nuevo Cliente';
    document.getElementById('clientForm').reset();
    document.getElementById('clientId').value = '';
    
    // Reset tabs
    switchTab('tab-perfil');
    // Hide specialized tabs for new client
    document.getElementById('btn-tab-asignacion').style.display = 'none';
    document.getElementById('btn-tab-historial').style.display = 'none';
    
    openModal();
}

function verCliente(id) {
    currentClientId = id;
    document.getElementById('modalTitle').innerText = 'Ficha de Cliente #' + id;
    
    // Show specialized tabs
    document.getElementById('btn-tab-asignacion').style.display = 'block';
    document.getElementById('btn-tab-historial').style.display = 'block';
    
    fetch(`api/clientes.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data) {
                document.getElementById('clientId').value = data.id;
                document.getElementById('nombre').value = data.nombre || '';
                document.getElementById('telefono').value = data.telefono || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('instagram').value = data.instagram || '';
                document.getElementById('direccion').value = data.direccion || '';
                document.getElementById('ciudad').value = data.ciudad || '';
                document.getElementById('codigo_postal').value = data.codigo_postal || '';
                document.getElementById('pais').value = data.pais || '';
                document.getElementById('notas').value = data.notas || '';
                
                switchTab('tab-perfil');
                openModal();
                
                // Load history and clear assignments search
                cargarHistorial(id);
                document.getElementById('searchProductInput').value = '';
                document.getElementById('productSearchResults').innerHTML = '';
            }
        });
}

function guardarCliente() {
    const formData = new FormData(document.getElementById('clientForm'));
    formData.append('action', 'save');
    
    fetch('api/clientes.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal();
            location.reload(); // Simple reload to refresh table
        } else {
            alert('Error al guardar: ' + (data.error || 'Desconocido'));
        }
    });
}

function eliminarCliente(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este cliente?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        fetch('api/clientes.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar');
            }
        });
    }
}

// Modal Functions
function openModal() {
    const modal = document.getElementById('clientModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.style.opacity = '1', 10);
}

function closeModal() {
    const modal = document.getElementById('clientModal');
    modal.style.opacity = '0';
    setTimeout(() => modal.style.display = 'none', 300);
}

function switchTab(tabId) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    // Show target content
    document.getElementById(tabId).classList.add('active');
    
    // Update button states
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabId}')"]`).classList.add('active');
}

// Assignments
function buscarProductos() {
    const query = document.getElementById('searchProductInput').value;
    if (query.length < 2) return;
    
    fetch(`api/clientes.php?action=search_products&q=${query}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('productSearchResults');
            container.innerHTML = '';
            
            data.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.SKU_REF}</td>
                    <td>${p.NOMBRE}</td>
                    <td>${p.PRECIO}€</td>
                    <td>${p.STOCK_FISICO}</td>
                    <td>
                        <button onclick="prepararAsignacion('${p.SKU_REF}', '${p.NOMBRE.replace(/'/g, "\\'")}')" class="btn-premium" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: var(--accent-green);">Asignar</button>
                    </td>
                `;
                container.appendChild(tr);
            });
        });
}

function prepararAsignacion(sku, nombre) {
    document.getElementById('selectedProductSku').value = sku;
    document.getElementById('selectedProductName').innerText = sku + ' - ' + nombre;
    document.getElementById('assignmentAction').style.display = 'block';
}

function crearPedido() {
    const sku = document.getElementById('selectedProductSku').value;
    const tipo = document.getElementById('tipoTrabajo').value;
    
    if (!sku) return alert('Selecciona un producto primero');
    
    const formData = new FormData();
    formData.append('action', 'create_order');
    formData.append('id_cliente', currentClientId);
    formData.append('sku', sku);
    formData.append('tipo_trabajo', tipo);
    
    fetch('api/clientes.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Pedido creado correctamente');
            switchTab('tab-historial');
            cargarHistorial(currentClientId);
            document.getElementById('assignmentAction').style.display = 'none';
        } else {
            alert('Error al crear pedido');
        }
    });
}

// History
function cargarHistorial(id) {
    fetch(`api/clientes.php?action=get_history&id=${id}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('orderHistoryList');
            container.innerHTML = '';
            
            data.forEach(o => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${o.id}</td>
                    <td>${o.fecha_pedido}</td>
                    <td><span class="status-badge status-${o.estado.toLowerCase().replace(/ /g, '-')}">${o.estado}</span></td>
                    <td>${o.sku_articulo || '--'}</td>
                    <td>${o.total || '0.00'}€</td>
                `;
                container.appendChild(tr);
            });
        });
}

// Filter table
function filtrarClientes() {
    const texto = document.getElementById('buscador').value.toLowerCase();
    const filas = document.querySelectorAll('#tablaClientes tbody tr');
    filas.forEach(f => {
        f.style.display = f.textContent.toLowerCase().includes(texto) ? '' : 'none';
    });
}
