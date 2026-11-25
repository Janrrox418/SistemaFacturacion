# SistemaFacturacion
Sistema de facturacion fase beta 

## Integración SAP

La URL de las consultas SAP se configura mediante la variable de entorno `SAP_API_URL`; no se incluye en el código. Debe estar configurada tanto en el entorno local como en Railway. El mandante se configura con `SAP_API_CLIENT` (por defecto `401`). Si SAP requiere autenticación Basic, configura `SAP_API_USERNAME` y `SAP_API_PASSWORD` como variables de entorno. La clase `services/SapApiService.php` centraliza las solicitudes JSON a SAP.

El formulario de inicio de sesión obtiene desde SAP la lista `TIPO=LOGIN_SOC` para mostrar sociedades (BUKRS y nombre). El usuario escribe directamente su número de proveedor (de 1 a 10 dígitos); no se consulta ni se muestra una lista de proveedores. Al enviar el formulario, el servidor valida el número con `TIPO=LOGIN_LISTA`, junto con la sociedad, y conserva el LIFNR original. El navegador nunca llama SAP.

Después del login, Inicio consulta el estado de cuenta mediante PATCH con `BUKRS`, `LIFNR`, `FECHAI` y `FECHAF`. SAP debe responder con `OPEN` y `CLOSE`; las partidas abiertas permiten adjuntar PDF, JPG o PNG de hasta 10 MB. Antes de usar la carga de facturas, aplica `database/migrations/20261020_create_proveedor_facturas_sap.sql` sobre la base de datos. Los archivos se guardan fuera de `public/` y solo se entregan al proveedor/sociedad autenticados.

La pantalla Mi perfil consulta `TIPO=PERFIL` mediante GET con `LIFNR` y `BUKRS`; los campos `LIFNR`, `NAME1`, `STCD1`, `STRAS`, `TELF1` y `SMTP_ADDR` se muestran en modo solo lectura. `LIFNR` debe venir en la respuesta para validar que el perfil pertenece a la sesión; los demás campos pueden venir vacíos o ausentes y mostrarán un texto de respaldo. La navegación ya no ofrece agregar empresas ni importar documentos desde SAP.

La clave inicial de un proveedor es su LIFNR (se acepta con o sin ceros iniciales). Si el proveedor aparece en SAP pero aún no tiene cuenta local, el primer acceso con esa clave crea su usuario y un RIF temporal `SAP-{LIFNR}`. Las claves nuevas o heredadas se guardan como hashes. Los proveedores pueden cambiar su contraseña desde la navegación lateral; después del cambio se usa únicamente la contraseña nueva.

## Despliegue de pruebas en Railway

El proyecto incluye un `Dockerfile` para ejecutar PHP 8.3 con Apache, `pdo_mysql` y cURL. Railway detecta el Dockerfile al desplegar el repositorio. El sitio conserva las rutas actuales; `.htaccess` bloquea el acceso web a la configuración, el código interno y los archivos privados. No se recomienda sustituir este contenedor por el servidor PHP integrado, que no aplica las reglas `.htaccess`.

### Base de datos

Agrega un servicio MySQL al proyecto Railway y vincúlalo con la aplicación. La aplicación requiere `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD` y `MYSQLDATABASE`; Railway puede proporcionarlas al vincular ambos servicios. `MYSQLPORT` también se usa si está disponible y, si falta, se toma el puerto MySQL estándar `3306`. En una base nueva, aplica una sola vez, en orden, los archivos de `database/migrations/`: primero `20261110_create_proveedores.sql` y luego `20261020_create_proveedor_facturas_sap.sql`. La tabla `proveedores` puede quedar vacía: el portal crea la cuenta al primer acceso válido confirmado por SAP.

### Variables y almacenamiento

Configura `SAP_API_URL` con la URL del servicio SAP que vaya a aceptar solicitudes desde Railway. Configura `SAP_API_CLIENT` y, si SAP los requiere, `SAP_API_USERNAME` y `SAP_API_PASSWORD`. La aplicación requiere `SAP_API_URL` en todos los entornos y mostrará un error de configuración si falta. No guardes estos valores en Git. La respuesta cruda de diagnóstico de SAP está deshabilitada en producción.

Agrega un volumen persistente de Railway montado en `/data`. El contenedor guarda allí las facturas, en `/data/facturas_sap`; esta ruta se configura mediante `INVOICE_STORAGE_PATH`. El volumen es necesario porque el disco ordinario del contenedor no es persistente entre despliegues.

### Primera prueba

1. Publica el código revisado en un repositorio privado y conéctalo a Railway.
2. Crea y vincula MySQL; aplica las dos migraciones anteriores.
3. Configura las variables SAP y confirma con el equipo de SAP/redes que el servicio sea accesible desde Railway. Usa HTTPS si SAP lo ofrece; una URL interna o restringida por firewall puede requerir autorización de red.
4. Crea el volumen `/data`, espera el despliegue y habilita el dominio HTTPS de Railway.
5. Prueba inicio de sesión, consulta SAP, cambio de contraseña, carga y descarga de una factura. Verifica también que una ruta `/storage/...` no entregue archivos.

Este entorno es para pruebas; antes de uso real deben acordarse respaldos, monitoreo y retención de datos.

## Despliegue en hosting compartido (PHP)

El proyecto también puede ejecutarse en un hosting compartido con PHP 8.2 o superior, Apache con `.htaccess`, las extensiones `pdo_mysql` y `curl`, y acceso de salida al servicio SAP. Este método no utiliza el `Dockerfile` ni `railway-start.sh`.

Sube el contenido del repositorio directamente a `public_html` (incluido el `.htaccess` y el `index.php` de la raíz; no subas `.git`). Crea la base MySQL y aplica las migraciones indicadas arriba.

Fuera de `public_html`, crea `facturacion-private.php` en la carpeta que lo contiene. Si el hosting coloca el directorio del subdominio dentro de `public_html`, el archivo privado debe quedar fuera del `public_html` de la web institucional. Debe devolver un arreglo PHP con las variables de producción, por ejemplo:

```php
<?php
return [
    'APP_ENV' => 'production',
    'MYSQLHOST' => 'localhost',
    'MYSQLPORT' => '3306',
    'MYSQLUSER' => 'usuario_completo',
    'MYSQLPASSWORD' => 'contraseña',
    'MYSQLDATABASE' => 'base_completa',
    'SAP_API_URL' => 'https://sap.example.com/endpoint',
    'SAP_API_CLIENT' => '401',
    'SAP_API_USERNAME' => 'usuario_sap',
    'SAP_API_PASSWORD' => 'contraseña_sap',
    'INVOICE_STORAGE_PATH' => __DIR__ . '/facturas_sap',
];
```

Reemplaza los valores de ejemplo por los datos reales del panel; omite las credenciales SAP si el servicio no las requiere. El archivo debe quedar fuera de `public_html`, no debe subirse a GitHub y debe tener permisos de lectura solo para la cuenta del hosting. Crea `facturas_sap` junto a ese archivo y permite que PHP escriba allí; así los adjuntos quedan fuera del directorio público. `config/runtime.php` carga esta configuración privada para la aplicación sin cambiar el uso de variables de entorno en Railway. No habilites depuración en producción.
