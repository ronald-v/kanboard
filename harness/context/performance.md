# Rendimiento: medir antes de optimizar

No hay una causa ni un presupuesto de latencia establecido por esta guía.
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
