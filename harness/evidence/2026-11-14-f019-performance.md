# F-019 · Rendimiento de navegación y CRUD

Actualizaci?n: 2026-11-14T14:50:37-05:00. Mismo ejecutor; sin delegación ni
revisión independiente. Autorización: DEC-029 y plan aprobado por el usuario.
Objetivo sin modificar: >=50 % de reducción de mediana y p95 por flujo.

## Diagnóstico comprobado

Preflight de solo lectura sobre configuración local: conexión 345.67 ms;
SELECT 1: 116.94, 117.38 y 124.75 ms. Son tres muestras diagnósticas, **no p95**.
Esquema nativo 139 y plugin 9, iguales al código. No se cargó common.php ni se
ejecutaron migraciones, carga o CRUD de prueba en esa base. No se copiaron secretos.

F-013 no mide esa red/base: sus resultados históricos no se reutilizan como prueba
de mejora. La inspección encontró transacción/FK/REPLACE de plugin en cada petición
aun sin migración, además de consultas por proyecto/usuario en bucles.

## Cambio mínimo y recuperación

- SchemaHandler conserva consulta de versión fresca y evita transacción/FK/escritura
  si la versión ya es igual o superior; no degrada un esquema más nuevo.
- Plugin agrupa permisos para opciones/reportes, membresías/listas de proyectos,
  acciones y parámetros, grupos de usuarios y movimientos de bolsas. El listado
  de tareas calcula visibilidad del tiempo en SQL, sin consultas por fila.
- Permisos directos conservan precedencia; empates con roles personalizados usan
  resolución nativa. No se cachean permisos entre peticiones. Datos de grupos se
  reutilizan solo durante el render del listado y se liberan incluso ante fallo.
- Dashboard nativo: la referencia de 100 proyectos produjo 737 consultas; con diez
  Chromium alcanzó p95 44880.18 ms (100 muestras). El plugin carga top 15/totales
  por proyecto y relaciones en conjunto, preservando contenido, etiquetas y
  subtareas. Usa consultas de ventana en motores compatibles y conserva la ruta
  nativa para motores anteriores, hooks de query y enlaces de paginación/orden.
  No elimina proyectos ni introduce paginación nueva.
- Sin cambios de esquema, vendor/libs, infraestructura, destino DB, factores de
  contraseña/MFA, reglas comerciales ni timeouts para aparentar mejora.

La excepción mínima de núcleo se limita a SchemaHandler. Alternativa descartada:
el plugin se inicializa después de que Loader ya ejecutó su manejador de esquema;
un override en initialize no elimina ese trabajo previo. No deshabilitar todas
las migraciones para evitarlo. Recuperación: revertir solo los cambios propios
comparados por hash/diff; no reset/clean, no reparación SQL ni rollback de datos.
Base conservada en `.runtime/f019/base`, copia before y manifest de hashes;
commit inicial `60f0f0ee99687b376d9ff117a3f19f9f9b4df78e`. Sin despliegue ni paquete nuevo.

## Ejecución en curso

Destino sintético: PHP 8.5.12, MariaDB 11.8.9-log, 127.0.0.1:13318,
kanboard_compat_http, copia sin configuración privada. Un servidor PHP y uno/diez
clientes; 100 proyectos/10000 tareas iniciales; snapshot idéntico entre lotes.
26 flujos (18 navegación/apertura + 8 altas/ediciones). Dos calentamientos por
cliente/flujo, >=100 muestras por flujo y escenario. Escrituras y crecimiento de
fixture se repiten en el mismo orden. No prueba hosting ni arranque frío.

Los JSON locales de antes/después se comparan mediante F019-Compare.py. Pendiente
anexar los lotes finales y verificaciones. Ninguna medición planeada figura aprobada.
El primer lote HTTP before-1 coincidió parcialmente con suites preparatorias;
debe repetirse sin suites simultáneas antes de usarlo como comparación final.

Herramientas: perfilador opt-in, preflight y observador pasivo para actividad normal;
no iniciados contra la base actual. Procedimiento y límites en context/performance.md.

## Fallos de preparación observados

- Lanzador de compatibilidad previo rechaza configuración privada presente;
  se conservó esa guarda y se usó perfil nuevo dedicado sin cargar esos archivos.
