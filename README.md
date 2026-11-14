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
