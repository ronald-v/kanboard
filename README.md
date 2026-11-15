# Projecta · rvSoftware

Aplicación interna de gestión de proyectos basada en [Kanboard](https://kanboard.org/), con registro de tiempo, clientes y bolsas de horas. Las funcionalidades propias están en `plugins/RvSoftware`.

## Características

- Proyectos, tableros Kanban, tareas y subtareas, con usuarios y permisos por proyecto.
- Registro manual de tiempo y temporizador, notas y correcciones auditadas.
- Revisión, clasificación y aprobación de horas facturables.
- Clientes y bolsas de horas por proyecto, ampliaciones, consumos, ajustes e historial de saldo.
- Reportes por período y exportación CSV.
- Acceso con segundo factor TOTP y códigos de recuperación.
- API JSON-RPC con credenciales individuales y adaptador MCP con OAuth/PKCE para proyectos, tareas y subtareas. La conexión pública de MCP sigue pendiente de validación.

## Tecnologías y requisitos

| Componente | Tecnología |
| --- | --- |
| Aplicación | PHP, plantillas HTML renderizadas en servidor |
| Interfaz | CSS, JavaScript y jQuery |
| Persistencia | PDO/PicoDB; SQLite y MySQL/MariaDB para el plugin |
| Dependencias PHP | Composer y archivos `composer.lock` |
| Herramientas de desarrollo | PowerShell, Python 3 y PHPUnit |

Kanboard declara PHP **8.1+**; MCP y las dependencias de pruebas requieren **8.3+**. El entorno local de compatibilidad se ha probado con PHP **8.5.12** y MariaDB **11.8.9**.

Necesitas PHP CLI en el `PATH`, con `gd`, `mbstring`, `openssl`, `dom`, `SimpleXML`, `xml` y las demás extensiones de [composer.json](composer.json). Habilita `pdo_mysql` para MariaDB o `pdo_sqlite` para SQLite; MCP requiere además Sodium. Para preparar las pruebas se necesitan Composer y las extensiones ZIP y LDAP. Git y Python 3 se usan para generar el paquete.

## Ejecutar en local · Windows

Ejecuta los comandos desde la raíz del repositorio en PowerShell.

### Con MariaDB

1. Crea una base de desarrollo y un usuario con permisos sobre ella. Si aún no tienes configuración local, copia el ejemplo:

   ```powershell
   if (-not (Test-Path data/config.php)) {
       Copy-Item config.mariadb.example.php data/config.php
   }
   ```

2. Edita `data/config.php` con host, puerto, base, usuario y contraseña. Conserva la inclusión de `config/projecta.php`. Para una instalación nueva, genera una clave MFA una sola vez:

   ```powershell
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

   Guarda el resultado en la configuración privada como `define('PROJECTA_KEY', 'CLAVE_GENERADA');` o en la variable de entorno `PROJECTA_KEY`. Conserva y respalda esa misma clave para los siguientes arranques.

3. Inicia el servidor:

   ```powershell
   powershell -NoProfile -File harness/scripts/Start-LocalServer.ps1
   ```

4. Abre [http://127.0.0.1:18001](http://127.0.0.1:18001). En una base nueva, entra con `admin` / `admin`, cambia la contraseña y configura el autenticador. Una base existente conserva sus credenciales.

Detén el servidor con `Ctrl+C`. Para otro puerto, agrega `-Port 18002`. El primer acceso puede crear o migrar tablas del núcleo y del plugin; respalda una base existente antes de conectarla. El servidor PHP integrado es para desarrollo local.

`data/config.php` está ignorado por Git. Kanboard no carga `.env` automáticamente. Las variables SMTP y la configuración de acceso se describen en [operación de Projecta](plugins/RvSoftware/PROJECTA.md).

### Con SQLite y datos de prueba

En una **copia separada sin `config.php` ni `data/config.php`**, prepara y arranca el entorno aislado:

```powershell
powershell -NoProfile -File harness/scripts/Initialize-TestEnvironment.ps1
powershell -NoProfile -File harness/scripts/Start-IsolatedServer.ps1 -Plugin
```

Abre la misma URL local. Este perfil guarda datos en `.runtime/data`, copia el plugin a `.runtime/plugins-enabled` y activa el modo de pruebas del plugin; úsalo con datos sintéticos. Sin `-Plugin` inicia Kanboard nativo. Si el inicializador no encuentra Composer, indica la ruta de `composer.phar` mediante `-ComposerPhar`.

El mensaje `Refusing to boot with an unreviewed config` indica que hay una configuración local: usa el lanzador MariaDB o una copia separada para SQLite. Más perfiles y requisitos en [pruebas y entorno](harness/context/testing.md).

## Compilar y generar el paquete

La aplicación se ejecuta directamente con PHP y los assets minificados incluidos. No requiere Node.js ni una compilación previa para arrancar.

### Regenerar CSS y JavaScript

Si modificas las fuentes de `assets/css/src` o `assets/js`, ejecuta desde la raíz de una **copia con base de desarrollo aislada**:

```powershell
php cli css
php cli js
```

Estos comandos regeneran los archivos minificados de `assets/css` y `assets/js`. La CLI arranca la aplicación y puede ejecutar migraciones de la base configurada. Los estilos propios de `plugins/RvSoftware/Assets` se sirven directamente.

### Paso a paso: obtener el ZIP para cargar al hosting

1. Abre PowerShell y entra en el proyecto:

   ```powershell
   Set-Location C:\Proyectos\kanboard
   ```

2. Instala las dependencias de MCP. Este comando localiza Composer y activa Sodium solo para ese proceso, sin modificar `php.ini`:

   ```powershell
   $composerPhar = Join-Path (Split-Path (Get-Command composer).Source) 'composer.phar'
   php -d extension=sodium $composerPhar install --working-dir=mcp --no-dev --prefer-dist --no-interaction
   ```

   Espera a que termine sin errores. Si falla, corrige el error antes de continuar.

3. Ejecuta la verificación de seguridad aislada y genera el paquete con el código actual. Los requisitos y comandos están en [operación de seguridad](plugins/RvSoftware/PROJECTA.md#seguridad-antes-de-empaquetar). La evidencia debe corresponder a estos archivos y tener como máximo 24 horas:

   ```powershell
   python harness/scripts/Build-Release.py --working-tree --security-evidence .runtime/f020/security-evidence.json
   ```

   Debe terminar con `PASS artifact:` seguido de la ruta del ZIP y su SHA256. El script comprueba el contenido y excluye configuración privada, datos locales, pruebas y harness. Los assets minificados ya están incluidos; no necesitas ejecutar los comandos CSS/JS anteriores para empaquetar esta versión.

   Si la evidencia no coincide, el error enumera los archivos agregados, retirados o modificados. Los cambios reales requieren repetir la verificación y sellar evidencia nueva. El empaquetado estabiliza únicamente la referencia Git que Composer genera para el paquete raíz MCP; las dependencias conservan su validación completa.

4. Abre la carpeta de salida:

   ```powershell
   explorer.exe .runtime\releases
   ```

   El archivo que cargarás es el **`.zip` indicado por `PASS artifact:`**, con nombre `projecta-0.2.0-preview-<identificador>.zip`. El archivo `.zip.sha256` contiene su hash; consérvalo para verificar la copia. Si hay varios ZIP, usa la ruta mostrada al terminar el paso 3.

5. Al extraerlo en el hosting, encontrarás una carpeta `kanboard/`. Si la aplicación debe abrir en la raíz del dominio, coloca **el contenido de `kanboard/`** en el directorio público del sitio, de modo que `index.php` quede allí.

El ZIP incluye las dependencias PHP: no necesitas Composer ni Node.js en el hosting. `data/config.php` y la clave MFA quedan fuera del paquete; configura esos valores en el servidor. La configuración y verificación del destino están en [operación de Projecta](plugins/RvSoftware/PROJECTA.md) y [MCP](mcp/README.md). El paquete se identifica como preview de desarrollo; su generación no acredita la validación del hosting.

## Pruebas

En la copia aislada SQLite, después de preparar el entorno:

```powershell
# Pruebas del plugin
powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -Plugin -Filter RvSoftware

# Suite completa con el plugin habilitado
powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -Plugin
```

Los perfiles MariaDB y las pruebas HTTP/API/MCP se describen en [pruebas y entorno](harness/context/testing.md). Las suites usan datos sintéticos en entornos separados de la base de trabajo.

## Estructura y documentación

| Ruta | Contenido |
| --- | --- |
| `app/` | Núcleo PHP de Kanboard |
| `plugins/RvSoftware/` | Funcionalidades y estilos de Projecta |
| `assets/` | CSS, JavaScript, fuentes e imágenes del núcleo |
| `config/projecta.php` | Configuración pública de Projecta |
| `data/` | Configuración privada, archivos y datos locales |
| `mcp/` | Adaptador MCP y OAuth; [guía de configuración](mcp/README.md) |
| `tests/` | Pruebas automatizadas |
| `harness/` | [Guía de desarrollo](harness/README.md), estado y evidencias |

## Licencia

Distribuido bajo [licencia MIT](LICENSE). Basado en Kanboard, creado por Frédéric Guillot y sus colaboradores. Consulta la [documentación oficial](https://docs.kanboard.org/) y el [registro de cambios de Kanboard](ChangeLog).
