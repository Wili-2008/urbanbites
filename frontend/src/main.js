import { api } from './utils/api.js';
import { getSession, saveSession, clearSession } from './utils/session.js';

const root = document.querySelector('#app');

const readCart = () => {
    try {
        const cart = JSON.parse(localStorage.getItem('urban-bites.cart') || '[]');
        return Array.isArray(cart) ? cart.filter((item) => item && Number(item.id_producto) > 0
            && Number(item.cantidad) > 0 && Number.isFinite(Number(item.precio))) : [];
    } catch {
        localStorage.removeItem('urban-bites.cart');
        return [];
    }
};

const formatDateInput = (date) => {
    const d = new Date(date);
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
};

const now = new Date();
const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);

const state = {
    user: getSession()?.user || null,
    products: [],
    categories: [],
    orders: [],
    users: [],
    roles: [],
    sales: [],
    report: null,
    auditLogs: [],
    inventoryMovements: [],
    config: { nombre_negocio: 'Urban Bites', costo_envio: 10.00 },
    cart: readCart(),
    posCart: [],
    posQuery: '',
    posCategory: 'Todos',
    posCustomer: {
        cliente_nombre: 'Consumidor Final',
        cliente_documento: '',
        metodo_pago: 'efectivo',
        monto_recibido: '',
        descuento: 0,
        observaciones: '',
    },
    lastSaleReceipt: null,
    reportDates: {
        inicio: formatDateInput(firstDayOfMonth),
        fin: formatDateInput(now),
    },
    auditFilters: {
        accion: '',
        entidad: '',
    },
    adjustingProductId: null,
    view: 'menu',
    category: 'Todos',
    query: '',
    notice: '',
    authMode: 'login',
    adminTab: 'reports',
    setupRequired: false,
    editingProductId: null,
    editingCategoryId: null,
};

const escapeHtml = (v = '') => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = (v) => `Bs ${Number(v || 0).toFixed(2)}`;
const placeholderImage = new URL('./assets/food-placeholder.svg', import.meta.url).href;

const imageFilesByCategory = {
    Bebidas: 'limonadar.jpg',
    Hamburguesas: 'hamburguesa.jpg',
    Pizzas: 'pizza.jpg',
    'Pollo frito': 'pollo.jpg',
    Salchipapas: 'salchipapai.jpg',
};

