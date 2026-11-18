# Urban Bites

Sistema local para gestionar inventario y ventas de comida rápida con PHP, MySQL, JavaScript y WAMP.

## Objetivo actual

Administrar un catálogo de comida rápida, sus existencias y las ventas realizadas por usuarios con rol de administrador o vendedor. El sistema muestra el menú, permite buscar y filtrar productos, administra categorías, usuarios e imágenes, registra ventas desde el punto de venta, descuenta stock dentro de una transacción y presenta reportes por fechas. Las operaciones importantes quedan registradas en una bitácora local protegida.

La ejecución y demostración se realiza en WAMP local. Este alcance no incluye InfinityFree ni otro alojamiento externo.

## Base de datos: seis tablas

El archivo `UrbanBites.sql` crea la base `urbanbites_wamp_6` y exactamente estas seis tablas:

1. `roles`: roles administrador y vendedor.
2. `usuarios`: acceso, datos y estado de las cuentas; cada cuenta referencia un rol.
3. `categorias`: clasificación del menú.
4. `productos`: código, nombre, precio, imagen, disponibilidad y stock.
5. `ventas`: cabecera de cada operación, vendedor, cliente, fecha, pago y total.
6. `detalle_ventas`: productos, cantidades y precios que componen cada venta.

La bitácora se guarda como JSON Lines en `storage/auditoria.log`, fuera de la carpeta pública del frontend; no agrega una séptima tabla. El SQL incluye productos de ejemplo y una regla para impedir que el stock quede negativo. Las ventas se procesan en una transacción del backend.

## Instalación en WAMP

1. Inicia WAMP y confirma que Apache y MySQL estén activos.
2. En Navicat, conéctate al MySQL **local** e importa `UrbanBites.sql`. El script crea una base nueva llamada `urbanbites_wamp_6`; no elimina ni modifica la base anterior.
3. Copia `api/.env.example` como `api/.env`. Ajusta host, puerto, usuario, contraseña y `JWT_SECRET` a la instalación local. El nombre inicial de base es `urbanbites_wamp_6`.
4. Configura Apache para servir `C:/UrbanBites/frontend` y exponer `/api/` hacia `C:/UrbanBites/api/public/`.
5. Si usarás el dominio local `urbanbites.com`, añade `127.0.0.1 urbanbites.com` al archivo de hosts de Windows y reinicia Apache. Si tu carpeta está en otra unidad, reemplaza ambas rutas con las rutas reales.
6. Abre `http://urbanbites.com/` (o el host local que configuraste). En una base recién creada aparecerá el formulario para crear el primer administrador. Luego ese administrador crea cuentas de vendedor.

Ejemplo de VirtualHost para una instalación en `C:/UrbanBites`:

```apache
<VirtualHost *:80>
    ServerName urbanbites.com
    DocumentRoot "C:/UrbanBites/frontend"

    <Directory "C:/UrbanBites/frontend">
        Options FollowSymLinks
        AllowOverride All
        Require local
    </Directory>

    Alias "/api/" "C:/UrbanBites/api/public/"
    <Directory "C:/UrbanBites/api/public">
        Options FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

`frontend/.htaccess` sirve `public/index.html` como página inicial. El alias de API apunta únicamente a `api/public`; la configuración, los modelos, los controladores y `.env` quedan fuera de la carpeta pública.

## Uso

- Visitante: consulta, busca y filtra el menú.
- Administrador: gestiona categorías, productos, imágenes, usuarios, stock, reportes y bitácora.
- Vendedor: consulta el catálogo y registra ventas en el POS.
- Una venta guarda sus líneas, descuenta existencias y calcula importes en el servidor.

## REST Client de VS Code

Los archivos `.rest` de `api/doc/` documentan consultas para usuarios, roles, categorías, productos y ventas, además de reportes y auditoría. Configura la variable `baseUrl` en REST Client según el host local y realiza el login para copiar el token Bearer antes de probar rutas protegidas. Los roles se consultan; el administrador y el vendedor son los dos roles iniciales del sistema.

## Datos anteriores

La importación inicial es para una base vacía. La base anterior se deja intacta para conservar una copia de seguridad. Si ya existe `urbanbites_wamp_6`, revisa primero su contenido en Navicat; no ejecutes instrucciones de borrado sobre una base con datos que necesites.
