# F-013 · Rendimiento sintético

Fecha: 2026-11-14T10:45:00-05:00. Mismo ejecutor, sin revisión independiente.

Destino: copia .runtime/projecta/app sin configuración privada; PHP 8.5.12 y
MariaDB 11.8.9-log loopback 13318, schema kanboard_compat_http reservado.
10 procesos/usuarios independientes; 100 proyectos y 10000 tareas de referencia;
2 iteraciones de calentamiento + 20 por proceso = 200 muestras por operación.
Comandos: Prepare-ProjectaSandbox.py, Projecta-Benchmark.php seed,
Benchmark-Projecta.py before/after. Los JSON íntegros locales quedan en .runtime/projecta.

| Operación | p95 antes ms | p95 después ms |
|---|---:|---:|
| Proyectos liderados |80.56|4.78|
| Resumen de trabajo |209.30|62.87|
| Crear proyecto |10.43|25.91|
| Crear tarea |30.43|46.61|
| Crear usuario |3.62|6.76|
| Crear grupo |3.95|6.53|

El resumen ejecutaba 409 consultas PicoDB; ahora 5 PicoDB + 2 consultas PDO
conjuntas. El cálculo de liderazgo pasa de 202 PicoDB a una PDO. No se introduce
caché de permisos: cambios y revocaciones se consultan inmediatamente. Se conserva
precedencia del rol directo sobre roles de grupo. Las escrituras no mejoraron;
no se atribuye una mejora a ellas. Las mediciones son del dominio en CLI, sin
TTFB/red/navegador. Algunos primeros intentos concurrentes fallaron en eventos
nativos de tareas; los dos lotes finales terminaron, pero esto no descarta la carrera.

Pendiente: benchmark HTTP completo con seguridad final y explicación de latencia
en la MariaDB remota del usuario/hosting. No se conectó ni se hizo carga allí.
F-013 permanece blocked para la aceptación extremo a extremo; no confundir el
avance medido con resolución integral del síntoma. Responsable: ejecutor para HTTP;
Ronnie para acceso de staging efectivo si se necesita medir red/hosting.