const imageFor = (path, category) => {
    if (typeof path === 'string' && path.trim()) {
        const imagePath = path.trim();
        if (/^https?:\/\//i.test(imagePath)) return imagePath;
        if (imagePath.startsWith('/')) return new URL(imagePath, window.location.origin).href;
        return new URL(`../${imagePath.replace(/^(\.\.\/)+/, '')}`, import.meta.url).href;
    }
    const imageFile = imageFilesByCategory[category];
    return imageFile ? new URL(`../${imageFile}`, import.meta.url).href : placeholderImage;
};

const subtotal = () => state.cart.reduce((sum, item) => sum + item.precio * item.cantidad, 0);
const cartCount = () => state.cart.reduce((sum, item) => sum + item.cantidad, 0);

const posSubtotal = () => state.posCart.reduce((sum, item) => sum + item.precio * item.cantidad, 0);
const posTotal = () => Math.max(0, posSubtotal() - Number(state.posCustomer.descuento || 0));

const isAdmin = () => state.user?.rol === 'administrador';
const isSeller = () => state.user?.rol === 'vendedor';
const isStaff = () => isAdmin() || isSeller();

function showNotice(message) {
    state.notice = message;
    render();
    window.clearTimeout(state._noticeTimer);
    state._noticeTimer = window.setTimeout(() => {
        if (state.notice === message) {
            state.notice = '';
            render();
        }
    }, 4500);
}

// -------------------------------------------------------------
// CARGAS ASÍNCRONAS CON FETCH / AJAX
// -------------------------------------------------------------
async function loadStore() {
    try {
        const [products, categories, config] = await Promise.all([
            api.get('listarproductos'),
            api.get('listarcategorias'),
            api.get('obtener_configuracion'),
        ]);
        state.products = Array.isArray(products) ? products : [];
        state.categories = Array.isArray(categories) ? categories : [];
        state.config = config || state.config;
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadAdminProducts() {
    try {
        const products = await api.get('listarproductos?incluir_no_disponibles=1');
        state.products = Array.isArray(products) ? products : [];
        state.categories = await api.get('listarcategorias?incluir_inactivas=1');
        state.config = await api.get('obtener_configuracion');
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadOrders() {
    try {
        const allOrders = await api.get('listarpedidos');
        const visible = isAdmin() ? allOrders : allOrders.filter((o) => Number(o.id_usuario) === Number(state.user.id_usuario));
        state.orders = await Promise.all(visible.map(async (order) => ({
            ...order,
            items: await api.get(`listardetallepedidos?id_pedido=${encodeURIComponent(order.id_pedido)}`),
        })));
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadSales() {
    try {
        const sales = await api.get('listar_ventas');
        state.sales = Array.isArray(sales) ? sales : [];
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadReports() {
    try {
        const report = await api.get(`reportes/ventas?fecha_inicio=${encodeURIComponent(state.reportDates.inicio)}&fecha_fin=${encodeURIComponent(state.reportDates.fin)}`);
        state.report = report;
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadAudit() {
    try {
        let endpoint = 'auditoria/listar?limite=100';
        if (state.auditFilters.accion) endpoint += `&accion=${encodeURIComponent(state.auditFilters.accion)}`;
        if (state.auditFilters.entidad) endpoint += `&entidad=${encodeURIComponent(state.auditFilters.entidad)}`;
        state.auditLogs = await api.get(endpoint);
        state.inventoryMovements = await api.get('movimientos/listar?limite=60');
    } catch (error) {
        showNotice(error.message);
    }
}

async function loadUsers() {
    try {
        state.users = await api.get('listarusuarios');
        state.roles = await api.get('listarroles').catch(() => []);
    } catch (error) {
        showNotice(error.message);
    }
}

// -------------------------------------------------------------
// VISTAS Y COMPONENTES
// -------------------------------------------------------------

function header() {
    const roleBadge = state.user
        ? `<span class="text-xs uppercase font-bold px-2 py-1 rounded bg-amber-100 text-amber-700 ml-1">${escapeHtml(state.user.rol)}</span>`
        : '';

    return `<header class="topbar">
        <a class="brand" href="#" data-view="menu">
            <span class="brand-mark">UB</span>
            <span>URBAN <b>BITES</b><small>INVENTARIO · VENTAS · COMIDA RÁPIDA</small></span>
        </a>
        <nav class="flex items-center gap-2">
            <button data-view="menu">Menú</button>
            ${isStaff() ? `<button data-view="pos" class="font-bold text-orange-600">Punto de Venta (POS)</button>` : ''}
            ${isStaff() ? `<button data-view="sales">Ventas</button>` : ''}
            ${isAdmin() ? `<button data-view="admin">Administración</button>` : ''}
            ${state.user?.rol === 'cliente' ? `<button data-view="orders">Mis pedidos</button>` : ''}
            ${state.user ? `
                <span class="text-sm text-gray-700 hidden md:inline ml-2">👤 ${escapeHtml(state.user.nombre)} ${roleBadge}</span>
                <button data-action="logout" class="quiet ml-2">Salir</button>
            ` : `
                <button data-view="auth" class="quiet">Ingresar</button>
            `}
            ${!isStaff() ? `<button class="cart-button ml-2" data-view="cart">Carrito <b>${cartCount()}</b></button>` : ''}
        </nav>
    </header>`;
}

function menuPage() {
    const products = state.products.filter((p) => Number(p.disponible) === 1
        && (state.category === 'Todos' || p.categoria === state.category)
        && `${p.codigo || ''} ${p.nombre} ${p.descripcion || ''}`.toLowerCase().includes(state.query.toLowerCase()));

    return `<section class="hero">
        <div>
            <span class="eyebrow">RECIÉN HECHO · CALIDAD Y SABOR</span>
            <h1>Sabor urbano.<br><em>Gestión en tiempo real.</em></h1>
            <p>Plataforma integral Urban Bites con control estricto de inventario, punto de venta y pedidos en línea.</p>
            <div class="flex gap-3">
                <button class="primary" data-scroll="catalogo">Explorar catálogo <span>↓</span></button>
                ${isStaff() ? `<button class="primary bg-gray-900 hover:bg-black" data-view="pos">Abrir Punto de Venta <span>↗</span></button>` : ''}
            </div>
        </div>
        <div class="hero-art">
            <img src="${imageFor('', 'Hamburguesas')}" alt="Comida de Urban Bites">
            <span class="hero-sticker">STOCK<br>ACTIVO</span>
        </div>
    </section>
    <section class="catalog" id="catalogo">
        <div class="section-heading">
            <div>
                <span class="eyebrow">NUESTRO MENÚ</span>
                <h2>Catálogo de productos</h2>
            </div>
            <label class="search">⌕ <input data-search placeholder="Buscar por nombre o código" value="${escapeHtml(state.query)}"></label>
        </div>
        <div class="filters">
            <button data-category="Todos" class="${state.category === 'Todos' ? 'active' : ''}">Todo el menú</button>
            ${state.categories.map((c) => `<button data-category="${escapeHtml(c.nombre)}" class="${state.category === c.nombre ? 'active' : ''}">${escapeHtml(c.nombre)}</button>`).join('')}
        </div>
        <div class="product-grid">
            ${products.map((p, i) => {
                const stock = Number(p.stock ?? 0);
                const outOfStock = stock <= 0;
                return `
                <article class="product-card">
                    <div class="product-image">
                        <img src="${escapeHtml(imageFor(p.imagen, p.categoria))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt="${escapeHtml(p.nombre)}" loading="lazy">
                        <span class="product-number">${escapeHtml(p.codigo || String(i + 1).padStart(2, '0'))}</span>
                        <span class="stock-tag ${outOfStock ? 'out-stock' : stock < 10 ? 'low-stock' : 'in-stock'}">
                            ${outOfStock ? 'Agotado' : `Stock: ${stock}`}
                        </span>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">
                            <span>${escapeHtml(p.categoria || 'Favoritos')}</span>
                            <strong>${money(p.precio)}</strong>
                        </div>
                        <h3>${escapeHtml(p.nombre)}</h3>
                        <p>${escapeHtml(p.descripcion || 'Preparado al momento con ingredientes frescos.')}</p>
                        ${outOfStock ? `
                            <button class="add-button text-gray-400 cursor-not-allowed" disabled>Sin existencias</button>
                        ` : `
                            <button class="add-button" data-add="${p.id_producto}">Añadir al pedido <b>＋</b></button>
                        `}
                    </div>
                </article>`;
            }).join('') || '<div class="empty">No encontramos productos en esta categoría.</div>'}
        </div>
    </section>`;
}

// -------------------------------------------------------------
// PUNTO DE VENTA (POS) - Vendedor y Administrador
// -------------------------------------------------------------
function posPage() {
    const products = state.products.filter((p) => Number(p.disponible) === 1
        && (state.posCategory === 'Todos' || p.categoria === state.posCategory)
        && `${p.codigo || ''} ${p.nombre} ${p.descripcion || ''}`.toLowerCase().includes(state.posQuery.toLowerCase()));

    const total = posTotal();
    const recibido = Number(state.posCustomer.monto_recibido || 0);
    const cambio = Math.max(0, recibido - total);
    const isEfectivo = state.posCustomer.metodo_pago === 'efectivo';

    return `<section class="page-wrap pos-container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">TERMINAL DE CAJA</span>
                <h1>Punto de Venta (POS)</h1>
                <p class="text-sm text-gray-600">Cajero activo: <b>${escapeHtml(state.user?.nombre || '')}</b> (${escapeHtml(state.user?.rol || '')})</p>
            </div>
            <div class="flex gap-2">
                <button class="link-button" data-view="sales">📋 Ver historial de ventas</button>
            </div>
        </div>

        <div class="pos-layout">
            <!-- PANEL IZQUIERDO: BUSCADOR Y CATÁLOGO DE PRODUCTOS -->
            <div class="pos-catalog-panel">
                <div class="pos-search-bar">
                    <span class="text-lg">🔍</span>
                    <input type="text" id="posSearchInput" placeholder="Buscar por código (ej. HAM-001) o nombre del producto..." value="${escapeHtml(state.posQuery)}" autofocus>
                    ${state.posQuery ? '<button id="posClearSearch" class="text-xs text-gray-500">Limpiar</button>' : ''}
                </div>

                <div class="filters">
                    <button data-pos-cat="Todos" class="${state.posCategory === 'Todos' ? 'active' : ''}">Todos</button>
                    ${state.categories.map((c) => `<button data-pos-cat="${escapeHtml(c.nombre)}" class="${state.posCategory === c.nombre ? 'active' : ''}">${escapeHtml(c.nombre)}</button>`).join('')}
                </div>

                <div class="pos-grid">
                    ${products.map((p) => {
                        const stock = Number(p.stock ?? 0);
                        const outOfStock = stock <= 0;
                        return `
                        <article class="pos-product-card ${outOfStock ? 'out-of-stock' : ''}" data-pos-add="${p.id_producto}">
                            <div class="pos-product-thumb">
                                <img src="${escapeHtml(imageFor(p.imagen, p.categoria))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt="${escapeHtml(p.nombre)}">
                                <span class="pos-product-badge">${escapeHtml(p.categoria || '')}</span>
                                <span class="stock-tag ${outOfStock ? 'out-stock' : stock < 10 ? 'low-stock' : 'in-stock'}">
                                    ${outOfStock ? 'Agotado' : `Stock: ${stock}`}
                                </span>
                            </div>
                            <div class="pos-product-body">
                                <span class="pos-product-code">${escapeHtml(p.codigo || '')}</span>
                                <h4 class="pos-product-title">${escapeHtml(p.nombre)}</h4>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="pos-product-price">${money(p.precio)}</span>
                                    <button class="px-2 py-1 text-xs bg-orange-600 text-white rounded font-bold hover:bg-orange-700" ${outOfStock ? 'disabled' : ''}>
                                        ${outOfStock ? 'Sin stock' : '＋ Agregar'}
                                    </button>
                                </div>
                            </div>
                        </article>`;
                    }).join('') || '<div class="empty">No hay productos que coincidan con la búsqueda.</div>'}
                </div>
            </div>

            <!-- PANEL DERECHO: TICKET DE VENTA Y COBRO -->
            <div class="pos-cart-panel shadow-md">
                <div class="pos-cart-header">
                    <h3 class="font-bold text-lg">Ticket de Venta</h3>
                    <span class="text-xs text-gray-500">${state.posCart.length} producto(s)</span>
                </div>

                <!-- LISTA DE ARTÍCULOS EN EL CARRITO DEL POS -->
                <div class="pos-cart-items">
                    ${state.posCart.length ? state.posCart.map((item, idx) => `
                        <div class="pos-cart-item">
                            <div>
                                <b class="block text-sm leading-tight">${escapeHtml(item.nombre)}</b>
                                <small class="text-gray-500">${escapeHtml(item.codigo)} · ${money(item.precio)} c/u</small>
                            </div>
                            <div class="pos-item-controls">
                                <button data-pos-qty="${idx}" data-delta="-1">−</button>
                                <input type="number" min="1" max="${item.stock_disponible}" value="${item.cantidad}" data-pos-input-qty="${idx}">
                                <button data-pos-qty="${idx}" data-delta="1" ${item.cantidad >= item.stock_disponible ? 'disabled' : ''}>＋</button>
                            </div>
                            <strong class="text-sm font-bold text-gray-800">${money(item.precio * item.cantidad)}</strong>
                            <button data-pos-remove="${idx}" class="text-red-600 text-xs font-bold hover:text-red-800" title="Quitar">✕</button>
                        </div>
                    `).join('') : '<div class="text-center py-8 text-gray-400 text-sm">Escanea un código o haz clic en un producto para agregarlo a la venta.</div>'}
                </div>

                <!-- DATOS DEL CLIENTE Y MÉTODO DE PAGO -->
                <form id="posCheckoutForm" class="pos-pay-section">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-xs font-semibold text-gray-700">
                            Cliente
                            <input name="cliente_nombre" value="${escapeHtml(state.posCustomer.cliente_nombre)}" placeholder="Consumidor Final" class="w-full mt-1 p-2 border text-xs rounded" required>
                        </label>
                        <label class="block text-xs font-semibold text-gray-700">
                            NIT / CI (Opcional)
                            <input name="cliente_documento" value="${escapeHtml(state.posCustomer.cliente_documento)}" placeholder="NIT / Carnet" class="w-full mt-1 p-2 border text-xs rounded">
                        </label>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-700 mb-1">Método de Pago</span>
                        <div class="payment-method-pills">
                            <button type="button" class="payment-pill ${state.posCustomer.metodo_pago === 'efectivo' ? 'active' : ''}" data-pay-method="efectivo">💵 Efectivo</button>
                            <button type="button" class="payment-pill ${state.posCustomer.metodo_pago === 'qr' ? 'active' : ''}" data-pay-method="qr">📱 Pago QR</button>
                            <button type="button" class="payment-pill ${state.posCustomer.metodo_pago === 'tarjeta' ? 'active' : ''}" data-pay-method="tarjeta">💳 Tarjeta</button>
                        </div>
                    </div>

                    ${isEfectivo ? `
                        <div class="grid grid-cols-2 gap-2 mt-1">
                            <label class="block text-xs font-semibold text-gray-700">
                                Monto Recibido (Bs)
                                <input type="number" step="0.50" min="0" name="monto_recibido" id="posMontoRecibido" value="${escapeHtml(state.posCustomer.monto_recibido)}" placeholder="0.00" class="w-full mt-1 p-2 border text-sm font-bold text-gray-800 rounded" required>
                            </label>
                            <div>
                                <span class="block text-xs font-semibold text-gray-700">Cambio / Vuelto</span>
                                <div class="change-display mt-1">
                                    <span>Vuelto:</span>
                                    <b id="posCambioText">${money(cambio)}</b>
                                </div>
                            </div>
                        </div>
                    ` : ''}

                    <div class="totals border-t pt-2 my-2">
                        <span class="flex justify-between text-sm text-gray-600">Subtotal: <b>${money(posSubtotal())}</b></span>
                        <strong class="flex justify-between text-xl font-extrabold text-orange-600 pt-1">Total a cobrar: <b>${money(total)}</b></strong>
                    </div>

                    <button type="submit" class="primary wide justify-center py-3 text-base font-bold rounded shadow" ${!state.posCart.length ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                        ⚡ Procesar Venta (${money(total)})
                    </button>
                    ${state.posCart.length ? `<button type="button" id="posClearCart" class="text-xs text-gray-500 hover:text-red-600 text-center py-1">Vaciar carrito actual</button>` : ''}
                </form>
            </div>
        </div>

        ${receiptModal()}
    </section>`;
}

function receiptModal() {
    if (!state.lastSaleReceipt) return '';
    const r = state.lastSaleReceipt;
    return `
    <div class="modal-overlay">
        <div class="modal-receipt">
            <div class="receipt-header">
                <h2>URBAN BITES</h2>
                <p>COMIDA RÁPIDA · BUEN SABOR</p>
                <p><b>VENTA: ${escapeHtml(r.numero_venta)}</b></p>
                <p class="text-xs text-gray-500">${escapeHtml(r.fecha_venta || '')}</p>
                <p class="text-xs text-gray-600 mt-2">Cliente: <b>${escapeHtml(r.cliente_nombre)}</b></p>
            </div>
            <div class="receipt-lines">
                ${(r.items || []).map((it) => `
                    <div class="flex justify-between text-xs">
                        <span>${it.cantidad} × ${escapeHtml(it.nombre || it.producto)}</span>
                        <b>${money(it.subtotal)}</b>
                    </div>
                `).join('')}
            </div>
            <div class="receipt-totals">
                <div><span>Subtotal:</span> <span>${money(r.subtotal)}</span></div>
                ${Number(r.descuento || 0) > 0 ? `<div><span>Descuento:</span> <span>-${money(r.descuento)}</span></div>` : ''}
                <div class="grand-total"><span>TOTAL:</span> <span>${money(r.total)}</span></div>
                <div class="text-xs text-gray-600 mt-1"><span>Método de pago:</span> <b class="uppercase">${escapeHtml(r.metodo_pago)}</b></div>
                ${r.monto_recibido ? `
                    <div class="text-xs text-gray-600"><span>Efectivo recibido:</span> <span>${money(r.monto_recibido)}</span></div>
                    <div class="text-xs font-bold text-green-700"><span>Cambio / Vuelto:</span> <span>${money(r.cambio)}</span></div>
                ` : ''}
            </div>
            <div class="receipt-actions no-print">
                <button class="primary flex-1 justify-center py-2 text-sm" onclick="window.print()">🖨️ Imprimir Ticket</button>
                <button class="px-4 py-2 border rounded text-sm hover:bg-gray-100" id="closeReceiptBtn">Nueva Venta</button>
            </div>
        </div>
    </div>`;
}

// -------------------------------------------------------------
// HISTORIAL DE VENTAS
// -------------------------------------------------------------
function salesPage() {
    return `<section class="page-wrap">
        <div class="section-heading">
            <div>
                <span class="eyebrow">HISTORIAL</span>
                <h1>${isAdmin() ? 'Registro General de Ventas' : 'Mis Ventas Realizadas'}</h1>
            </div>
            ${isStaff() ? `<button class="primary" data-view="pos">＋ Nueva Venta en Caja</button>` : ''}
        </div>
        <div class="bg-white border rounded p-4 mb-4 flex gap-4 items-center flex-wrap">
            <span class="text-sm font-bold text-gray-700">Filtrar período:</span>
            <input type="date" id="salesDateStart" class="p-2 border rounded text-xs" value="${escapeHtml(state.reportDates.inicio)}">
            <span class="text-xs text-gray-500">hasta</span>
            <input type="date" id="salesDateEnd" class="p-2 border rounded text-xs" value="${escapeHtml(state.reportDates.fin)}">
            <button id="filterSalesBtn" class="px-3 py-1 bg-gray-800 text-white text-xs rounded hover:bg-black font-semibold">Filtrar</button>
        </div>
        <div class="bg-white border rounded overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nº Venta</th>
                        <th>Fecha y Hora</th>
                        <th>Vendedor</th>
                        <th>Cliente</th>
                        <th>Método</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.sales.map((s) => `
                        <tr>
                            <td><b class="text-orange-700">${escapeHtml(s.numero_venta)}</b></td>
                            <td class="text-xs text-gray-600">${escapeHtml(s.fecha_venta)}</td>
                            <td>${escapeHtml(s.vendedor || '')}</td>
                            <td>${escapeHtml(s.cliente_nombre)}</td>
                            <td><span class="uppercase text-xs font-bold px-2 py-1 bg-gray-100 rounded">${escapeHtml(s.metodo_pago)}</span></td>
                            <td class="text-center font-bold">${s.total_items ?? '-'}</td>
                            <td class="font-extrabold text-orange-600">${money(s.total)}</td>
                            <td>
                                <button class="text-xs px-2 py-1 border rounded hover:bg-gray-100" data-view-sale="${s.id_venta}">Ver ticket</button>
                            </td>
                        </tr>
                    `).join('') || '<tr><td colspan="8" class="text-center py-6 text-gray-400">No se encontraron ventas registradas.</td></tr>'}
                </tbody>
            </table>
        </div>
        ${receiptModal()}
    </section>`;
}

// -------------------------------------------------------------
// PANEL DE ADMINISTRACIÓN COMPLETO
// -------------------------------------------------------------
function adminReportsTab() {
    const rep = state.report;
    const res = rep?.resumen || { total_ventas: 0, recaudacion_total: 0, unidades_vendidas: 0, ticket_promedio: 0 };
    return `
    <div class="printable-report">
        <div class="report-header-bar">
            <div>
                <span class="eyebrow">AUDITORÍA ECONÓMICA</span>
                <h2 class="text-xl font-extrabold mt-1">Informe de Ventas e Ingresos</h2>
                <small class="text-gray-500">Período analizado: <b>${escapeHtml(state.reportDates.inicio)}</b> al <b>${escapeHtml(state.reportDates.fin)}</b></small>
            </div>
            <form id="reportFilterForm" class="report-filter-form no-print">
                <label>Desde: <input type="date" name="fecha_inicio" value="${escapeHtml(state.reportDates.inicio)}" required></label>
                <label>Hasta: <input type="date" name="fecha_fin" value="${escapeHtml(state.reportDates.fin)}" required></label>
                <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs rounded font-bold hover:bg-black">Actualizar</button>
                <button type="button" id="printReportBtn" class="primary py-2 text-xs font-bold print-allow">🖨️ Imprimir Reporte Limpio</button>
            </form>
        </div>

        <div class="report-stats-grid">
            <div class="stat-card highlight">
                <span class="stat-label">Recaudación Total</span>
                <span class="stat-value">${money(res.recaudacion_total)}</span>
                <small class="text-xs text-gray-500">Ingreso bruto verificado en caja</small>
            </div>
            <div class="stat-card">
                <span class="stat-label">Transacciones (Ventas)</span>
                <span class="stat-value">${res.total_ventas}</span>
                <small class="text-xs text-gray-500">Ventas completadas en el período</small>
            </div>
            <div class="stat-card">
                <span class="stat-label">Unidades Vendidas</span>
                <span class="stat-value">${res.unidades_vendidas}</span>
                <small class="text-xs text-gray-500">Productos entregados</small>
            </div>
            <div class="stat-card">
                <span class="stat-label">Ticket Promedio</span>
                <span class="stat-value">${money(res.ticket_promedio)}</span>
                <small class="text-xs text-gray-500">Monto promedio por compra</small>
            </div>
        </div>

        <div class="report-section-grid">
            <!-- PRODUCTOS MÁS VENDIDOS -->
            <div class="report-card">
                <h3>🏆 Productos Más Vendidos</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-center">Unidades</th>
                            <th class="text-right">Total (Bs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(rep?.productos_mas_vendidos || []).map((p, i) => `
                            <tr>
                                <td><span class="font-bold text-xs text-gray-600">${escapeHtml(p.codigo || '')}</span></td>
                                <td><b>${escapeHtml(p.nombre)}</b></td>
                                <td class="text-xs text-gray-500">${escapeHtml(p.categoria)}</td>
                                <td class="text-center font-bold text-emerald-700">${p.unidades_vendidas}</td>
                                <td class="text-right font-extrabold text-gray-800">${money(p.total_recaudado)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="5" class="text-center py-4 text-gray-400">Sin ventas en este rango de fechas.</td></tr>'}
                    </tbody>
                </table>
            </div>

            <!-- VENTAS POR MÉTODO DE PAGO Y RENDIMIENTO -->
            <div class="report-card">
                <h3>💳 Recaudación por Método de Pago</h3>
                <div class="flex flex-col gap-3 mb-6">
                    ${(rep?.ventas_por_metodo || []).map((m) => `
                        <div class="flex justify-between items-center p-3 bg-gray-50 border rounded">
                            <div>
                                <b class="uppercase text-xs tracking-wider text-gray-800">${escapeHtml(m.metodo_pago)}</b>
                                <div class="text-xs text-gray-500">${m.transacciones} venta(s)</div>
                            </div>
                            <strong class="text-base text-emerald-700">${money(m.total_recaudado)}</strong>
                        </div>
                    `).join('') || '<div class="text-xs text-gray-400">No hay datos de pago.</div>'}
                </div>

                <h3>🧑‍🍳 Desempeño por Vendedor</h3>
                <div class="flex flex-col gap-2">
                    ${(rep?.ventas_por_vendedor || []).map((v) => `
                        <div class="flex justify-between items-center p-2 text-xs border-b">
                            <span><b>${escapeHtml(v.vendedor)}</b> <small class="text-gray-500">(${escapeHtml(v.rol)})</small></span>
                            <span>${v.total_ventas} ventas · <b>${money(v.total_recaudado)}</b></span>
                        </div>
                    `).join('') || '<div class="text-xs text-gray-400">No hay datos de vendedores.</div>'}
                </div>
            </div>
        </div>
    </div>`;
}

function adminAuditTab() {
    return `
    <div class="flex flex-col gap-4">
        <div class="audit-filter-bar flex justify-between items-center flex-wrap gap-3">
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-xs font-bold text-gray-700">Filtrar bitácora:</span>
                <select id="auditActionFilter">
                    <option value="">Todas las acciones</option>
                    <option value="MODIFICAR_PRECIO" ${state.auditFilters.accion === 'MODIFICAR_PRECIO' ? 'selected' : ''}>Cambios de Precio</option>
                    <option value="MODIFICAR_STOCK" ${state.auditFilters.accion === 'MODIFICAR_STOCK' ? 'selected' : ''}>Cambios de Stock</option>
                    <option value="AJUSTE_INVENTARIO" ${state.auditFilters.accion === 'AJUSTE_INVENTARIO' ? 'selected' : ''}>Ajustes de Inventario</option>
                    <option value="VENTA_REGISTRADA" ${state.auditFilters.accion === 'VENTA_REGISTRADA' ? 'selected' : ''}>Ventas</option>
                    <option value="CREAR" ${state.auditFilters.accion === 'CREAR' ? 'selected' : ''}>Creaciones</option>
                    <option value="DESACTIVAR" ${state.auditFilters.accion === 'DESACTIVAR' ? 'selected' : ''}>Desactivaciones</option>
                </select>
                <select id="auditEntityFilter">
                    <option value="">Todas las entidades</option>
                    <option value="productos" ${state.auditFilters.entidad === 'productos' ? 'selected' : ''}>Productos</option>
                    <option value="usuarios" ${state.auditFilters.entidad === 'usuarios' ? 'selected' : ''}>Usuarios</option>
                    <option value="ventas" ${state.auditFilters.entidad === 'ventas' ? 'selected' : ''}>Ventas</option>
                </select>
                <button id="applyAuditFiltersBtn" class="px-3 py-1 bg-gray-800 text-white text-xs rounded hover:bg-black font-semibold">Aplicar</button>
            </div>
            <button id="reloadAuditBtn" class="text-xs px-3 py-1 border rounded hover:bg-gray-100">🔄 Actualizar</button>
        </div>

        <div class="report-card">
            <h3>🛡️ Bitácora de Auditoría del Sistema</h3>
            <p class="text-xs text-gray-500 mb-3">Registro inmutable de acciones críticas: cambios de precios, stock, ventas y usuarios.</p>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Actor (Usuario)</th>
                            <th>Acción</th>
                            <th>Entidad / ID</th>
                            <th>Valores Anteriores</th>
                            <th>Valores Nuevos</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${state.auditLogs.map((a) => {
                            let badgeClass = 'badge-stock';
                            if (a.accion === 'MODIFICAR_PRECIO') badgeClass = 'badge-price';
                            else if (a.accion === 'CREAR') badgeClass = 'badge-create';
                            else if (a.accion === 'DESACTIVAR' || a.accion === 'ELIMINAR') badgeClass = 'badge-deactivate';
                            else if (a.accion === 'VENTA_REGISTRADA') badgeClass = 'badge-sale';

                            return `
                            <tr>
                                <td class="text-xs whitespace-nowrap text-gray-500">${escapeHtml(a.fecha)}</td>
                                <td><b>${escapeHtml(a.usuario || 'Sistema')}</b> <small class="text-gray-400">(${escapeHtml(a.rol || 'auto')})</small></td>
                                <td><span class="badge-audit ${badgeClass}">${escapeHtml(a.accion)}</span></td>
                                <td><span class="text-xs font-mono">${escapeHtml(a.entidad)} #${a.entidad_id ?? '-'}</span></td>
                                <td class="text-xs font-mono text-gray-600 max-w-xs truncate" title="${escapeHtml(a.datos_anteriores || '')}">${escapeHtml(a.datos_anteriores || '-')}</td>
                                <td class="text-xs font-mono text-gray-800 max-w-xs truncate" title="${escapeHtml(a.datos_nuevos || '')}">${escapeHtml(a.datos_nuevos || '-')}</td>
                                <td class="text-xs text-gray-700">${escapeHtml(a.detalles || '')}</td>
                            </tr>`;
                        }).join('') || '<tr><td colspan="7" class="text-center py-6 text-gray-400">No hay registros de auditoría aún.</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KARDEX DE MOVIMIENTOS DE INVENTARIO -->
        <div class="report-card">
            <h3>📦 Movimientos Recientes de Inventario (Kardex)</h3>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Tipo Movimiento</th>
                            <th class="text-center">Stock Previo</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Nuevo Stock</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${state.inventoryMovements.map((m) => `
                            <tr>
                                <td class="text-xs text-gray-500">${escapeHtml(m.fecha)}</td>
                                <td><span class="font-bold text-xs">${escapeHtml(m.codigo)}</span></td>
                                <td><b>${escapeHtml(m.producto)}</b></td>
                                <td><span class="text-xs font-bold uppercase ${m.tipo_movimiento === 'egreso_venta' ? 'text-rose-700' : 'text-emerald-700'}">${escapeHtml(m.tipo_movimiento)}</span></td>
                                <td class="text-center font-mono">${m.stock_anterior}</td>
                                <td class="text-center font-bold ${m.cantidad < 0 ? 'text-rose-700' : 'text-emerald-700'}">${m.cantidad > 0 ? `+${m.cantidad}` : m.cantidad}</td>
                                <td class="text-center font-mono font-bold">${m.stock_nuevo}</td>
                                <td class="text-xs text-gray-600">${escapeHtml(m.motivo || '')}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="8" class="text-center py-6 text-gray-400">No hay movimientos de inventario registrados.</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function productAdminPage() {
    const product = state.products.find((item) => Number(item.id_producto) === Number(state.editingProductId));
    const categories = state.categories.filter((category) => Number(category.activa) || Number(category.id_categoria) === Number(product?.id_categoria));

    return `
    <div class="flex flex-col gap-6">
        <form id="productForm" class="form-card admin-form">
            <h2>${product ? 'Editar producto e inventario' : 'Añadir nuevo producto'}</h2>
            ${product ? `<input name="id_producto" type="hidden" value="${Number(product.id_producto)}">` : ''}

            <div class="grid grid-cols-2 gap-3">
                <label>
                    Código único de producto *
                    <input name="codigo" value="${escapeHtml(product?.codigo || '')}" placeholder="Ej: HAM-001" required maxlength="50" style="text-transform:uppercase;">
                </label>
                <label>
                    Categoría *
                    <select name="id_categoria" required>
                        ${categories.map((category) => `<option value="${category.id_categoria}" ${Number(category.id_categoria) === Number(product?.id_categoria) ? 'selected' : ''}>${escapeHtml(category.nombre)}</option>`).join('')}
                    </select>
                </label>
            </div>

            <label>
                Nombre del producto *
                <input name="nombre" value="${escapeHtml(product?.nombre || '')}" required maxlength="120">
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label>
                    Precio de venta (Bs) *
                    <input name="precio" type="number" min="0" step="0.01" value="${product ? Number(product.precio) : ''}" required>
                </label>
                <label>
                    Existencias / Stock *
                    <input name="stock" type="number" min="0" step="1" value="${product ? Number(product.stock) : 50}" required>
                </label>
            </div>

            ${product?.imagen ? `
                <div class="current-product-image">
                    <span>Imagen actual</span>
                    <img src="${escapeHtml(imageFor(product.imagen, product.categoria))}" alt="${escapeHtml(product.nombre)}">
                </div>
            ` : ''}

            <label>
                Subir imagen desde el equipo
                <input name="imagen_archivo" type="file" accept="image/jpeg,image/png,image/webp">
            </label>
            <small class="text-xs text-gray-500">Formatos JPG, PNG o WebP; máximo 2 MB.</small>

            <label>
                URL de imagen externa (opcional)
                <input name="imagen" value="${escapeHtml(product?.imagen || '')}" maxlength="255" placeholder="https://..." autocomplete="off">
            </label>

            ${product?.imagen ? '<label class="check"><input name="quitar_imagen" type="checkbox"> Quitar imagen actual</label>' : ''}

            <label>
                Descripción
                <textarea name="descripcion" rows="3">${escapeHtml(product?.descripcion || '')}</textarea>
            </label>

            <label class="check">
                <input name="disponible" type="checkbox" ${!product || Number(product.disponible) ? 'checked' : ''}>
                Disponible para la venta
            </label>

            <div class="form-actions mt-3">
                <button class="primary">${product ? 'Guardar cambios ↗' : 'Guardar producto ↗'}</button>
                ${product ? '<button type="button" data-cancel-product-edit class="px-4 py-2 border rounded">Cancelar</button>' : ''}
            </div>
        </form>

        <!-- AJUSTE RÁPIDO DE STOCK MODAL / SECTION -->
        ${state.adjustingProductId ? `
            <div class="form-card admin-form bg-amber-50 border-amber-200">
                <h3 class="font-bold text-amber-900">📦 Ajuste Rápido de Stock</h3>
                <p class="text-xs text-amber-800">
                    Producto: <b>${escapeHtml(state.products.find((p) => p.id_producto === state.adjustingProductId)?.nombre)}</b>
                    (Stock actual: ${state.products.find((p) => p.id_producto === state.adjustingProductId)?.stock})
                </p>
                <form id="stockAdjustForm" class="mt-3 flex flex-col gap-3">
                    <input type="hidden" name="id_producto" value="${state.adjustingProductId}">
                    <label>
                        Cantidad a sumar (positiva) o restar (negativa)
                        <input type="number" step="1" name="cantidad" placeholder="Ej: +10 o -5" class="p-2 border rounded font-bold" required>
                    </label>
                    <label>
                        Motivo del ajuste
                        <input type="text" name="motivo" placeholder="Ej: Lote de compra #481 o Merma por daño" class="p-2 border rounded" required>
                    </label>
                    <div class="flex gap-2">
                        <button class="primary py-2 text-xs">Confirmar Ajuste ↗</button>
                        <button type="button" id="cancelAdjustStockBtn" class="px-3 py-2 border text-xs rounded">Cancelar</button>
                    </div>
                </form>
            </div>
        ` : ''}

        <!-- LISTA DE PRODUCTOS ADMINISTRABLES -->
        <div class="admin-products">
            <h3 class="font-bold text-lg mb-2">Inventario Actual (${state.products.length} productos)</h3>
            ${state.products.map((item) => {
                const stock = Number(item.stock ?? 0);
                return `
                <article class="p-3 border rounded bg-white flex items-center gap-3">
                    <img src="${escapeHtml(imageFor(item.imagen, item.categoria))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt="" class="w-16 h-12 object-cover rounded">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded">${escapeHtml(item.codigo || 'SIN-COD')}</span>
                            <b>${escapeHtml(item.nombre)}</b>
                        </div>
                        <small class="text-gray-500">
                            ${escapeHtml(item.categoria)} · ${money(item.precio)} ·
                            <span class="font-bold ${stock <= 0 ? 'text-red-600' : stock < 10 ? 'text-amber-700' : 'text-emerald-700'}">Existencias: ${stock}</span> ·
                            ${Number(item.disponible) ? '<span class="text-green-700 font-semibold">Disponible</span>' : '<span class="text-red-600 font-semibold">No disponible</span>'}
                        </small>
                    </div>
                    <button data-quick-adjust-stock="${item.id_producto}" class="text-xs px-2 py-1 bg-amber-50 border border-amber-300 rounded text-amber-800 hover:bg-amber-100">± Stock</button>
                    <button data-edit-product="${item.id_producto}" class="text-xs px-2 py-1 border rounded hover:bg-gray-100">Editar</button>
                    <button data-toggle-product="${item.id_producto}" class="text-xs px-2 py-1 border rounded hover:bg-gray-100">
                        ${Number(item.disponible) ? 'Desactivar' : 'Activar'}
                    </button>
                </article>`;
            }).join('')}
        </div>
    </div>`;
}

function categoryAdminPage() {
    const category = state.categories.find((item) => Number(item.id_categoria) === Number(state.editingCategoryId));
    return `
    <form id="categoryForm" class="form-card admin-form">
        <h2>${category ? 'Editar categoría' : 'Añadir categoría'}</h2>
        <label>Nombre<input name="nombre" value="${escapeHtml(category?.nombre || '')}" maxlength="80" required></label>
        <label>Descripción<input name="descripcion" value="${escapeHtml(category?.descripcion || '')}" maxlength="255"></label>
        <div class="form-actions">
            <button class="primary">${category ? 'Guardar cambios' : 'Guardar categoría'} ↗</button>
            ${category ? '<button type="button" data-cancel-category-edit>Cancelar</button>' : ''}
        </div>
    </form>
    <div class="admin-products">
        ${state.categories.map((item) => `
            <article>
                <div>
                    <b>${escapeHtml(item.nombre)}</b>
                    <small>${escapeHtml(item.descripcion || '')} · ${Number(item.activa) ? 'Activa' : 'Inactiva'}</small>
                </div>
                <button data-edit-category="${item.id_categoria}">Editar</button>
                <button data-toggle-category="${item.id_categoria}">${Number(item.activa) ? 'Desactivar' : 'Activar'}</button>
            </article>
        `).join('')}
    </div>`;
}

function userAdminPage() {
    return `
    <form id="userForm" class="form-card admin-form">
        <h2>Registrar Personal (Administrador / Vendedor)</h2>
        <label>Nombre Completo<input name="nombre" required maxlength="100"></label>
        <label>Correo electrónico<input name="correo" type="email" required maxlength="150"></label>
        <label>Teléfono<input name="telefono" maxlength="25"></label>
        <label>Contraseña inicial<input name="contrasena" type="password" required minlength="6"></label>
        <label>
            Rol en el sistema
            <select name="rol">
                <option value="vendedor" selected>Vendedor (Punto de Venta únicamente)</option>
                <option value="administrador">Administrador (Acceso total, inventario, reportes)</option>
                <option value="cliente">Cliente</option>
            </select>
        </label>
        <button class="primary">Crear cuenta de personal ↗</button>
    </form>
    <div class="admin-users mt-6">
        <h3 class="font-bold text-lg mb-2">Cuentas Registradas</h3>
        ${state.users.map((u) => `
            <article class="p-3 border rounded bg-white">
                <div>
                    <b>${escapeHtml(u.nombre)}</b>
                    <small class="text-xs text-gray-500">
                        <span class="font-bold uppercase px-2 py-0.5 rounded bg-gray-100">${escapeHtml(u.rol)}</span> ·
                        ${escapeHtml(u.correo)} · ${Number(u.activo) ? 'Activa' : 'Desactivada'}
                    </small>
                </div>
                <label>Nombre<input data-user-name="${u.id_usuario}" value="${escapeHtml(u.nombre)}" maxlength="100"></label>
                <label>Correo<input data-user-email="${u.id_usuario}" type="email" value="${escapeHtml(u.correo)}" maxlength="150"></label>
                <label>Teléfono<input data-user-phone="${u.id_usuario}" value="${escapeHtml(u.telefono || '')}" maxlength="25"></label>
                <label>Nueva contraseña<input data-user-password="${u.id_usuario}" type="password" minlength="6" placeholder="Dejar en blanco para conservar"></label>
                <label>
                    Rol
                    <select data-user-role="${u.id_usuario}" ${Number(u.id_usuario) === Number(state.user.id_usuario) ? 'disabled title="No puedes cambiar tu propio rol"' : ''}>
                        <option value="administrador" ${u.rol === 'administrador' ? 'selected' : ''}>Administrador</option>
                        <option value="vendedor" ${u.rol === 'vendedor' ? 'selected' : ''}>Vendedor</option>
                        <option value="cliente" ${u.rol === 'cliente' ? 'selected' : ''}>Cliente</option>
                    </select>
                </label>
                <div class="flex gap-2 mt-2">
                    <button data-save-user="${u.id_usuario}" class="text-xs px-3 py-1 bg-gray-800 text-white rounded">Guardar</button>
                    <button data-toggle-user="${u.id_usuario}" ${Number(u.id_usuario) === Number(state.user.id_usuario) ? 'disabled title="No puedes desactivar tu propia cuenta"' : ''} class="text-xs px-3 py-1 border rounded">
                        ${Number(u.activo) ? 'Desactivar' : 'Reactivar'}
                    </button>
                </div>
            </article>
        `).join('') || '<div class="empty">No hay cuentas registradas.</div>'}
    </div>`;
}

function adminPage() {
    const tabs = [
        ['reports', '📊 Reportes de Ventas'],
        ['products', '🍔 Inventario y Productos'],
        ['audit', '🛡️ Auditoría y Bitácora'],
        ['users', '👥 Personal y Roles'],
        ['orders', '📦 Pedidos Web'],
        ['categories', '🏷️ Categorías'],
        ['config', '⚙️ Negocio'],
    ];

    let body = '';
    if (state.adminTab === 'reports') body = adminReportsTab();
    else if (state.adminTab === 'products') body = productAdminPage();
    else if (state.adminTab === 'audit') body = adminAuditTab();
    else if (state.adminTab === 'users') body = userAdminPage();
    else if (state.adminTab === 'orders') body = ordersPage();
    else if (state.adminTab === 'categories') body = categoryAdminPage();
    else if (state.adminTab === 'config') body = `
        <form id="configForm" class="form-card admin-form">
            <h2>Configuración del Negocio</h2>
            <label>Nombre del comercio<input name="nombre_negocio" value="${escapeHtml(state.config.nombre_negocio)}" required></label>
            <label>Costo de entrega a domicilio (Bs)<input name="costo_envio" type="number" min="0" step="0.50" value="${Number(state.config.costo_envio)}" required></label>
            <button class="primary mt-3">Guardar configuración ↗</button>
        </form>`;

    return `<section class="page-wrap admin-page">
        <span class="eyebrow">CONTROL TOTAL</span>
        <h1>Panel Administrativo</h1>
        <div class="admin-tabs">
            ${tabs.map(([key, label]) => `<button data-admin-tab="${key}" class="${state.adminTab === key ? 'selected' : ''}">${label}</button>`).join('')}
        </div>
        ${body}
    </section>`;
}

// -------------------------------------------------------------
// PEDIDOS WEB DE CLIENTES (Módulo Conservado)
// -------------------------------------------------------------
function ordersPage() {
    return `<section class="page-wrap">
        <span class="eyebrow">SEGUIMIENTO</span>
        <h1>${isAdmin() ? 'Pedidos Recibidos (Canal Online)' : 'Mis Pedidos'}</h1>
        <div class="orders-list">
            ${state.orders.map((o) => `
                <article class="order-card">
                    <div class="order-title">
                        <div>
                            <small>PEDIDO #${o.id_pedido}${isAdmin() ? ` · ${escapeHtml(o.usuario || '')} (${escapeHtml(o.correo || '')})` : ''}</small>
                            <h2>${o.tipo_entrega === 'domicilio' ? `Entrega a domicilio · ${escapeHtml(o.direccion_entrega || '')}` : 'Retiro en local'}</h2>
                        </div>
                        <span class="status status-${escapeHtml(o.estado)}">${escapeHtml(String(o.estado).replaceAll('_', ' '))}</span>
                    </div>
                    <p class="text-xs text-gray-500">${new Date(String(o.fecha_pedido).replace(' ', 'T')).toLocaleString('es-BO')}</p>
                    <div class="order-items">
                        ${(o.items || []).map((item) => `<span>${item.cantidad} × ${escapeHtml(item.nombre || item.producto)} <b>${money(item.subtotal)}</b></span>`).join('')}
                    </div>
                    <div class="order-total-line">
                        <span>Subtotal ${money(o.subtotal)} · Envío ${money(o.costo_envio)}</span>
                        <strong>${money(o.total)}</strong>
                    </div>
                    ${isAdmin() ? `
                        <div class="status-control">
                            <label>Estado
                                <select data-status="${o.id_pedido}">
                                    ${['pendiente', 'aceptado', 'en_preparacion', 'listo', 'entregado', 'cancelado'].map((s) => `<option value="${s}" ${o.estado === s ? 'selected' : ''}>${s.replaceAll('_', ' ')}</option>`).join('')}
                                </select>
                            </label>
                            <button class="primary" data-save-status="${o.id_pedido}">Actualizar estado</button>
                        </div>
                    ` : ''}
                </article>
            `).join('') || '<div class="empty">Aún no hay pedidos registrados.</div>'}
        </div>
    </section>`;
}

// -------------------------------------------------------------
// CARRITO DE CLIENTE (CANAL ONLINE)
// -------------------------------------------------------------
function cartPage() {
    const fee = Number(state.config.costo_envio || 0);
    return `<section class="page-wrap">
        <div class="section-heading">
            <div>
                <span class="eyebrow">TU SELECCIÓN</span>
                <h1>Tu pedido en línea</h1>
            </div>
            <button class="link-button" data-view="menu">← Seguir explorando</button>
        </div>
        ${state.cart.length ? `
            <div class="checkout-layout">
                <div class="cart-list">
                    ${state.cart.map((item, index) => `
                        <article class="cart-line">
                            <img src="${escapeHtml(imageFor(item.imagen))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt="">
                            <div>
                                <h3>${escapeHtml(item.nombre)}</h3>
                                <p>${money(item.precio)} por unidad</p>
                                <div class="quantity">
                                    <button data-qty="${index}" data-delta="-1">−</button>
                                    <b>${item.cantidad}</b>
                                    <button data-qty="${index}" data-delta="1">＋</button>
                                    <button class="remove" data-remove="${index}">Quitar</button>
                                </div>
                            </div>
                            <strong>${money(item.precio * item.cantidad)}</strong>
                        </article>
                    `).join('')}
                </div>
                <form id="checkoutForm" class="summary-card">
                    <h2>Detalles de entrega</h2>
                    <label class="radio-option">
                        <input type="radio" name="tipo_entrega" value="recoger" checked>
                        <span><b>Recoger en local</b><small>Sin costo adicional</small></span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="tipo_entrega" value="domicilio">
                        <span><b>Entrega a domicilio</b><small>${money(fee)} de costo de envío</small></span>
                    </label>
                    <label class="address-field hidden">
                        Dirección de entrega
                        <input name="direccion_entrega" maxlength="255" placeholder="Calle, zona y número">
                    </label>
                    <label>
                        Referencia (opcional)
                        <input name="referencia_entrega" maxlength="255" placeholder="Ej: Puerta verde frente a la plaza">
                    </label>
                    <label>
                        Teléfono de contacto
                        <input name="telefono_contacto" type="tel" maxlength="25" value="${escapeHtml(state.user?.telefono || '')}" placeholder="Número de celular" required>
                    </label>
                    <label>
                        Nota para cocina (opcional)
                        <textarea name="observaciones" rows="2" maxlength="500" placeholder="Sin cebolla, salsas aparte…"></textarea>
                    </label>
                    <div class="totals">
                        <span>Subtotal <b>${money(subtotal())}</b></span>
                        <span>Envío <b class="shipping-cost">${money(0)}</b></span>
                        <strong>Total <b class="order-total">${money(subtotal())}</b></strong>
                    </div>
                    <button class="primary wide">Confirmar pedido online <span>↗</span></button>
                </form>
            </div>
        ` : `
            <div class="empty">
                <h2>Tu carrito está vacío</h2>
                <p>Explora el menú y agrega tus platos favoritos.</p>
                <button class="primary mt-4" data-view="menu">Ver catálogo ↗</button>
            </div>
        `}
    </section>`;
}

// -------------------------------------------------------------
// AUTENTICACIÓN
// -------------------------------------------------------------
function authPage() {
    if (state.setupRequired) {
        return `<section class="auth-layout">
            <div class="auth-copy">
                <span class="eyebrow">INSTALACIÓN INICIAL</span>
                <h1>Configura tu<br>administración.</h1>
                <p>Esta base de datos aún no cuenta con un Administrador registrado. Crea la cuenta inicial para habilitar la gestión de inventario, punto de venta y reportes.</p>
            </div>
            <form id="setupForm" class="form-card">
                <h2>Crear Administrador Inicial</h2>
                <label>Nombre completo<input name="nombre" required maxlength="100" autocomplete="name"></label>
                <label>Correo electrónico<input name="correo" type="email" required maxlength="150" autocomplete="email"></label>
                <label>Contraseña maestra<input name="contrasena" type="password" required minlength="8" autocomplete="new-password"></label>
                <button class="primary wide mt-3">Crear cuenta e ingresar <span>↗</span></button>
            </form>
        </section>`;
    }

    const register = state.authMode === 'register';
    return `<section class="auth-layout">
        <div class="auth-copy">
            <span class="eyebrow">ACCESO AL SISTEMA</span>
            <h1>Sabor urbano.<br>Control total.</h1>
            <p>Inicia sesión como Administrador, Vendedor (Punto de Venta) o Cliente para acceder a las funciones correspondientes a tu rol.</p>
        </div>
        <form id="authForm" class="form-card">
            <h2>${register ? 'Crear cuenta de cliente' : 'Iniciar sesión'}</h2>
            ${register ? `
                <label>Nombre<input name="nombre" required maxlength="100" autocomplete="name"></label>
                <label>Teléfono<input name="telefono" maxlength="25" autocomplete="tel"></label>
            ` : `
                <label>Tipo de acceso
                    <select name="tipo_cuenta">
                        <option value="staff">Personal (Administrador / Vendedor)</option>
                        <option value="cliente">Cliente (Pedidos en línea)</option>
                    </select>
                </label>
            `}
            <label>Correo electrónico<input name="correo" type="email" required autocomplete="email"></label>
            <label>Contraseña<input name="contrasena" type="password" required minlength="6" autocomplete="current-password"></label>
            <button class="primary wide mt-3">${register ? 'Crear cuenta' : 'Ingresar al sistema'} <span>↗</span></button>
            <button class="link-button mt-2" type="button" data-action="auth-toggle">
                ${register ? '¿Ya tienes una cuenta? Iniciar sesión' : '¿Primera vez como cliente? Crear cuenta'}
            </button>
        </form>
    </section>`;
}

// -------------------------------------------------------------
// RENDERIZADO PRINCIPAL
// -------------------------------------------------------------
function render() {
    let page;
    if (state.view === 'auth') page = authPage();
    else if (state.view === 'cart') page = cartPage();
    else if (state.view === 'pos' && isStaff()) page = posPage();
    else if (state.view === 'sales' && isStaff()) page = salesPage();
    else if (state.view === 'orders') page = ordersPage();
    else if (state.view === 'admin' && isAdmin()) page = adminPage();
    else page = menuPage();

    root.innerHTML = `<div class="app-shell">
        ${header()}
        ${state.notice ? `<div class="toast">${escapeHtml(state.notice)}</div>` : ''}
        ${page}
        <footer>© ${new Date().getFullYear()} Urban Bites · Sistema de Inventario, Ventas y Auditoría.</footer>
    </div>`;

    bind();
}

function persistCart() {
    localStorage.setItem('urban-bites.cart', JSON.stringify(state.cart));
}

async function showView(view) {
    if (['cart', 'orders'].includes(view) && !state.user) {
        state.view = 'auth';
        state.notice = 'Inicia sesión para continuar.';
        render();
        return;
    }
    if (['pos', 'sales'].includes(view) && !isStaff()) {
        state.view = 'auth';
        state.notice = 'Acceso exclusivo para personal de caja o administración.';
        render();
        return;
    }
    if (view === 'admin' && !isAdmin()) {
        state.view = 'menu';
        state.notice = 'Acceso denegado. Se requiere perfil Administrador.';
        render();
        return;
    }

    state.view = view;
    try {
        if (view === 'pos') {
            await loadStore();
        } else if (view === 'sales') {
            await loadSales();
        } else if (view === 'orders') {
            await loadOrders();
        } else if (view === 'admin') {
            state.editingProductId = null;
            state.editingCategoryId = null;
            state.adjustingProductId = null;
            if (state.adminTab === 'reports') await loadReports();
            else if (state.adminTab === 'audit') await loadAudit();
            else if (state.adminTab === 'users') await loadUsers();
            else if (state.adminTab === 'orders') await loadOrders();
            else await loadAdminProducts();
        }
    } catch (error) {
        state.notice = error.message;
    }

    render();
    window.scrollTo(0, 0);
}

// -------------------------------------------------------------
// VINCULACIÓN DE EVENTOS DEL DOM
// -------------------------------------------------------------
function bind() {
    // Navegación
    root.querySelectorAll('[data-view]').forEach((btn) => btn.addEventListener('click', () => showView(btn.dataset.view)));

    // Filtros de categoría en menú público
    root.querySelectorAll('[data-category]').forEach((btn) => btn.addEventListener('click', () => {
        state.category = btn.dataset.category;
        render();
    }));

    // Búsqueda en menú público
    root.querySelector('[data-search]')?.addEventListener('input', (e) => {
        const cursor = e.target.selectionStart;
        state.query = e.target.value;
        render();
        const input = root.querySelector('[data-search]');
        if (input) {
            input.focus();
            input.setSelectionRange(cursor, cursor);
        }
    });

    root.querySelector('[data-scroll]')?.addEventListener('click', () => {
        document.querySelector('#catalogo')?.scrollIntoView({ behavior: 'smooth' });
    });

    // Carrito de cliente
    root.querySelectorAll('[data-add]').forEach((btn) => btn.addEventListener('click', () => {
        const product = state.products.find((p) => Number(p.id_producto) === Number(btn.dataset.add));
        if (!product) return;
        const stock = Number(product.stock ?? 0);
        const current = state.cart.find((item) => Number(item.id_producto) === Number(product.id_producto));
        const currentQty = current ? current.cantidad : 0;

        if (currentQty + 1 > stock) {
            showNotice(`No hay suficiente stock de ${product.nombre} (Disponibles: ${stock}).`);
            return;
        }

        if (current) current.cantidad++;
        else state.cart.push({
            id_producto: Number(product.id_producto),
            nombre: product.nombre,
            precio: Number(product.precio),
            imagen: imageFor(product.imagen, product.categoria),
            cantidad: 1,
        });

        persistCart();
        showNotice(`${product.nombre} añadido al pedido.`);
    }));

    root.querySelectorAll('[data-qty]').forEach((btn) => btn.addEventListener('click', () => {
        const item = state.cart[Number(btn.dataset.qty)];
        const product = state.products.find((p) => Number(p.id_producto) === Number(item.id_producto));
        const stock = product ? Number(product.stock) : 999;
        const newQty = item.cantidad + Number(btn.dataset.delta);

        if (newQty > stock) {
            showNotice(`Solo hay ${stock} unidades disponibles.`);
            return;
        }
        item.cantidad = Math.max(1, newQty);
        persistCart();
        render();
    }));

    root.querySelectorAll('[data-remove]').forEach((btn) => btn.addEventListener('click', () => {
        state.cart.splice(Number(btn.dataset.remove), 1);
        persistCart();
        render();
    }));

    // Selección de entrega en checkout cliente
    root.querySelectorAll('[name="tipo_entrega"]').forEach((radio) => radio.addEventListener('change', () => {
        if (!radio.checked) return;
        const delivery = radio.value === 'domicilio';
        root.querySelector('.address-field')?.classList.toggle('hidden', !delivery);
        const addr = root.querySelector('[name="direccion_entrega"]');
        if (addr) addr.required = delivery;
        const fee = delivery ? Number(state.config.costo_envio) : 0;
        const shp = root.querySelector('.shipping-cost');
        if (shp) shp.textContent = money(fee);
        const tot = root.querySelector('.order-total');
        if (tot) tot.textContent = money(subtotal() + fee);
    }));

    // Confirmación de pedido online (cliente)
    root.querySelector('#checkoutForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        const payload = {
            ...data,
            id_usuario: Number(state.user.id_usuario),
            items: state.cart.map((item) => ({ id_producto: item.id_producto, cantidad: item.cantidad })),
        };
        try {
            const result = await api.post('agregar_pedido', payload);
            state.cart = [];
            persistCart();
            state.view = 'orders';
            state.notice = `Pedido #${result.id_pedido} confirmado por ${money(result.total)}.`;
            await loadOrders();
            render();
        } catch (error) {
            showNotice(error.message);
        }
    });

    // =========================================================
    // EVENTOS DEL PUNTO DE VENTA (POS)
    // =========================================================
    // Búsqueda en el POS
    const posSearchInput = root.querySelector('#posSearchInput');
    if (posSearchInput) {
        posSearchInput.addEventListener('input', (e) => {
            state.posQuery = e.target.value;
            render();
            const inp = root.querySelector('#posSearchInput');
            if (inp) {
                inp.focus();
                inp.setSelectionRange(inp.value.length, inp.value.length);
            }
        });
    }

    root.querySelector('#posClearSearch')?.addEventListener('click', () => {
        state.posQuery = '';
        render();
    });

    // Categorías en POS
    root.querySelectorAll('[data-pos-cat]').forEach((btn) => btn.addEventListener('click', () => {
        state.posCategory = btn.dataset.posCat;
        render();
    }));

    // Agregar producto al carrito del POS
    root.querySelectorAll('[data-pos-add]').forEach((card) => card.addEventListener('click', (e) => {
        if (e.target.tagName === 'INPUT') return;
        const id = Number(card.dataset.posAdd);
        const prod = state.products.find((p) => Number(p.id_producto) === id);
        if (!prod) return;
        const stock = Number(prod.stock ?? 0);
        if (stock <= 0) {
            showNotice(`El producto '${prod.nombre}' está agotado.`);
            return;
        }

        const existing = state.posCart.find((it) => it.id_producto === id);
        if (existing) {
            if (existing.cantidad + 1 > stock) {
                showNotice(`No hay más existencias disponibles (Stock: ${stock}).`);
                return;
            }
            existing.cantidad++;
        } else {
            state.posCart.push({
                id_producto: id,
                codigo: prod.codigo,
                nombre: prod.nombre,
                precio: Number(prod.precio),
                cantidad: 1,
                stock_disponible: stock,
            });
        }
        render();
    }));

    // Controles de cantidad en ticket POS
    root.querySelectorAll('[data-pos-qty]').forEach((btn) => btn.addEventListener('click', () => {
        const item = state.posCart[Number(btn.dataset.posQty)];
        const newQty = item.cantidad + Number(btn.dataset.delta);
        if (newQty > item.stock_disponible) {
            showNotice(`Máximo stock disponible: ${item.stock_disponible}`);
            return;
        }
        item.cantidad = Math.max(1, newQty);
        render();
    }));

    root.querySelectorAll('[data-pos-input-qty]').forEach((inp) => inp.addEventListener('change', (e) => {
        const item = state.posCart[Number(inp.dataset.posInputQty)];
        let val = parseInt(e.target.value, 10);
        if (isNaN(val) || val < 1) val = 1;
        if (val > item.stock_disponible) {
            showNotice(`Solo hay ${item.stock_disponible} unidades disponibles.`);
            val = item.stock_disponible;
        }
        item.cantidad = val;
        render();
    }));

    root.querySelectorAll('[data-pos-remove]').forEach((btn) => btn.addEventListener('click', () => {
        state.posCart.splice(Number(btn.dataset.posRemove), 1);
        render();
    }));

    root.querySelector('#posClearCart')?.addEventListener('click', () => {
        state.posCart = [];
        render();
    });

    // Métodos de pago en POS
    root.querySelectorAll('[data-pay-method]').forEach((btn) => btn.addEventListener('click', () => {
        state.posCustomer.metodo_pago = btn.dataset.payMethod;
        render();
    }));

    // Cálculo dinámico de cambio en efectivo
    const montoInp = root.querySelector('#posMontoRecibido');
    if (montoInp) {
        montoInp.addEventListener('input', (e) => {
            state.posCustomer.monto_recibido = e.target.value;
            const cambioText = root.querySelector('#posCambioText');
            if (cambioText) {
                const total = posTotal();
                const recibido = Number(e.target.value || 0);
                const cambio = Math.max(0, recibido - total);
                cambioText.textContent = money(cambio);
            }
        });
    }

    // Procesar venta en POS (Fetch / AJAX con transacción y validación)
    root.querySelector('#posCheckoutForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!state.posCart.length) {
            showNotice('El carrito de venta está vacío.');
            return;
        }

        const form = e.currentTarget;
        const formData = new FormData(form);
        const clienteNombre = formData.get('cliente_nombre')?.toString().trim() || 'Consumidor Final';
        const clienteDocumento = formData.get('cliente_documento')?.toString().trim() || '';
        const montoRecibidoVal = formData.get('monto_recibido');
        const montoRecibido = montoRecibidoVal ? Number(montoRecibidoVal) : null;
        const total = posTotal();

        if (state.posCustomer.metodo_pago === 'efectivo') {
            if (!montoRecibido || montoRecibido < total) {
                showNotice(`El monto recibido (${money(montoRecibido)}) no alcanza para cubrir el total de ${money(total)}.`);
                return;
            }
        }

        const payload = {
            cliente_nombre: clienteNombre,
            cliente_documento: clienteDocumento,
            metodo_pago: state.posCustomer.metodo_pago,
            monto_recibido: montoRecibido,
            descuento: 0,
            items: state.posCart.map((it) => ({
                id_producto: it.id_producto,
                cantidad: it.cantidad,
            })),
        };

        try {
            const res = await api.post('agregar_venta', payload);
            state.lastSaleReceipt = res.venta;
            state.posCart = [];
            state.posCustomer.monto_recibido = '';
            showNotice(`¡Venta ${res.venta.numero_venta} completada por ${money(res.venta.total)}!`);
            await loadStore(); // Actualiza existencias inmediatamente
            render();
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelector('#closeReceiptBtn')?.addEventListener('click', () => {
        state.lastSaleReceipt = null;
        render();
    });

    // Ver ticket desde historial de ventas
    root.querySelectorAll('[data-view-sale]').forEach((btn) => btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.viewSale);
        try {
            const sale = await api.get(`obtener_venta?id_venta=${id}`);
            state.lastSaleReceipt = sale;
            render();
        } catch (error) {
            showNotice(error.message);
        }
    }));

    // Filtros de ventas
    root.querySelector('#filterSalesBtn')?.addEventListener('click', async () => {
        const start = root.querySelector('#salesDateStart')?.value;
        const end = root.querySelector('#salesDateEnd')?.value;
        if (start) state.reportDates.inicio = start;
        if (end) state.reportDates.fin = end;
        try {
            state.sales = await api.get(`listar_ventas?fecha_inicio=${encodeURIComponent(state.reportDates.inicio)}&fecha_fin=${encodeURIComponent(state.reportDates.fin)}`);
            render();
        } catch (error) {
            showNotice(error.message);
        }
    });

    // =========================================================
    // EVENTOS DEL PANEL DE ADMINISTRACIÓN
    // =========================================================
    root.querySelectorAll('[data-admin-tab]').forEach((btn) => btn.addEventListener('click', async () => {
        state.adminTab = btn.dataset.adminTab;
        state.editingProductId = null;
        state.editingCategoryId = null;
        state.adjustingProductId = null;
        try {
            if (state.adminTab === 'reports') await loadReports();
            else if (state.adminTab === 'audit') await loadAudit();
            else if (state.adminTab === 'users') await loadUsers();
            else if (state.adminTab === 'orders') await loadOrders();
            else await loadAdminProducts();
        } catch (error) {
            showNotice(error.message);
        }
        render();
    }));

    // Filtrar reportes
    root.querySelector('#reportFilterForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        state.reportDates.inicio = data.fecha_inicio;
        state.reportDates.fin = data.fecha_fin;
        await loadReports();
        render();
    });

    // Imprimir reporte limpio
    root.querySelector('#printReportBtn')?.addEventListener('click', () => {
        window.print();
    });

    // Filtros de auditoría
    root.querySelector('#applyAuditFiltersBtn')?.addEventListener('click', async () => {
        state.auditFilters.accion = root.querySelector('#auditActionFilter')?.value || '';
        state.auditFilters.entidad = root.querySelector('#auditEntityFilter')?.value || '';
        await loadAudit();
        render();
    });

    root.querySelector('#reloadAuditBtn')?.addEventListener('click', async () => {
        await loadAudit();
        render();
    });

    // Guardar / Crear producto
    root.querySelector('#productForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const data = Object.fromEntries(new FormData(form));
        const imageFile = form.elements.imagen_archivo?.files?.[0];
        const removeImage = Boolean(form.elements.quitar_imagen?.checked);
        const editingId = state.editingProductId;
        const current = editingId ? state.products.find((p) => Number(p.id_producto) === Number(editingId)) : null;

        delete data.imagen_archivo;
        delete data.quitar_imagen;
        data.disponible = form.elements.disponible.checked ? 1 : 0;
        data.id_categoria = Number(data.id_categoria);
        data.precio = Number(data.precio);
        data.stock = Number(data.stock || 0);
        data.codigo = data.codigo.trim().toUpperCase();
        data.imagen = data.imagen?.trim() || null;
        if (imageFile && !data.imagen && !removeImage) data.imagen = current?.imagen || null;

        try {
            let productId = editingId;
            if (editingId !== null) {
                if (removeImage && !imageFile) {
                    await api.post('quitar_imagen_producto', { id_producto: Number(editingId) });
                    data.imagen = null;
                }
                data.id_producto = Number(editingId);
                await api.put('update_producto', data);
            } else {
                const created = await api.post('agregar_producto', data);
                productId = Number(created.id_producto);
            }

            if (imageFile) {
                await api.postFormData('subir_imagen_producto', { id_producto: Number(productId), imagen_archivo: imageFile });
            }

            state.editingProductId = null;
            await loadAdminProducts();
            showNotice(editingId !== null ? 'Producto actualizado correctamente.' : 'Producto agregado al inventario.');
        } catch (error) {
            showNotice(error.message);
        }
    });

    // Ajuste rápido de stock
    root.querySelectorAll('[data-quick-adjust-stock]').forEach((btn) => btn.addEventListener('click', () => {
        state.adjustingProductId = Number(btn.dataset.quickAdjustStock);
        render();
        root.querySelector('#stockAdjustForm')?.scrollIntoView({ behavior: 'smooth' });
    }));

    root.querySelector('#cancelAdjustStockBtn')?.addEventListener('click', () => {
        state.adjustingProductId = null;
        render();
    });

    root.querySelector('#stockAdjustForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        data.id_producto = Number(data.id_producto);
        data.cantidad = parseInt(data.cantidad, 10);
        try {
            const res = await api.post('ajustar_stock', data);
            state.adjustingProductId = null;
            await loadAdminProducts();
            showNotice(`Stock ajustado: nuevo saldo ${res.stock_nuevo} unidades.`);
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelectorAll('[data-edit-product]').forEach((btn) => btn.addEventListener('click', () => {
        state.editingProductId = Number(btn.dataset.editProduct);
        render();
        root.querySelector('#productForm')?.scrollIntoView({ behavior: 'smooth' });
    }));

    root.querySelector('[data-cancel-product-edit]')?.addEventListener('click', () => {
        state.editingProductId = null;
        render();
    });

    root.querySelectorAll('[data-toggle-product]').forEach((btn) => btn.addEventListener('click', async () => {
        const prod = state.products.find((p) => Number(p.id_producto) === Number(btn.dataset.toggleProduct));
        if (!prod) return;
        try {
            await api.put('update_producto', {
                ...prod,
                id_producto: Number(prod.id_producto),
                codigo: prod.codigo,
                id_categoria: Number(prod.id_categoria),
                precio: Number(prod.precio),
                stock: Number(prod.stock),
                disponible: Number(prod.disponible) ? 0 : 1,
            });
            await loadAdminProducts();
            render();
            showNotice(`Estado de '${prod.nombre}' actualizado.`);
        } catch (error) {
            showNotice(error.message);
        }
    }));

    // Categorías admin
    root.querySelectorAll('[data-edit-category]').forEach((btn) => btn.addEventListener('click', () => {
        state.editingCategoryId = Number(btn.dataset.editCategory);
        render();
    }));

    root.querySelector('[data-cancel-category-edit]')?.addEventListener('click', () => {
        state.editingCategoryId = null;
        render();
    });

    root.querySelector('#categoryForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        const editingId = state.editingCategoryId;
        try {
            if (editingId !== null) {
                const cat = state.categories.find((c) => Number(c.id_categoria) === Number(editingId));
                await api.put('update_categoria', { ...data, id_categoria: Number(editingId), activa: Number(cat.activa) });
                state.editingCategoryId = null;
            } else {
                await api.post('agregar_categoria', data);
            }
            await loadAdminProducts();
            showNotice(editingId !== null ? 'Categoría actualizada.' : 'Categoría agregada.');
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelectorAll('[data-toggle-category]').forEach((btn) => btn.addEventListener('click', async () => {
        const cat = state.categories.find((c) => Number(c.id_categoria) === Number(btn.dataset.toggleCategory));
        if (!cat) return;
        try {
            await api.put('update_categoria', {
                id_categoria: Number(cat.id_categoria),
                nombre: cat.nombre,
                descripcion: cat.descripcion || '',
                activa: Number(cat.activa) ? 0 : 1,
            });
            await loadAdminProducts();
            render();
            showNotice('Estado de la categoría actualizado.');
        } catch (error) {
            showNotice(error.message);
        }
    }));

    // Usuarios admin
    root.querySelector('#userForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        try {
            await api.post('agregar_usuario', data);
            await loadUsers();
            e.currentTarget.reset();
            showNotice('Cuenta de usuario creada exitosamente.');
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelectorAll('[data-save-user]').forEach((btn) => btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.saveUser);
        const user = state.users.find((u) => Number(u.id_usuario) === id);
        if (!user) return;
        const nombre = root.querySelector(`[data-user-name="${id}"]`).value.trim();
        const correo = root.querySelector(`[data-user-email="${id}"]`).value.trim();
        const telefono = root.querySelector(`[data-user-phone="${id}"]`).value;
        const contrasena = root.querySelector(`[data-user-password="${id}"]`).value;
        const rol = root.querySelector(`[data-user-role="${id}"]`).value;
        try {
            await api.put('update_usuario', {
                id_usuario: id,
                nombre,
                correo,
                telefono,
                ...(contrasena ? { contrasena } : {}),
                rol,
                activo: Number(user.activo),
            });
            await loadUsers();
            showNotice('Cuenta de usuario actualizada.');
        } catch (error) {
            showNotice(error.message);
        }
    }));

    root.querySelectorAll('[data-toggle-user]').forEach((btn) => btn.addEventListener('click', async () => {
        const user = state.users.find((u) => Number(u.id_usuario) === Number(btn.dataset.toggleUser));
        if (!user) return;
        try {
            if (Number(user.activo)) {
                await api.del('delete_usuario', { id_usuario: Number(user.id_usuario) });
            } else {
                await api.put('update_usuario', {
                    id_usuario: Number(user.id_usuario),
                    nombre: user.nombre,
                    correo: user.correo,
                    telefono: user.telefono || '',
                    rol: user.rol,
                    activo: 1,
                });
            }
            await loadUsers();
            showNotice('Estado de cuenta actualizado.');
        } catch (error) {
            showNotice(error.message);
        }
    }));

    // Configuración general
    root.querySelector('#configForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.currentTarget));
        data.costo_envio = Number(data.costo_envio);
        try {
            await api.put('update_configuracion', data);
            await loadAdminProducts();
            showNotice('Configuración del comercio guardada.');
        } catch (error) {
            showNotice(error.message);
        }
    });

    // Actualizar estado de pedidos (admin)
    root.querySelectorAll('[data-save-status]').forEach((btn) => btn.addEventListener('click', async () => {
        const id = Number(btn.dataset.saveStatus);
        const estado = root.querySelector(`[data-status="${id}"]`).value;
        try {
            await api.put('update_pedido', { id_pedido: id, estado });
            await loadOrders();
            showNotice('Estado del pedido actualizado.');
        } catch (error) {
            showNotice(error.message);
        }
    }));

    // =========================================================
    // EVENTOS DE AUTENTICACIÓN
    // =========================================================
    root.querySelector('#authForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(e.currentTarget));
        const tipoCuenta = payload.tipo_cuenta;
        delete payload.tipo_cuenta;

        try {
            let endpoint;
            if (state.authMode === 'register') {
                endpoint = 'auth/registrar-cliente';
            } else if (tipoCuenta === 'staff') {
                endpoint = 'auth/login-usuario';
            } else {
                endpoint = 'auth/login-cliente';
            }

            const result = await api.post(endpoint, payload);
            const session = saveSession(result.user, result.token);
            state.user = session.user;
            state.notice = '';

            if (isStaff()) {
                state.view = 'pos';
            } else {
                state.view = 'menu';
            }

            await loadStore();
            render();
            showNotice(`¡Bienvenido/a, ${session.user.nombre}!`);
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelector('#setupForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(e.currentTarget));
        try {
            const result = await api.post('auth/setup-admin', payload);
            state.user = saveSession(result.user, result.token).user;
            state.setupRequired = false;
            state.view = 'admin';
            state.adminTab = 'reports';
            await loadStore();
            render();
            showNotice('Cuenta administradora inicial creada exitosamente.');
        } catch (error) {
            showNotice(error.message);
        }
    });

    root.querySelector('[data-action="auth-toggle"]')?.addEventListener('click', () => {
        state.authMode = state.authMode === 'register' ? 'login' : 'register';
        render();
    });

    root.querySelector('[data-action="logout"]')?.addEventListener('click', async () => {
        await api.post('auth/logout', {}).catch(() => {});
        clearSession();
        state.user = null;
        state.view = 'menu';
        state.posCart = [];
        state.authMode = 'login';
        state.notice = '';
        render();
        showNotice('Sesión finalizada.');
    });
}

// -------------------------------------------------------------
// INICIALIZACIÓN
// -------------------------------------------------------------
async function start() {
    try {
        await loadStore();
    } catch (error) {
        state.notice = error.message;
    }

    try {
        const setup = await api.get('auth/setup-status');
        state.setupRequired = Boolean(setup?.setup_required);
    } catch (error) {
        if (!state.notice) state.notice = error.message;
    }

    if (state.user) {
        try {
            const verification = await api.get('auth/verify');
            if (verification?.user) {
                state.user = { ...state.user, ...verification.user };
            }
        } catch {
            clearSession();
            state.user = null;
        }
    }

    render();
}

start();
