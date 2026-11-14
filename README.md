# Kanboard rvSoftware

Aplicación interna de gestión de proyectos basada en [Kanboard](https://kanboard.org/). Conserva sus tableros, tareas, usuarios y permisos, y añade funciones para registrar y revisar tiempo, administrar clientes y bolsas de horas, conciliar consumos y consultar reportes y exportaciones. Las extensiones propias están en `plugins/RvSoftware`.

## Ejecutar localmente en Windows

Desde PowerShell, en la raíz del repositorio, inicia el entorno aislado con el plugin de rvSoftware:

```powershell
powershell -NoProfile -File harness/scripts/Start-IsolatedServer.ps1 -Plugin
```

Abre [http://127.0.0.1:18001](http://127.0.0.1:18001). Detén el servidor con `Ctrl+C`. Para usar otro puerto, agrega, por ejemplo, `-Port 18002`.

El servidor usa SQLite y archivos bajo `.runtime/data`; al arrancar puede crear o migrar esa base y el esquema del plugin. Usa únicamente datos de prueba. En una base nueva, el acceso inicial es `admin` / `admin`; una base ya existente conserva sus propias credenciales y datos. El script copia el plugin a `.runtime/plugins-enabled`. Sin `-Plugin` arranca Kanboard nativo.

### Requisitos y preparación

- PHP CLI 8.1 o superior en el PATH, con las extensiones declaradas en [composer.json](composer.json), además del controlador PDO SQLite. El entorno de pruebas también utiliza ZIP y LDAP.
- Composer para preparar las dependencias de desarrollo. En Windows deben estar disponibles los archivos de las extensiones PHP, incluso si el script las habilita solo para su proceso.
- Si el entorno aislado aún no está preparado, ejecuta una vez:

```powershell
powershell -NoProfile -File harness/scripts/Initialize-TestEnvironment.ps1
```

Este comando instala las dependencias fijadas en `composer.lock` en `.runtime/vendor`. Si no encuentra `composer.phar`, indica su ruta mediante `-ComposerPhar`. El servidor se detiene si encuentra `config.php`, `data/config.php` o `.runtime/data/config.php`, para evitar iniciar con una configuración no revisada. Consulta [pruebas y entorno](harness/context/testing.md) para detalles y solución de problemas.

## Conectar MariaDB

Para ejecutar **tu configuración de MariaDB** guardada en `data/config.php`, usa:

```powershell
powershell -NoProfile -File harness/scripts/Start-LocalServer.ps1
```

Abre http://127.0.0.1:18001. Este lanzador carga tu archivo, habilita las extensiones PHP necesarias por proceso y conserva `DEBUG` y `PLUGIN_INSTALLER` como los definiste. No necesita `-Plugin`: Kanboard carga el plugin desde `plugins/RvSoftware`. El primer acceso puede crear o migrar las tablas de la base configurada. El servidor queda limitado a loopback; deténlo con `Ctrl+C`.

`Start-IsolatedServer.ps1 -Plugin` sigue siendo exclusivamente para SQLite sintético y rechaza configuraciones locales. Si aparece `Refusing to boot with an unreviewed config`, cambia al comando MariaDB anterior; activar `DEBUG` no elimina ese bloqueo.

Kanboard no lee un archivo `.env` por sí solo. Puedes configurar variables en el entorno del proceso PHP o utilizar el archivo nativo `data/config.php`. Para esta segunda opción, copia [config.mariadb.example.php](config.mariadb.example.php) a `data/config.php` y reemplaza el host, puerto, nombre, usuario y contraseña por los de la base creada en Hostinger. MariaDB usa `DB_DRIVER=mysql` y requiere la extensión PHP `pdo_mysql`. Los valores del ejemplo son marcadores, no credenciales reales.

```powershell
Copy-Item config.mariadb.example.php data/config.php
```

Edita la copia sin subirla a Git: `data/config.php` y `config.php` están ignorados. El directorio `data` y las copias de seguridad deben estar protegidos frente a acceso web; verifica las reglas del servidor efectivo. Como alternativa, configura `DB_DRIVER`, `DB_HOSTNAME`, `DB_PORT`, `DB_NAME`, `DB_USERNAME` y `DB_PASSWORD` en el entorno PHP. Si existe `DATABASE_URL`, Kanboard la procesa antes de los archivos de configuración: evita combinar ambos métodos. El servidor aislado de desarrollo rechaza archivos de configuración locales para proteger sus datos de prueba; usa un entorno separado para MariaDB.

## Preparar el despliegue en Hostinger

1. Crea una base MariaDB y su usuario en Hostinger y asigna al usuario permisos sobre esa base. Consulta allí el host, nombre y usuario reales; no supongas que coinciden con los marcadores del ejemplo.
2. Comprueba en el sitio y en una instalación de prueba que PHP 8.5 y las extensiones requeridas por `app/check_setup.php`, especialmente `pdo_mysql`, GD y mbstring, estén activos. La cabecera pública observada anteriormente indicaba PHP 8.3.36; confirma la versión efectiva de la aplicación.
3. Desde una copia con Git, genera el paquete con `python harness/scripts/Build-Release.py`. El ZIP y su archivo `.sha256` quedan en `.runtime/releases`; comprueba el hash antes de cargarlo. El paquete incluye el plugin y las dependencias de ejecución, y excluye el harness y los datos locales.
4. En un sitio de staging, extrae el contenido de `kanboard/` en el directorio público asignado. Crea `data/config.php` a partir del ejemplo y configura la base de staging. Asegura que `data`, archivos subidos y caché sean escribibles por PHP y que los datos, configuración y respaldos no sean accesibles por HTTP.
5. Respalda juntos código, base y archivos, y verifica la restauración antes de actualizar una instalación existente. El primer arranque puede ejecutar migraciones del núcleo y del plugin; usa una base aislada para la prueba y evita arranques simultáneos durante la migración.
6. Abre el sitio de staging, cambia la contraseña inicial de una base nueva y verifica login, proyectos, registro y aprobación de horas, bolsas, reportes y adjuntos. Comprueba también las URL protegidas y la restauración en el servidor real.

Para los requisitos de PHP, protección del servidor y recuperación, consulta [instalación y recuperación](plugins/RvSoftware/INSTALL.md). Las pruebas locales con PHP 8.5 y MariaDB 11.8.9 no sustituyen la validación del hosting. La publicación productiva requiere autorización y recuperación verificadas; F-012 continúa bloqueada hasta completar las comprobaciones del destino.

## Desarrollo y validación

- `app/`: aplicación Kanboard; `plugins/RvSoftware/`: funcionalidades propias.
- `harness/`: contexto, decisiones, evidencias y herramientas de desarrollo. [Estado de las funcionalidades](harness/feature-list.json) y [guía del harness](harness/README.md).
- `tests/`: pruebas de Kanboard. Para ejecutar las pruebas aisladas de SQLite:

```powershell
powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -Plugin
```

El comando requiere haber preparado el entorno. Para validar la estructura del harness:

```powershell
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
```

La validación del harness es estática y no sustituye las pruebas de la aplicación. La validación integrada y el despliegue siguen pendientes de comprobación en el entorno de destino; consulta el [estado canónico](harness/feature-list.json).

## Referencias y licencia

- [Documentación oficial de Kanboard](https://docs.kanboard.org/) y [registro de cambios](ChangeLog).
- Kanboard fue creado por Frédéric Guillot y sus [colaboradores](https://github.com/kanboard/kanboard/graphs/contributors). La aplicación original se encuentra en modo de mantenimiento.
- Distribuido bajo la [licencia MIT](LICENSE).
