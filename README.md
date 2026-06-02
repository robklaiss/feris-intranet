# Industria Feris CRM

Aplicación interna en PHP 8.3 + SQLite para operar el flujo comercial completo de Industria Feris desde contrato hasta factura, manteniendo la facturación SIFEN todavía como placeholder local.

## Alcance actual

- Autenticación real por sesión con usuarios locales y roles `admin`, `operador`, `consulta`.
- Estados operativos unificados para contratos, órdenes, notas internas, remisiones y facturas:
  - `draft`
  - `confirmed`
  - `cancelled`
  - `closed`
- Acciones explícitas server-side:
  - `confirm`
  - `cancel`
  - `reopen`
  - `close`
  - `print`
  - `export csv`
- Auditoría operativa enriquecida:
  - usuario
  - acción
  - documento
  - id de documento
  - número de documento
  - estado anterior
  - estado nuevo
  - payload resumido
  - timestamp
- UX operativa reforzada:
  - badges de estado en listados y dashboard
  - filtros por búsqueda + estado en documentos
  - acciones visibles por fila
  - timeline de auditoría en detalle
  - alertas de saldo, cierre y consumo
- Preparación operativa para EC2:
  - guía de deploy
  - ejemplo Nginx + PHP-FPM
  - permisos de `storage/`
  - rotación básica de logs
  - backup y restore de SQLite

## Reglas operativas aplicadas

### Estados

Todos los documentos comerciales usan el mismo modelo:

- `draft`: editable, no consumible por el siguiente paso.
- `confirmed`: bloqueado para edición libre y habilitado para el siguiente paso.
- `cancelled`: no consumible y no reaperturable si ya tiene consumo aguas abajo.
- `closed`: documento terminado; puede reabrirse solo si no quedó consumido por documentos posteriores.

### Reglas de consumo

- Solo un documento `confirmed` puede alimentar el siguiente documento del flujo.
- Un documento `cancelled` o `closed` no aparece como fuente seleccionable.
- Si una orden ya fue usada por notas internas, no puede anularse ni reabrirse.
- Si una nota interna ya fue usada por remisiones, no puede anularse ni reabrirse.
- Si una remisión ya fue usada por facturas, no puede anularse ni reabrirse.
- Un contrato confirmado no puede editarse libremente; debe reabrirse a borrador, y si ya tiene órdenes asociadas no admite cambios destructivos.
- El cierre operativo exige saldo cero:
  - contrato: sin saldo por item contractual
  - orden: sin saldo pendiente de nota interna
  - nota: sin saldo pendiente de remisión
  - remisión: sin saldo pendiente de factura
  - factura: cierre manual disponible desde `confirmed`

## Roles

- `admin`: acceso total, incluyendo configuración.
- `operador`: crear, editar lo permitido, confirmar, anular, reabrir, cerrar, imprimir, exportar, gestionar clientes.
- `consulta`: solo ver, reportes e imprimir.

## Usuarios demo

Se crean desde la migración `004_add_operational_readiness.sql`.

- `admin` / `admin123`
- `operador` / `operador123`
- `consulta` / `consulta123`

## Estructura

```text
/Users/robinklaiss/Dev/feris-intranet
  app/
    Controllers/
    Repositories/
    Services/
    Support/
    Views/
  assets/
  bin/
    migrate.php
    seed.php
    test.php
    backup_sqlite.sh
    restore_sqlite.sh
    rotate_logs.sh
  config/
  database/
    migrations/
    seeds/
    data/
  public/
  storage/
    backups/
    exports/
    prints/
    logs/
```

## Migraciones

- `001_create_core_tables.sql`
- `002_add_form_detail_fields.sql`
- `003_add_operational_flow_support.sql`
- `004_add_operational_readiness.sql`
- `005_add_licitaciones_module.sql`
- `006_expand_audit_operational_tracking.sql`
- `007_add_client_dependencies_and_dncp.sql` a `018_link_finished_goods_to_remissions.sql` cubren el modulo de produccion textil.

## Produccion area textiles

La validacion tecnica y documentacion operativa del modulo de produccion textil esta en [docs/produccion-textil.md](/Users/robinklaiss/Dev/feris-intranet/docs/produccion-textil.md).

