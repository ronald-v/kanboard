# Rendimiento: medir antes de optimizar

DEC-026 fijó para F-013 p95 de lectura ≤800 ms y escritura ≤1500 ms, con 10
trabajadores, 100 proyectos y 10000 tareas sintéticas. La consulta N+1 de liderazgo
se redujo a una consulta conjunta para roles nativos, con resolución nativa de
casos personalizados. [Mediciones y límites](../evidence/2026-11-14-projecta-integracion.md).
No se midió la red/base remota del usuario ni Hostinger. El protocolo siguiente
se mantiene para cambios futuros; no atribuye cualquier lentitud a la misma causa.
Para una feature autorizada registrar ruta/operación, runtime/SAPI, motor, proximidad
de DB, tamaño de fixture, rol, plugin y caché. Aislar datos antes de instrumentar.
No cambiar timeouts para declarar una mejora de velocidad.

1. Reproducir con sintéticos y fijar muestras/carga antes del cambio. Separar
   primera carga, calentamiento y repetición. Registrar cantidad de muestras,
   mediana y percentil si la muestra lo permite; no presentar una solicitud como p95.
2. Separar navegador/red/TTFB/PHP/SQL y servicios externos. Medir consultas y N+1,
   tiempos de conexión/latencia DB, locks/transacciones, sesión, hooks por petición,
   notificaciones síncronas, DNS/SMTP y assets según evidencia. Un síntoma no es causa.
3. Revisar planes EXPLAIN e índices con db en copia sintética representativa;
   el análisis que ejecuta consultas requiere conocer sus efectos. No capturar
   valores personales, credenciales o SQL con datos reales.
4. Corregir el cuello demostrado con el menor cambio. Medir coste de lecturas y
   escrituras de índices. Caché requiere claves por usuario/permisos e invalidación;
   no permitir exposición cruzada. No introducir Redis/colas/SPA sin necesidad probada.
5. Comparar antes/después en las mismas condiciones, con errores y corrección de
   resultados. Conservar muestra/tamaño y métricas, regresión funcional, seguridad,
   concurrencia y recuperación pertinentes. Si no hay mejora, no declararla.

Los umbrales de aceptación deben acordarse antes de modificar; no elegirlos después
para aprobar. No ejecutar carga sobre el hosting o la MariaDB del usuario por defecto.

## F-019 · Protocolo local y aceptación pendiente

DEC-029 autoriza reducir al menos 50 % mediana y p95 **por flujo**, desde clic
hasta quedar utilizable/confirmado. No sustituirlo por promedio global ni por el
objetivo absoluto histórico de F-013. Un resultado de loopback no acredita la
conexión remota. [Evidencia](../evidence/2026-11-14-f019-performance.md).

`F019-Environment.py` conserva una copia `before` sin configuración privada y
prepara `after` en `.runtime/f019`. La base anterior fue capturada con árbol de
producto limpio en `60f0f0ee99687b376d9ff117a3f19f9f9b4df78e`; sus hashes están en
`.runtime/f019/baseline-manifest.json`. No regenerar `before` a partir de fuentes
ya optimizadas. `prepare --variant before` rechaza sobrescribir una copia presente.

El perfil sintético fija loopback 13318/kanboard_compat_http, datadir reservado,
100 proyectos/10000 tareas y diez identidades. `seed --reset` recrea **solo** ese
schema tras comprobar identidad; `snapshot`/`restore` también verifican el datadir.
No cambiar esas guardas ni reutilizar credenciales reales. Un solo PHP sirve a uno
o diez clientes. Ejecutar mediciones en serie, sin suites simultáneas:

```powershell
python harness/scripts/F019-Environment.py prepare --variant after
python harness/scripts/F019-Environment.py seed --variant before --reset
python harness/scripts/F019-Environment.py snapshot
python harness/scripts/F019-Environment.py serve --variant before
# Otra consola: restaurar snapshot antes de CADA lote; luego repetir con after.
python harness/scripts/F019-Environment.py restore
python harness/scripts/F019-Benchmark.py --label before-http-1 --clients 1 --samples 100
python harness/scripts/F019-Environment.py restore
python harness/scripts/F019-Browser.py --label before-browser-1 --clients 1 --samples 100
# Repetir ambos clientes con 10; detener solo el servidor propio y servir after.
python harness/scripts/F019-Compare.py
```

HTTP mide apertura/guardado/redirecciones; Chromium distingue navegación, apertura
modal AJAX y submit nativo con controles listos/fuentes/dos frames. Dos iteraciones
de calentamiento por cliente/flujo quedan fuera de mediana/p95. No representan un
benchmark independiente de arranque frío. Playwright/Chromium son herramientas
de prueba bajo `.runtime/f019`, no dependencias ni servicios del producto.
`mysql_questions` procede de SHOW SESSION STATUS e incluye SQL PDO directo;
no equivale al tiempo SQL ni cuenta todos los intercambios del protocolo TCP.

### Observación voluntaria de uso normal

Sin arrancar pruebas contra datos reales: `Measure-LocalDatabase.php` carga solo
definiciones y funciones; usa una transacción de lectura y comprueba versiones.
No carga `common.php`. El perfilador rehúsa arrancar si hay migración pendiente.
No modifica DEBUG ni la configuración privada.

```powershell
powershell -NoProfile -File harness/scripts/Start-LocalServer.ps1 -Port 18002 -Profile -ProfileVariant Before
# Otra consola; tú operas el navegador y lo cierras al terminar:
python harness/scripts/F019-ObserveLocal.py --variant Before
# Detener ese perfilador y repetir ambos comandos con Current.
```

El perfil Before usa código conservado y carga las definiciones privadas solo
en memoria; datos/adjuntos siguen en su ubicación original. No copia configuración
ni datos. No iniciar ambos perfiles a la vez ni usar la base real para suites.
El navegador observador abre la página inicial y registra acciones que tú realices;
no introduce credenciales, pulsa botones ni genera CRUD. Sus métricas y las del
servidor quedan fuera del document root, en TEMP/Projecta-F019. No guardan valores,
IDs, cuerpos, SQL, cookies o tokens. La observación requiere URLs nativas con
controller/action; no declara medidos enlaces de rutas amigables sin esos parámetros.
El p95 real permanece nulo con menos de 100 muestras por operación. Comparar los
pares Before/Current con el mismo rol, acciones, volumen y condiciones de red;
no confundir PHP/Server-Timing con clic-a-listo. Las sesiones de observación no
autorizan carga automática, migraciones ni despliegue.

El perfilador real no añade consultas SQL por petición. Su contador `pico_queries`
es parcial y no incluye PDO directo; no usarlo para afirmar reducción total de SQL.
Los contadores completos se recogen solo en el perfil sintético. Para comparar los
resúmenes del observador sin reducir requisitos:

```powershell
python harness/scripts/F019-Compare.py --normal-before RUTA_BEFORE.summary.json --normal-after RUTA_CURRENT.summary.json
```

Este modo devuelve aceptación solo si los 26 flujos tienen >=100 muestras por
variante, cero errores y ambas reducciones >=50 %. Ausencias permanecen pendientes.

La referencia con diez Chromium agotó el deadline inicial de 30 s en dashboard.
Los lotes browser de diez clientes usan `--timeout-ms 120000` **en ambas variantes**
para registrar la demora real. Es un límite de la herramienta, no un cambio de
timeouts de Kanboard ni una mejora de velocidad. El comparador exige deadlines
iguales y compara las duraciones observadas.

Verificación de herramientas: `python harness/scripts/Test-F019Tools.py` usa solo
la copia after, la base sintética y HTML interceptado, nunca la base privada.
