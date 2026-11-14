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