El documento fija el alcance por fases y deja fuera de alcance, por ahora, SIFEN, `LocalBillingAdapter`, `sifen-minisender-3` y cambios directos en servidor.

Accesos operativos principales:

- Produccion textil: `/production-orders`
- Inventario de insumos: `/raw-materials`
- Compras textiles: `/purchase-requisitions`, `/supplier-purchase-orders`, `/goods-receipts`
- Inventario terminado: `/finished-goods-inventory`
- Reportes textiles: `/reports?type=textile_production_by_status`

## Comandos exactos para correr localmente

### 1. Configurar entorno

```bash
cd /Users/robinklaiss/Dev/feris-intranet
cp .env.example .env
sed -i '' 's#^DB_DATABASE=.*#DB_DATABASE=/Users/robinklaiss/Dev/feris-intranet/database/data/app.sqlite#' .env
```

### 2. Migrar y cargar demo

```bash
cd /Users/robinklaiss/Dev/feris-intranet
php bin/migrate.php
php bin/seed.php
```

### 3. Levantar servidor local

```bash
cd /Users/robinklaiss/Dev/feris-intranet
php -S 127.0.0.1:8080 -t public public/router.php
```

Abrir:

```text
http://127.0.0.1:8080/login
```

### 4. Ejecutar pruebas

```bash
cd /Users/robinklaiss/Dev/feris-intranet
php bin/test.php
```

## Despliegue en Apache/cPanel por FTP

La app puede funcionar dentro de una subcarpeta pública como:

```text
https://proyectos.vinculo.com.py/industria-feris-crm
```

Subir la carpeta completa `industria-feris-crm` al hosting, incluyendo los archivos ocultos `.htaccess`.