- Preflight inicial carecía de functions.php: falló antes de conectar; corregido.
- Parser de formularios HTTP elegía búsqueda de cabecera; ahora exige CSRF.
  Identificador de proyecto de fixture se corrigió a alfanumérico y único.
- Benchmark browser intentaba navegar a fragmentos de modal como páginas enteras;
  corregido para usar el modal nativo. Pasada de 26 flujos completa sin errores JS.
- Suite MariaDB detectó servicio de permisos congelado en upgrade histórico;
  definición stateless sustituida explícitamente. Revalidación final pendiente.

La aceptación real está pendiente de muestras emparejadas de uso normal. No afirmar
que toda la aplicación ya es un 50 % más rápida ni marcar F-019 validated por tests
de loopback, reducción de consultas o sintaxis.

## Regresión ejecutada

- Plugin + SchemaHandler: 63 tests / 342 aserciones, exit 0 tanto SQLite como
  MariaDB. Ajuste posterior para reutilizar colección del listado verificado con
  1 test / 7 aserciones por motor, exit 0, sin repetir toda la suite.
- Perfil nativo explícito (sin plugin ni auto_prepend Projecta): 88 tests /
  904 aserciones, exit 0. Incluye SchemaHandler, DashboardPagination, acciones,
  roles de proyecto, UserHelper, TaskFinder, ConfigModel, TotpAuth y permisos.
- El intento amplio de 1302 tests se lanzó inicialmente con perfil Projecta y
  se interrumpió tras fallos de expectativas nativas; no se declara suite
  completa aprobada. La configuración se corrigió con --native. Su salida parcial
  permanece en .runtime/f019/tests-native-sqlite-output.txt.
- El estado F-001..F-018 sigue idéntico a la base conservada. Harness estático
  inicial pasa con 19 features/31 casos; comprobaciones finales aún pendientes.

## Resultado final de los lotes

Actualizaci?n: 2026-11-14T17:01:11-05:00.

Cuatro pares completos: HTTP y Chromium, uno y diez clientes, un servidor PHP,
100 muestras por cada uno de los 26 flujos. El before-http-1 se repiti? sin suites
simult?neas y reemplaz? el lote preliminar. Snapshot restaurado antes de cada lote.
No hay muestras emparejadas de uso normal sobre la conexi?n remota.

| Lote | Flujos con >=50 % en mediana Y p95 |
|---|---:|
| http-1 | 1/26 |
| http-10 | 1/26 |
| browser-1 | 0/26 |
| browser-10 | 0/26 |

**Resultado: objetivo integral NO cumplido.** La reducci?n de consultas no demuestra
reducci?n equivalente de clic-a-listo. Existen flujos sin mejora y regresiones de
tiempo en loopback. No se reduce el umbral ni se considera validada F-019.

| Flujo | Consultas antes/despu?s (HTTP 1) | Mediana browser 1 antes/despu?s ms | p95 browser 1 antes/despu?s ms |
|---|---:|---:|---:|
| home | 25 / 14 | 199.19 / 214.75 | 218.00 / 240.55 |
| projects | 23 / 12 | 183.50 / 198.40 | 215.32 / 215.33 |
| time | 123 / 15 | 214.54 / 198.23 | 232.45 / 217.36 |
| bags | 35 / 23 | 198.86 / 199.55 | 218.61 / 217.40 |
| reports | 426 / 20 | 263.53 / 199.08 | 281.62 / 232.06 |
| dashboard | 737 / 231 | 1834.81 / 1497.18 | 2586.29 / 1862.01 |
| board | 33 / 24 | 337.44 / 348.10 | 415.33 / 411.62 |
| task | 39 / 30 | 215.32 / 216.20 | 264.98 / 249.01 |
| users | 33 / 14 | 200.66 / 199.73 | 968.89 / 222.75 |
| administration | 21 / 12 | 198.03 / 199.29 | 230.55 / 236.97 |
| project_create_form | 22 / 12 | 140.55 / 132.60 | 165.37 / 164.15 |
| project_edit_form | 27 / 18 | 106.86 / 131.73 | 166.08 / 149.36 |
| task_create_form | 26 / 18 | 146.04 / 146.94 | 172.05 / 164.45 |
| task_edit_form | 28 / 20 | 146.00 / 131.54 | 164.79 / 162.77 |
| subtask_create_form | 22 / 14 | 146.49 / 131.99 | 164.77 / 165.02 |
| subtask_edit_form | 25 / 17 | 98.90 / 132.96 | 165.38 / 164.37 |
| user_create_form | 18 / 10 | 131.76 / 132.21 | 166.54 / 166.68 |
| user_edit_form | 22 / 13 | 131.38 / 131.64 | 165.15 / 165.43 |
| create_project | 26 / 17 | 212.45 / 213.76 | 249.22 / 238.64 |
| edit_project | 27 / 18 | 204.66 / 217.85 | 233.71 / 252.37 |
| create_task | 33 / 24 | 221.88 / 234.99 | 261.54 / 257.32 |
| edit_task | 39 / 30 | 210.93 / 226.70 | 243.74 / 245.38 |
| create_subtask | 39 / 30 | 219.54 / 231.17 | 250.98 / 277.18 |
| edit_subtask | 39 / 30 | 234.64 / 234.28 | 270.85 / 259.50 |
| create_user | 22 / 13 | 442.62 / 451.44 | 486.92 / 473.58 |
| edit_user | 22 / 13 | 232.27 / 233.11 | 274.04 / 265.24 |

