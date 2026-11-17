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
const state = {
    user: getSession()?.user || null,
    products: [], categories: [], orders: [], users: [], config: { nombre_negocio: 'Urban Bites', costo_envio: 0 },
    cart: readCart(),
    view: 'menu', category: 'Todos', query: '', notice: '', authMode: 'login', adminTab: 'orders', setupRequired: false,
    editingProductId: null, editingCategoryId: null,
};
const escapeHtml = (v = '') => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
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
const isAdmin = () => state.user?.rol === 'administrador';

async function loadStore() {
    const [products, categories, config] = await Promise.all([
        api.get('listarproductos'), api.get('listarcategorias'), api.get('obtener_configuracion'),
    ]);
    state.products = (Array.isArray(products) ? products : []).filter((p) => Number(p.disponible) === 1);
    state.categories = Array.isArray(categories) ? categories : [];
    state.config = config || state.config;
}
async function loadAdminProducts() {
    const products = await api.get('listarproductos?incluir_no_disponibles=1');
    state.products = Array.isArray(products) ? products : [];
    state.categories = await api.get('listarcategorias?incluir_inactivas=1');
    state.config = await api.get('obtener_configuracion');
}
async function loadOrders() {
    const allOrders = await api.get('listarpedidos');
    const visible = isAdmin() ? allOrders : allOrders.filter((o) => Number(o.id_usuario) === Number(state.user.id_usuario));
    state.orders = await Promise.all(visible.map(async (order) => ({
        ...order,
        items: await api.get(`listardetallepedidos?id_pedido=${encodeURIComponent(order.id_pedido)}`),
    })));
}
function showNotice(message) {
    state.notice = message; render();
    window.setTimeout(() => { if (state.notice === message) { state.notice = ''; render(); } }, 4000);
}
function header() {
    return `<header class="topbar"><a class="brand" href="#" data-view="menu"><span class="brand-mark">UB</span><span>URBAN <b>BITES</b><small>COMIDA RÁPIDA · BUEN SABOR</small></span></a><nav>
        <button data-view="menu">Menú</button>${state.user ? `<button data-view="orders">${isAdmin() ? 'Pedidos' : 'Mis pedidos'}</button>${isAdmin() ? '<button data-view="admin">Administración</button>' : ''}<button data-action="logout" class="quiet">Salir</button>` : '<button data-view="auth" class="quiet">Ingresar</button>'}
        <button class="cart-button" data-view="cart">Carrito <b>${cartCount()}</b></button></nav></header>`;
}
function menuPage() {
    const products = state.products.filter((p) => Number(p.disponible) === 1 && (state.category === 'Todos' || p.categoria === state.category)
        && `${p.nombre} ${p.descripcion || ''}`.toLowerCase().includes(state.query.toLowerCase()));
    return `<section class="hero"><div><span class="eyebrow">RECIÉN HECHO · DIRECTO A TU MESA</span><h1>Antojo resuelto.<br><em>Sin complicaciones.</em></h1><p>Elige tus favoritos, confirma tu pedido y nosotros nos encargamos del resto.</p><button class="primary" data-scroll="catalogo">Explorar menú <span>↓</span></button></div><div class="hero-art"><img src="${imageFor('', 'Hamburguesas')}" alt="Comida de Urban Bites"><span class="hero-sticker">HECHO<br>CON SABOR</span></div></section>
    <section class="catalog" id="catalogo"><div class="section-heading"><div><span class="eyebrow">NUESTRO MENÚ</span><h2>Elige tu antojo</h2></div><label class="search">⌕ <input data-search placeholder="Buscar en el menú" value="${escapeHtml(state.query)}"></label></div>
    <div class="filters"><button data-category="Todos" class="${state.category === 'Todos' ? 'active' : ''}">Todo el menú</button>${state.categories.map((c) => `<button data-category="${escapeHtml(c.nombre)}" class="${state.category === c.nombre ? 'active' : ''}">${escapeHtml(c.nombre)}</button>`).join('')}</div>
    <div class="product-grid">${products.map((p, i) => `<article class="product-card"><div class="product-image"><img src="${escapeHtml(imageFor(p.imagen, p.categoria))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt="${escapeHtml(p.nombre)}" loading="lazy"><span class="product-number">${String(i + 1).padStart(2, '0')}</span></div><div class="product-info"><div class="product-meta"><span>${escapeHtml(p.categoria || 'Favoritos')}</span><strong>${money(p.precio)}</strong></div><h3>${escapeHtml(p.nombre)}</h3><p>${escapeHtml(p.descripcion || 'Preparado al momento con ingredientes frescos.')}</p><button class="add-button" data-add="${p.id_producto}">Añadir al pedido <b>＋</b></button></div></article>`).join('') || '<div class="empty">No encontramos productos en esta categoría.</div>'}</div></section>
    <section class="service-note"><span>01 / HECHO PARA TI</span><h2>Tu comida favorita,<br>en pocos pasos.</h2><p>Haz tu pedido online y elige si pasas a recogerlo o prefieres recibirlo en casa.</p></section>`;
}
function authPage() {
    if (state.setupRequired) {
        return `<section class="auth-layout"><div class="auth-copy"><span class="eyebrow">PRIMER INGRESO</span><h1>Configura tu<br>administración.</h1><p>Esta base de datos todavía no tiene una cuenta administradora. Crea la cuenta inicial para administrar el menú y los pedidos.</p></div><form id="setupForm" class="form-card"><h2>Crear administrador inicial</h2><label>Nombre<input name="nombre" required maxlength="100" autocomplete="name"></label><label>Correo electrónico<input name="correo" type="email" required maxlength="150" autocomplete="email"></label><label>Contraseña<input name="contrasena" type="password" required minlength="8" autocomplete="new-password"></label><button class="primary wide">Crear cuenta e ingresar <span>↗</span></button></form></section>`;
    }
    const register = state.authMode === 'register';
    return `<section class="auth-layout"><div class="auth-copy"><span class="eyebrow">BIENVENIDO A URBAN BITES</span><h1>El buen sabor<br>empieza aquí.</h1><p>Inicia sesión o crea una cuenta para confirmar tus pedidos y consultar su estado.</p></div><form id="authForm" class="form-card"><h2>${register ? 'Crea tu cuenta' : 'Iniciar sesión'}</h2>${register ? '<label>Nombre<input name="nombre" required maxlength="100" autocomplete="name"></label><label>Teléfono<input name="telefono" maxlength="25" autocomplete="tel"></label>' : '<label>Tipo de cuenta<select name="tipo_cuenta"><option value="cliente">Cliente</option><option value="administrador">Administrador</option></select></label>'}<label>Correo electrónico<input name="correo" type="email" required autocomplete="email"></label><label>Contraseña<input name="contrasena" type="password" required minlength="6" autocomplete="current-password"></label><button class="primary wide">${register ? 'Crear cuenta' : 'Ingresar'} <span>↗</span></button><button class="link-button" type="button" data-action="auth-toggle">${register ? 'Ya tengo una cuenta' : '¿Primera vez aquí? Crear cuenta'}</button></form></section>`;
}
function cartPage() {
    const fee = Number(state.config.costo_envio || 0);
    return `<section class="page-wrap"><div class="section-heading"><div><span class="eyebrow">TU SELECCIÓN</span><h1>Tu pedido</h1></div><button class="link-button" data-view="menu">← Seguir explorando</button></div>${state.cart.length ? `<div class="checkout-layout"><div class="cart-list">${state.cart.map((item, index) => `<article class="cart-line"><img src="${escapeHtml(imageFor(item.imagen))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt=""><div><h3>${escapeHtml(item.nombre)}</h3><p>${money(item.precio)} por unidad</p><div class="quantity"><button data-qty="${index}" data-delta="-1">−</button><b>${item.cantidad}</b><button data-qty="${index}" data-delta="1">＋</button><button class="remove" data-remove="${index}">Quitar</button></div></div><strong>${money(item.precio * item.cantidad)}</strong></article>`).join('')}</div><form id="checkoutForm" class="summary-card"><h2>Detalles del pedido</h2>
        <label class="radio-option"><input type="radio" name="tipo_entrega" value="recoger" checked><span><b>Recoger en local</b><small>Sin costo de entrega</small></span></label><label class="radio-option"><input type="radio" name="tipo_entrega" value="domicilio"><span><b>Entrega a domicilio</b><small>${money(fee)} de envío</small></span></label>
        <label class="address-field hidden">Dirección de entrega<input name="direccion_entrega" maxlength="255" placeholder="Calle, zona y número"></label><label>Referencia (opcional)<input name="referencia_entrega" maxlength="255" placeholder="Punto de referencia"></label><label>Teléfono de contacto<input name="telefono_contacto" type="tel" maxlength="25" value="${escapeHtml(state.user?.telefono || '')}" placeholder="Tu número de teléfono" required></label><label>Nota para cocina (opcional)<textarea name="observaciones" rows="2" maxlength="500" placeholder="Sin cebolla, extra salsa…"></textarea></label>
        <div class="totals"><span>Subtotal <b>${money(subtotal())}</b></span><span>Envío <b class="shipping-cost">${money(0)}</b></span><strong>Total <b class="order-total">${money(subtotal())}</b></strong></div><button class="primary wide">Confirmar pedido <span>↗</span></button></form></div>` : '<div class="empty"><h2>Tu carrito está esperando</h2><p>Agrega algo rico desde nuestro menú.</p><button class="primary" data-view="menu">Ver menú ↗</button></div>'}</section>`;
}
function ordersPage() {
    return `<section class="page-wrap"><span class="eyebrow">SEGUIMIENTO</span><h1>${isAdmin() ? 'Pedidos recibidos' : 'Mis pedidos'}</h1><div class="orders-list">${state.orders.map((o) => `<article class="order-card"><div class="order-title"><div><small>PEDIDO #${o.id_pedido}${isAdmin() ? ` · ${escapeHtml(o.usuario || '')} · ${escapeHtml(o.correo || '')}` : ''}</small><h2>${o.tipo_entrega === 'domicilio' ? `Entrega a domicilio · ${escapeHtml(o.direccion_entrega || '')}` : 'Retiro en local'}</h2></div><span class="status status-${escapeHtml(o.estado)}">${escapeHtml(String(o.estado).replaceAll('_', ' '))}</span></div><p>${new Date(String(o.fecha_pedido).replace(' ', 'T')).toLocaleString('es-BO')}</p><div class="order-items">${(o.items || []).map((item) => `<span>${item.cantidad} × ${escapeHtml(item.nombre || item.producto)} <b>${money(item.subtotal)}</b></span>`).join('')}</div><div class="order-total-line"><span>Subtotal ${money(o.subtotal)} · Envío ${money(o.costo_envio)}</span><strong>${money(o.total)}</strong></div>${isAdmin() ? `<div class="status-control"><label>Estado<select data-status="${o.id_pedido}">${['pendiente', 'aceptado', 'en_preparacion', 'listo', 'entregado', 'cancelado'].map((s) => `<option value="${s}" ${o.estado === s ? 'selected' : ''}>${s.replaceAll('_', ' ')}</option>`).join('')}</select></label><button class="primary" data-save-status="${o.id_pedido}">Actualizar estado</button></div>` : ''}</article>`).join('') || '<div class="empty">Aún no hay pedidos registrados.</div>'}</div></section>`;
}
function productAdminPage() {
    const product = state.products.find((item) => Number(item.id_producto) === Number(state.editingProductId));
    const categories = state.categories.filter((category) => Number(category.activa) || Number(category.id_categoria) === Number(product?.id_categoria));
    return `<form id="productForm" class="form-card admin-form"><h2>${product ? 'Editar producto' : 'Añadir producto'}</h2>${product ? `<input name="id_producto" type="hidden" value="${Number(product.id_producto)}">` : ''}<label>Nombre<input name="nombre" value="${escapeHtml(product?.nombre || '')}" required maxlength="120"></label><label>Categoría<select name="id_categoria" required>${categories.map((category) => `<option value="${category.id_categoria}" ${Number(category.id_categoria) === Number(product?.id_categoria) ? 'selected' : ''}>${escapeHtml(category.nombre)}</option>`).join('')}</select></label><label>Precio (Bs)<input name="precio" type="number" min="0" step="0.01" value="${product ? Number(product.precio) : ''}" required></label>${product?.imagen ? `<div class="current-product-image"><span>Imagen actual</span><img src="${escapeHtml(imageFor(product.imagen, product.categoria))}" alt="${escapeHtml(product.nombre)}"></div>` : ''}<label>Subir imagen desde el equipo<input name="imagen_archivo" type="file" accept="image/jpeg,image/png,image/webp"></label><small>Formatos JPG, PNG o WebP; máximo 2 MB. También puedes indicar una URL externa.</small><label>URL de imagen (opcional)<input name="imagen" value="${escapeHtml(product?.imagen || '')}" maxlength="255" placeholder="https://..." autocomplete="off"></label>${product?.imagen ? '<label class="check"><input name="quitar_imagen" type="checkbox"> Quitar imagen actual</label>' : ''}<label>Descripción<textarea name="descripcion" rows="3">${escapeHtml(product?.descripcion || '')}</textarea></label><label class="check"><input name="disponible" type="checkbox" ${!product || Number(product.disponible) ? 'checked' : ''}> Disponible en el menú</label><div class="form-actions"><button class="primary">${product ? 'Guardar cambios' : 'Guardar producto'} ↗</button>${product ? '<button type="button" data-cancel-product-edit>Cancelar</button>' : ''}</div></form><div class="admin-products">${state.products.map((item) => `<article><img src="${escapeHtml(imageFor(item.imagen, item.categoria))}" onerror="this.onerror=null;this.src='${placeholderImage}'" alt=""><div><b>${escapeHtml(item.nombre)}</b><small>${escapeHtml(item.categoria)} · ${money(item.precio)} · ${Number(item.disponible) ? 'Disponible' : 'No disponible'}</small></div><label>Categoría<select data-product-category="${item.id_producto}">${state.categories.map((category) => `<option value="${category.id_categoria}" ${Number(category.id_categoria) === Number(item.id_categoria) ? 'selected' : ''}>${escapeHtml(category.nombre)}</option>`).join('')}</select></label><button data-edit-product="${item.id_producto}">Editar</button><button data-toggle-product="${item.id_producto}">${Number(item.disponible) ? 'Desactivar' : 'Activar'}</button></article>`).join('')}</div>`;
}

function categoryAdminPage() {
    const category = state.categories.find((item) => Number(item.id_categoria) === Number(state.editingCategoryId));
    return `<form id="categoryForm" class="form-card admin-form"><h2>${category ? 'Editar categoría' : 'Añadir categoría'}</h2><label>Nombre<input name="nombre" value="${escapeHtml(category?.nombre || '')}" maxlength="80" required></label><label>Descripción<input name="descripcion" value="${escapeHtml(category?.descripcion || '')}" maxlength="255"></label><div class="form-actions"><button class="primary">${category ? 'Guardar cambios' : 'Guardar categoría'} ↗</button>${category ? '<button type="button" data-cancel-category-edit>Cancelar</button>' : ''}</div></form><div class="admin-products">${state.categories.map((item) => `<article><div><b>${escapeHtml(item.nombre)}</b><small>${escapeHtml(item.descripcion || '')} · ${Number(item.activa) ? 'Activa' : 'Inactiva'}</small></div><button data-edit-category="${item.id_categoria}">Editar</button><button data-toggle-category="${item.id_categoria}">${Number(item.activa) ? 'Desactivar' : 'Activar'}</button></article>`).join('')}</div>`;
}

function adminPage() {
    const tabs = [['orders', 'Pedidos'], ['products', 'Productos'], ['categories', 'Categorías'], ['config', 'Configuración'], ['users', 'Usuarios']];
    let body = '';
    if (state.adminTab === 'orders') body = ordersPage();
    if (state.adminTab === 'products') body = productAdminPage();
    if (state.adminTab === 'categories') body = categoryAdminPage();
    if (state.adminTab === 'config') body = `<form id="configForm" class="form-card admin-form"><h2>Tu negocio</h2><label>Nombre del negocio<input name="nombre_negocio" value="${escapeHtml(state.config.nombre_negocio)}" required></label><label>Costo fijo de envío (Bs)<input name="costo_envio" type="number" min="0" step="0.01" value="${Number(state.config.costo_envio)}" required></label><button class="primary">Guardar configuración ↗</button></form>`;
    if (state.adminTab === 'users') body = `<form id="userForm" class="form-card admin-form"><h2>Crear cuenta</h2><label>Nombre<input name="nombre" required maxlength="100"></label><label>Correo electrónico<input name="correo" type="email" required maxlength="150"></label><label>Teléfono<input name="telefono" maxlength="25"></label><label>Contraseña temporal<input name="contrasena" type="password" required minlength="6"></label><label>Rol<select name="rol"><option value="cliente">Cliente</option><option value="administrador">Administrador</option></select></label><button class="primary">Crear cuenta ↗</button></form><div class="admin-users">${state.users.map((u) => `<article><div><b>${escapeHtml(u.nombre)}</b><small>${escapeHtml(u.rol)} · ${Number(u.activo) ? 'Activa' : 'Desactivada'}</small></div><label>Nombre<input data-user-name="${u.id_usuario}" value="${escapeHtml(u.nombre)}" maxlength="100"></label><label>Correo<input data-user-email="${u.id_usuario}" type="email" value="${escapeHtml(u.correo)}" maxlength="150"></label><label>Teléfono<input data-user-phone="${u.id_usuario}" value="${escapeHtml(u.telefono || '')}" maxlength="25"></label><label>Nueva contraseña<input data-user-password="${u.id_usuario}" type="password" minlength="6" placeholder="Dejar en blanco para conservar"></label><label>Rol<select data-user-role="${u.id_usuario}" ${Number(u.id_usuario) === Number(state.user.id_usuario) ? 'disabled' : ''}><option value="cliente" ${u.rol === 'cliente' ? 'selected' : ''}>Cliente</option><option value="administrador" ${u.rol === 'administrador' ? 'selected' : ''}>Administrador</option></select></label><button data-save-user="${u.id_usuario}">Guardar cambios</button><button data-toggle-user="${u.id_usuario}" ${Number(u.id_usuario) === Number(state.user.id_usuario) ? 'disabled title="No puedes desactivar tu propia cuenta"' : ''}>${Number(u.activo) ? 'Desactivar' : 'Reactivar'}</button></article>`).join('') || '<div class="empty">No hay cuentas registradas.</div>'}</div>`;
    return `<section class="page-wrap admin-page"><span class="eyebrow">PANEL DE CONTROL</span><h1>Administración</h1><div class="admin-tabs">${tabs.map(([key, label]) => `<button data-admin-tab="${key}" class="${state.adminTab === key ? 'selected' : ''}">${label}</button>`).join('')}</div>${body}</section>`;
}
function render() {
    let page = state.view === 'auth' ? authPage() : state.view === 'cart' ? cartPage() : state.view === 'orders' ? ordersPage() : state.view === 'admin' && isAdmin() ? adminPage() : menuPage();
    root.innerHTML = `<div class="app-shell">${header()}${state.notice ? `<div class="toast">${escapeHtml(state.notice)}</div>` : ''}${page}<footer>© ${new Date().getFullYear()} Urban Bites · Preparado con sabor.</footer></div>`;
    bind();
}
function persistCart() { localStorage.setItem('urban-bites.cart', JSON.stringify(state.cart)); }
async function showView(view) {
    if (['cart', 'orders', 'admin'].includes(view) && !state.user) { state.view = 'auth'; state.notice = 'Inicia sesión para continuar.'; render(); return; }
    if (view === 'admin' && !isAdmin()) { state.view = 'menu'; render(); return; }
    state.view = view;
    try {
        if (view === 'orders') await loadOrders();
        if (view === 'admin') { state.adminTab = 'orders'; state.editingProductId = null; state.editingCategoryId = null; state.orders = []; await Promise.all([loadAdminProducts(), loadOrders()]); }
    } catch (error) { state.notice = error.message; }
    render(); window.scrollTo(0, 0);
}
function bind() {
    root.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => showView(button.dataset.view)));
    root.querySelectorAll('[data-category]').forEach((button) => button.addEventListener('click', () => { state.category = button.dataset.category; render(); }));
    root.querySelector('[data-search]')?.addEventListener('input', (event) => { const cursor = event.target.selectionStart; state.query = event.target.value; render(); const input = root.querySelector('[data-search]'); input.focus(); input.setSelectionRange(cursor, cursor); });
    root.querySelector('[data-scroll]')?.addEventListener('click', () => document.querySelector('#catalogo')?.scrollIntoView({ behavior: 'smooth' }));
    root.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', () => { const product = state.products.find((p) => Number(p.id_producto) === Number(button.dataset.add)); const current = state.cart.find((item) => Number(item.id_producto) === Number(product.id_producto)); if (current) current.cantidad++; else state.cart.push({ id_producto: Number(product.id_producto), nombre: product.nombre, precio: Number(product.precio), imagen: imageFor(product.imagen, product.categoria), cantidad: 1 }); persistCart(); showNotice(`${product.nombre} se añadió al carrito.`); }));
    root.querySelectorAll('[data-qty]').forEach((button) => button.addEventListener('click', () => { const item = state.cart[Number(button.dataset.qty)]; item.cantidad = Math.max(1, item.cantidad + Number(button.dataset.delta)); persistCart(); render(); }));
    root.querySelectorAll('[data-remove]').forEach((button) => button.addEventListener('click', () => { state.cart.splice(Number(button.dataset.remove), 1); persistCart(); render(); }));
    root.querySelectorAll('[name="tipo_entrega"]').forEach((radio) => radio.addEventListener('change', () => { if (!radio.checked) return; const delivery = radio.value === 'domicilio'; root.querySelector('.address-field')?.classList.toggle('hidden', !delivery); root.querySelector('[name="direccion_entrega"]').required = delivery; const fee = delivery ? Number(state.config.costo_envio) : 0; root.querySelector('.shipping-cost').textContent = money(fee); root.querySelector('.order-total').textContent = money(subtotal() + fee); }));
    root.querySelector('#authForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const payload = Object.fromEntries(new FormData(event.currentTarget)); const role = payload.tipo_cuenta; delete payload.tipo_cuenta; try { const endpoint = state.authMode === 'register' ? 'auth/registrar-cliente' : role === 'administrador' ? 'auth/login-usuario' : 'auth/login-cliente'; const result = await api.post(endpoint, payload); const session = saveSession(result.user, result.token); state.user = session.user; state.view = 'menu'; state.notice = ''; await loadStore(); render(); } catch (error) { showNotice(error.message); } });
    root.querySelector('#setupForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const payload = Object.fromEntries(new FormData(event.currentTarget)); try { const result = await api.post('auth/setup-admin', payload); state.user = saveSession(result.user, result.token).user; state.setupRequired = false; state.view = 'menu'; await loadStore(); state.adminTab = 'products'; render(); showNotice('Cuenta administradora creada.'); } catch (error) { showNotice(error.message); } });
    root.querySelector('[data-action="auth-toggle"]')?.addEventListener('click', () => { state.authMode = state.authMode === 'register' ? 'login' : 'register'; render(); });
    root.querySelector('[data-action="logout"]')?.addEventListener('click', async () => { await api.post('auth/logout', {}).catch(() => {}); clearSession(); state.user = null; state.view = 'menu'; state.authMode = 'login'; state.notice = ''; render(); });
    root.querySelector('#checkoutForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); const payload = { ...data, id_usuario: Number(state.user.id_usuario), items: state.cart.map((item) => ({ id_producto: item.id_producto, cantidad: item.cantidad })) }; try { const result = await api.post('agregar_pedido', payload); state.cart = []; persistCart(); state.view = 'orders'; state.notice = `Pedido #${result.id_pedido} confirmado por ${money(result.total)}.`; await loadOrders(); render(); } catch (error) { showNotice(error.message); } });
    root.querySelectorAll('[data-admin-tab]').forEach((button) => button.addEventListener('click', async () => { state.adminTab = button.dataset.adminTab; state.editingProductId = null; state.editingCategoryId = null; try { if (state.adminTab === 'orders') await loadOrders(); else if (state.adminTab === 'users') state.users = await api.get('listarusuarios'); else await loadAdminProducts(); } catch (error) { state.notice = error.message; } render(); }));
    root.querySelectorAll('[data-save-status]').forEach((button) => button.addEventListener('click', async () => { try { const id = Number(button.dataset.saveStatus); const estado = root.querySelector(`[data-status="${id}"]`).value; await api.put('update_pedido', { id_pedido: id, estado }); await loadOrders(); showNotice('Estado del pedido actualizado.'); } catch (error) { showNotice(error.message); } }));
    root.querySelector('#productForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = Object.fromEntries(new FormData(form));
        const imageFile = form.elements.imagen_archivo.files[0];
        const removeImage = Boolean(form.elements.quitar_imagen?.checked);
        const editingId = state.editingProductId;
        const currentProduct = editingId === null ? null : state.products.find((product) => Number(product.id_producto) === Number(editingId));
        delete data.imagen_archivo;
        delete data.quitar_imagen;
        data.disponible = form.elements.disponible.checked ? 1 : 0;
        data.id_categoria = Number(data.id_categoria);
        data.precio = Number(data.precio);
        data.imagen = data.imagen?.trim() || null;
        if (imageFile && !data.imagen && !removeImage) data.imagen = currentProduct?.imagen || null;
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
            showNotice(editingId !== null ? 'Producto actualizado.' : 'Producto agregado al menú.');
        } catch (error) { showNotice(error.message); }
    });
    root.querySelectorAll('[data-toggle-product]').forEach((button) => button.addEventListener('click', async () => { const product = state.products.find((p) => Number(p.id_producto) === Number(button.dataset.toggleProduct)); try { await api.put('update_producto', { ...product, id_producto: Number(product.id_producto), id_categoria: Number(product.id_categoria), precio: Number(product.precio), disponible: Number(product.disponible) ? 0 : 1 }); await loadAdminProducts(); render(); } catch (error) { showNotice(error.message); } }));
    root.querySelectorAll('[data-edit-category]').forEach((button) => button.addEventListener('click', () => { state.editingCategoryId = Number(button.dataset.editCategory); render(); }));
    root.querySelectorAll('[data-toggle-category]').forEach((button) => button.addEventListener('click', async () => { const category = state.categories.find((item) => Number(item.id_categoria) === Number(button.dataset.toggleCategory)); if (!category) return; try { await api.put('update_categoria', { id_categoria: Number(category.id_categoria), nombre: category.nombre, descripcion: category.descripcion || '', activa: Number(category.activa) ? 0 : 1 }); await loadAdminProducts(); render(); } catch (error) { showNotice(error.message); } }));
    root.querySelector('#categoryForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); const editingId = state.editingCategoryId; try { if (editingId !== null) { const category = state.categories.find((item) => Number(item.id_categoria) === Number(editingId)); await api.put('update_categoria', { ...data, id_categoria: Number(editingId), activa: Number(category.activa) }); state.editingCategoryId = null; } else { await api.post('agregar_categoria', data); } await loadAdminProducts(); showNotice(editingId !== null ? 'Categoría actualizada.' : 'Categoría agregada.'); } catch (error) { showNotice(error.message); } });
    root.querySelector('#userForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); try { await api.post('agregar_usuario', data); state.users = await api.get('listarusuarios'); showNotice('Cuenta creada.'); } catch (error) { showNotice(error.message); } });
    root.querySelectorAll('[data-save-user]').forEach((button) => button.addEventListener('click', async () => { const id = Number(button.dataset.saveUser); const user = state.users.find((item) => Number(item.id_usuario) === id); if (!user) return; const nombre = root.querySelector(`[data-user-name="${id}"]`).value.trim(); const correo = root.querySelector(`[data-user-email="${id}"]`).value.trim(); const telefono = root.querySelector(`[data-user-phone="${id}"]`).value; const contrasena = root.querySelector(`[data-user-password="${id}"]`).value; const rol = root.querySelector(`[data-user-role="${id}"]`).value; try { await api.put('update_usuario', { id_usuario: id, nombre, correo, telefono, ...(contrasena ? { contrasena } : {}), rol, activo: Number(user.activo) }); state.users = await api.get('listarusuarios'); showNotice('Cuenta actualizada.'); } catch (error) { showNotice(error.message); } }));
    root.querySelectorAll('[data-toggle-user]').forEach((button) => button.addEventListener('click', async () => { const user = state.users.find((item) => Number(item.id_usuario) === Number(button.dataset.toggleUser)); if (!user) return; try { if (Number(user.activo)) await api.del('delete_usuario', { id_usuario: Number(user.id_usuario) }); else await api.put('update_usuario', { id_usuario: Number(user.id_usuario), nombre: user.nombre, correo: user.correo, telefono: user.telefono || '', rol: user.rol, activo: 1 }); state.users = await api.get('listarusuarios'); showNotice('Estado de cuenta actualizado.'); } catch (error) { showNotice(error.message); } }));
    root.querySelector('#configForm')?.addEventListener('submit', async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); data.costo_envio = Number(data.costo_envio); try { await api.put('update_configuracion', data); await loadAdminProducts(); showNotice('Configuración guardada.'); } catch (error) { showNotice(error.message); } });
    root.querySelectorAll('[data-edit-product]').forEach((button) => button.addEventListener('click', () => { state.editingProductId = Number(button.dataset.editProduct); render(); }));
    root.querySelector('[data-cancel-product-edit]')?.addEventListener('click', () => { state.editingProductId = null; render(); });
    root.querySelector('[data-cancel-category-edit]')?.addEventListener('click', () => { state.editingCategoryId = null; render(); });
}

async function start() {
    try { await loadStore(); } catch (error) { state.notice = error.message; }
    try { state.setupRequired = Boolean((await api.get('auth/setup-status')).setup_required); }
    catch (error) { if (!state.notice) state.notice = error.message; }
    if (state.user) { try { const verification = await api.get('auth/verify'); if (verification.user) state.user = { ...state.user, ...verification.user }; } catch { clearSession(); state.user = null; } }
    render();
}
start();