Configurar `.env` en el servidor:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://proyectos.vinculo.com.py/industria-feris-crm
APP_BASE_URI=/industria-feris-crm
DB_DRIVER=sqlite
DB_DATABASE=/home2/coati23/public_html/proyectos/industria-feris-crm/database/data/app.sqlite
```

Requisitos del hosting:

- PHP 8.3 o superior.
- Extensiones `pdo_sqlite`, `sqlite3` y `mbstring`.
- Permisos de escritura para `storage/` y `database/data/`.
- Apache con `mod_rewrite` habilitado.

El archivo `index.php` de la raíz y los `.htaccess` enrutan las URLs hacia `public/index.php`. No usar `/public` en la URL final.

## Despliegue en EC2 Ubuntu

### 1. Instalar paquetes

```bash
sudo apt update
sudo apt install -y nginx php8.3 php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-cli unzip zip rsync
```

### 2. Copiar proyecto

```bash
sudo mkdir -p /var/www/industria-feris-crm
sudo rsync -av /Users/robinklaiss/Dev/feris-intranet/ /var/www/industria-feris-crm/
```

### 3. Configurar entorno

```bash
cd /var/www/industria-feris-crm
cp .env.example .env
sed -i 's#^APP_ENV=.*#APP_ENV=production#' .env
sed -i 's#^APP_DEBUG=.*#APP_DEBUG=false#' .env
sed -i 's#^APP_URL=.*#APP_URL=https://crm.tudominio.com#' .env
sed -i 's#^DB_DATABASE=.*#DB_DATABASE=/var/www/industria-feris-crm/database/data/app.sqlite#' .env
```

### 4. Permisos operativos

```bash
sudo mkdir -p /var/www/industria-feris-crm/storage/{logs,exports,prints,backups}
sudo mkdir -p /var/www/industria-feris-crm/database/data
sudo chown -R www-data:www-data /var/www/industria-feris-crm/storage /var/www/industria-feris-crm/database/data
sudo chmod -R 775 /var/www/industria-feris-crm/storage /var/www/industria-feris-crm/database/data
```

### 5. Inicializar base

```bash
cd /var/www/industria-feris-crm
sudo -u www-data php bin/migrate.php
sudo -u www-data php bin/seed.php
```

### 6. Configurar Nginx

Archivo recomendado: [deploy/nginx/industria-feris-crm.conf.example](/Users/robinklaiss/Dev/feris-intranet/deploy/nginx/industria-feris-crm.conf.example)

Copiar a `/etc/nginx/sites-available/industria-feris-crm`:

```nginx
server {
    listen 80;
    server_name crm.tudominio.com;

    root /var/www/industria-feris-crm/public;
    index index.php;

    access_log /var/log/nginx/industria-feris-crm.access.log;
    error_log /var/log/nginx/industria-feris-crm.error.log;

    location /assets/ {
        alias /var/www/industria-feris-crm/assets/;
        access_log off;
        expires 7d;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Activación:

```bash
sudo ln -sf /etc/nginx/sites-available/industria-feris-crm /etc/nginx/sites-enabled/industria-feris-crm
sudo nginx -t
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

### 7. Rotación básica de logs

La app sigue escribiendo errores PHP en `storage/logs/app.log`. Se incluye un script simple para rotarlo por tamaño.

Ejecución manual:

```bash
cd /var/www/industria-feris-crm
sudo -u www-data ./bin/rotate_logs.sh 10
```

Cron sugerido:

```bash
sudo crontab -e
```

```cron
0 * * * * cd /var/www/industria-feris-crm && sudo -u www-data ./bin/rotate_logs.sh 10 >/dev/null 2>&1
```

## Backup

### Backup timestamped sin compresión

```bash
cd /Users/robinklaiss/Dev/feris-intranet
./bin/backup_sqlite.sh
```

### Backup timestamped con zip

```bash
cd /Users/robinklaiss/Dev/feris-intranet
./bin/backup_sqlite.sh --zip
```

### Backup en ruta custom

```bash
cd /Users/robinklaiss/Dev/feris-intranet
./bin/backup_sqlite.sh --zip --dest /tmp/industria-feris-backups
```

Contenido del backup:

- `app.sqlite`
- `storage/exports/`
- `storage/prints/`
- `manifest.txt`

Cron sugerido en EC2:

```cron
0 2 * * * cd /var/www/industria-feris-crm && sudo -u www-data ./bin/backup_sqlite.sh --zip >/dev/null 2>&1
```

## Restore

### Restaurar desde directorio

```bash
cd /Users/robinklaiss/Dev/feris-intranet
./bin/restore_sqlite.sh /Users/robinklaiss/Dev/feris-intranet/storage/backups/20260308_235959
```

### Restaurar desde zip

```bash
cd /Users/robinklaiss/Dev/feris-intranet
./bin/restore_sqlite.sh /Users/robinklaiss/Dev/feris-intranet/storage/backups/20260308_235959.zip
```

### Restore en EC2

```bash
cd /var/www/industria-feris-crm
sudo -u www-data ./bin/restore_sqlite.sh /var/www/industria-feris-crm/storage/backups/20260308_235959.zip
```

Comportamiento del restore:

- repone `app.sqlite`
- guarda una copia previa como `app.sqlite.before_restore_<timestamp>`
- mueve `storage/exports` y `storage/prints` actuales a `*.before_restore_<timestamp>`
- restaura exports y prints desde el backup

## Auditoría y reportes

La pantalla de reportes y los detalles de documento ahora exponen el `audit_log` operativo con:

- usuario
- documento
- acción
- transición de estado
- payload resumido

## Estrategia SIFEN actual

- No se integra todavía `sifen-minisender-3`.
- La factura sigue usando `LocalBillingAdapter`.
- El envío real a SIFEN permanece fuera de este repo.

## Pendientes reales para futura integración con `sifen-minisender-3`

1. Implementar un bridge real en `integrations/billing_adapter/PythonSifenBridge.php`.
2. Definir payload de salida estable entre PHP y Python:
   - datos fiscales validados
   - XML firmado
   - CDC o identificador equivalente
   - respuesta normalizada de SET
3. Agregar persistencia operativa para:
   - estado SIFEN
   - XML enviado
   - track/lote
   - intentos y reintentos
   - errores normalizados
4. Endurecer reglas fiscales previas al envío:
   - RUC y razón social
   - condición de venta
   - totales/impuestos
   - establecimiento/punto/timbrado
5. Definir cola o proceso asíncrono para envío y reconciliación de respuesta.
