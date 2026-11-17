# Guía de Publicación en InfinityFree · Urban Bites
### Sistema de Gestión de Inventario, Ventas (POS) y Auditoría

Esta guía detalla los pasos para publicar **Urban Bites** en el hosting gratuito **InfinityFree**, asegurando la correcta configuración de la base de datos relacional, el backend en PHP con PDO y el frontend interactivo con Tailwind CSS y Fetch API.

---

## Requisitos Previos

- Cuenta activa en [InfinityFree](https://www.infinityfree.com/) con un subdominio gratuito (`.infinityfreeapp.com`, `.rf.gd`, etc.) o dominio propio vinculado.
- Acceso a **MySQL Databases** y **phpMyAdmin** desde el panel de control (cPanel / VistaPanel).
- Cliente FTP (FileZilla) o el Administrador de Archivos web del panel.
- **Importante de Seguridad:** Nunca compartas tus contraseñas de hosting, FTP, base de datos ni el archivo `backend/.env`.

---

## 1. Importación de la Base de Datos Relacional

La base de datos está completamente normalizada y contiene las tablas requeridas por la consigna académica (`roles`, `usuarios`, `categorias`, `productos`, `ventas`, `detalle_ventas`, `movimientos_inventario`, `auditoria`, `configuracion`, `pedidos` y `detalle_pedidos`), además de restricciones `FOREIGN KEY`, `NOT NULL`, `UNIQUE`, `CHECK` y datos iniciales de catálogo.

1. Entra a tu panel de InfinityFree y abre **MySQL Databases**.
2. Crea una nueva base de datos (por ejemplo, con sufijo `urbanbites`).
3. Anota los datos de conexión que muestra el panel:
   - **MySQL Hostname:** (ejemplo: `sqlxxx.infinityfree.com` — **NO** uses `localhost`).
   - **MySQL Database Name:** (ejemplo: `epiz_12345678_urbanbites`).
   - **MySQL Username:** (ejemplo: `epiz_12345678`).
   - **MySQL Password:** (la contraseña de tu cuenta de hosting).
   - **MySQL Port:** `3306`.
4. Haz clic en el botón de **phpMyAdmin** correspondiente a esa base de datos.
5. Selecciona la base de datos en la barra lateral izquierda.
6. Ve a la pestaña **Importar** (Import).
7. Selecciona el archivo `deploy/infinityfree/database/urbanbites_schema.sql` de tu proyecto.
   *(Este archivo está especialmente preparado para hosting: no incluye instrucciones `CREATE DATABASE` ni `USE`, las cuales fallarían por restricciones de permisos en hosting compartido).*
8. Haz clic en **Continuar** (Go) y confirma que todas las tablas y datos iniciales se creen sin errores.

---

## 2. Estructura de Archivos en `htdocs`

Sube los archivos a la carpeta `htdocs` del servidor utilizando FileZilla o el administrador web. La estructura final debe ser:

```text
htdocs/
├── index.html                   (copiado desde frontend/index.html)
├── src/                         (carpeta frontend/src/ completa)
│   ├── assets/
│   ├── styles/
│   │   ├── tailwind.css         (estilos Tailwind compilados estáticos)
│   │   ├── urban-bites.css
│   │   └── urban-bites-admin.css
│   ├── utils/
│   │   ├── api.js
│   │   └── session.js
│   └── main.js
└── backend/                     (carpeta backend/ completa)
    ├── .htaccess                (protege archivos internos y restringe navegación)
    ├── .env                     (archivo privado creado en el servidor, ver paso 3)
    ├── config/
    │   ├── config.php
    │   ├── ConexionPDO.php
    │   └── AuthMiddleware.php
    ├── controllers/
    │   ├── ApiController.php
    │   ├── AuthController.php
    │   ├── UsuarioController.php
    │   ├── RolController.php
    │   ├── ProductoController.php
    │   ├── VentaController.php
    │   ├── ReporteController.php
    │   ├── AuditoriaController.php
    │   ├── CategoriaController.php
    │   ├── PedidoController.php
    │   ├── DetallePedidoController.php
    │   ├── ClienteController.php
    │   └── ConfiguracionController.php
    ├── models/
    │   ├── Rol.php
    │   ├── Usuario.php
    │   ├── Producto.php
    │   ├── Venta.php
    │   ├── DetalleVenta.php
    │   ├── MovimientoInventario.php
    │   ├── Auditoria.php
    │   ├── Categoria.php
    │   ├── Pedido.php
    │   ├── DetallePedido.php
    │   ├── Cliente.php
    │   └── Configuracion.php
    ├── routes/
    │   └── api.php
    └── uploads/
        └── products/            (directorio con permisos de escritura para imágenes)
```

> **Nota:** No subas archivos con contraseñas, archivos de migración `.sql`, carpetas `.git`, respaldos `.bak` ni archivos `.rest` a la raíz pública. El archivo `backend/.htaccess` bloquea automáticamente el acceso directo a estos tipos de archivo.

---

## 3. Configuración Segura de Variables de Entorno (`.env`)

1. En el Administrador de Archivos de InfinityFree, entra a `htdocs/backend/`.
2. Crea un archivo llamado exactamente `.env` (con el punto al inicio).
3. Pega el siguiente formato y reemplaza con los datos reales de tu panel de MySQL:

```ini
DB_HOST=sqlxxx.infinityfree.com
DB_NAME=epiz_12345678_urbanbites
DB_USER=epiz_12345678
DB_PASSWORD="TU_CONTRASENA_DE_MYSQL"
DB_PORT=3306
DB_CHARSET=utf8mb4
JWT_SECRET="GENERA_UNA_CLAVE_ALEATORIA_DE_64_CARACTERES_HEXADECIMALES"
```

> **Consejo para JWT_SECRET:** Puedes generar una clave segura de 64 caracteres combinando letras y números aleatorios (o ejecutando en PHP `bin2hex(random_bytes(32))`). Debe ser única y privada.

---

## 4. Creación Segura de Cuentas de Prueba (Sin Contraseñas Expuestas)

El sistema incluye un asistente de instalación segura que detecta automáticamente si la base de datos no tiene un administrador:

### A. Cuenta de Administrador (Primer Ingreso)
1. Abre tu dominio en el navegador (`https://tudominio.infinityfreeapp.com`).
2. Haz clic en **Ingresar**.
3. El sistema detectará que es una instalación nueva y mostrará el formulario:
   **"Crear Administrador Inicial"**.
4. Ingresa el nombre (ej. `Administrador General`), correo (ej. `admin@urbanbites.com`) y una contraseña segura de tu elección (mínimo 8 caracteres).
5. Haz clic en **Crear cuenta e ingresar**. La cuenta se registrará con `password_hash()` y el rol `administrador`.

### B. Cuenta de Vendedor para Punto de Venta (POS)
1. Con la sesión de Administrador iniciada, ve a la pestaña **Administración** -> **Personal y Roles**.
2. En el formulario **"Registrar Personal"**:
   - **Nombre:** `Vendedor Turno Mañana` (o el nombre deseado).
   - **Correo:** `vendedor@urbanbites.com`.
   - **Teléfono:** `70012345`.
   - **Contraseña:** asigna una contraseña temporal segura.
   - **Rol:** selecciona **Vendedor**.
3. Haz clic en **Crear cuenta de personal**.
4. ¡Listo! Ahora puedes cerrar sesión y probar el ingreso con la cuenta de Vendedor.

### C. Comprobación de Restricciones por Rol
- **Inicio como Vendedor:** El vendedor solo tiene acceso al **Punto de Venta (POS)**, consulta de catálogo y su propio historial de ventas. Si intenta acceder a rutas administrativas, el backend bloquea la petición con `HTTP 403 Forbidden` (`AuthMiddleware::esAdmin()`).
- **Inicio como Administrador:** El administrador tiene acceso a la gestión de inventario (códigos, precios, stock, ajustes con motivo), reporte de ventas filtrado por fechas con opción de impresión limpia, y la bitácora completa de auditoría.

---

## 5. Verificación de Funcionamiento y Pruebas en Vivo

1. **Punto de Venta:** Agrega productos al carrito del POS, verifica el cálculo automático de totales, selecciona método de pago (Efectivo/QR/Tarjeta), ingresa el efectivo recibido y confirma el cambio. Al procesar la venta:
   - Se ejecuta una transacción en MySQL (`SELECT ... FOR UPDATE`, registro de venta y detalles, descuento de stock).
   - Se abre el ticket imprimible de la venta.
   - Las existencias del catálogo se actualizan inmediatamente en pantalla vía Fetch/AJAX.
2. **Reportes:** En Administración -> Reportes, filtra por rango de fechas y presiona **Imprimir Reporte Limpio** para validar la hoja de impresión sin barras de navegación.
3. **Auditoría:** En Administración -> Auditoría, verifica que cada venta, ajuste de inventario o modificación de precio haya registrado el actor, la acción, valores previos/nuevos y la fecha exacta.