Datos completos antes/despu?s en `.runtime/f019/{before,after}-{http,browser}-{1,10}.json`;
comparaci?n calculada en `.runtime/f019/comparison.json`. Guardas y f?rmulas en
F019-Compare.py. Esos JSON no contienen credenciales/IDs ni datos de negocio.

Experimento separado de configuraci?n: 100 lecturas por operaci?n/modo en loopback,
c?digo after, mismo snapshot, sin CRUD ni base real. No se mezcl? con los pares anteriores.

| Vista | Mediana base ms | Mediana DEBUG ms | Mediana OPcache ms |
|---|---:|---:|---:|
| home | 96.90 | 97.44 | 54.43 |
| task | 95.58 | 95.33 | 46.01 |
| reports | 74.36 | 75.08 | 31.09 |
| users | 80.22 | 79.53 | 31.30 |

OPcache redujo coste PHP en este experimento; se verific? lectura de fuentes
actualizadas. DEBUG no mostr? un coste relevante en esta muestra. No se modific?
el lanzador/configuraci?n del usuario para activar OPcache ni desactivar DEBUG.
El PHP local 8.3.27 no tiene OPcache cargado; esa diferencia de runtime y la base
remota impiden extrapolar directamente el benchmark PHP 8.5.12 a su instalaci?n.

La navegaci?n documentada con diez navegadores al dashboard sigue costosa aun
con menos consultas: p95 before 44880.18 ms / after 43271.02 ms.
Siguiente diagn?stico: separar CPU/DOM/recursos/cola del servidor en esa vista;
no atribuir toda la demora a SQL ni cambiar contenido/paginaci?n sin necesidad probada.

Verificaci?n final: 64 tests / 349 aserciones SQLite; MariaDB 63 tests / 343
aserciones + nueva prueba de render 1 test / 6 aserciones, todos exit 0.
88 tests / 904 aserciones nativos pasan. Preflight/perfilador/observador pasan
en sint?ticos, incluida redacci?n y denegaci?n de rutas privadas. El fallo de
alias del helper de usuarios se corrigi? con un helper propio rvUserListing y
plantilla de detalle que conserva campos y escapa el tooltip; los lotes after
anteriores al arreglo se descartaron y todos los pares finales usan el arreglo.

Harness est?tico: 19 features/31 casos; escenarios: 26 fixtures, 0 fallos.
Mismo ejecutor, sin revisi?n independiente. C?digo de producto conservado tal
como est? en el commit realizado por el usuario c152293f1; no se reescribe ese commit.

F-019 queda blocked por aceptaci?n no alcanzada y medici?n real pendiente.
Responsable ejecutor: localizar costes restantes y verificar cada cambio; Ronnie:
generar muestras de uso normal con el observador cuando se retome la aceptaci?n.
No se ejecutaron carga/CRUD de prueba en la base real ni despliegue.
