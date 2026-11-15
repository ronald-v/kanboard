# DEC-034 ? Correcci?n de empaquetado Composer

Fecha: 2026-11-14T21:24:30-05:00. Feature: F-020.

Evidencia: reporte de error del usuario al regenerar el ZIP; comparaci?n con el
artefacto verificado: solo cambian dos referencias Git de rvsoftware/projecta-mcp
en mcp/vendor/composer/installed.php. No cambian versiones/referencias de dependencias.

Decisi?n: corregir de forma reversible el empaquetado para normalizar ?nicamente
la referencia Git del paquete ra?z en los bytes del artefacto/copia de pruebas;
no editar vendor del proyecto ni ignorar el archivo. Mantener hashes completos de
dependencias, controles de antig?edad y rechazo ante cambios reales. A?adir diagn?stico
de rutas divergentes y actualizar la evidencia tras retests.

Impacto: reabrir F-020 durante esta correcci?n; estados anteriores intactos. Copia
de base en .runtime/f020/packaging-fix-baseline. No autoriza despliegue ni base real.

Siguiente: pruebas positivas/negativas, suites aisladas, evidencia vigente y ZIP exacto.
