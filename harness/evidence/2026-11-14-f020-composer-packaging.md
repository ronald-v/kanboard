# F-020 · Corrección del ZIP tras regenerar metadata Composer

Seguimiento autorizado por el reporte del usuario, DEC-034. Mismo ejecutor, sin
revisión independiente, despliegue ni acceso a la base real.

## Causa comprobada

La comparación con el ZIP anterior identificó un único archivo diferente:
`mcp/vendor/composer/installed.php`. Sus dos referencias al paquete raíz
`rvsoftware/projecta-mcp` pasaron de 956e1a2abb16bdddcdaed2a401b2c8f303e1766e
a c152293f13a0badba77984887dcbcd9410b41f58. No cambiaron otras entradas del artefacto,
versiones o referencias de dependencias. El gate detectaba correctamente bytes
distintos, pero incluía una identidad Git incidental del paquete raíz que Composer
regenera al operar dentro del repositorio padre.

## Corrección

El colector genera bytes estables para ese archivo: exclusivamente las dos
referencias Git del paquete raíz pasan a null. La copia sintética usa exactamente
la misma transformación y el sellador compara esos bytes. Se conserva el archivo
completo, el PHP restante, versiones/referencias de dependencias y su validación.
El formato o nombre de paquete inesperados fallan de forma cerrada. El manifiesto
conserva el commit base y los hashes del código realmente empaquetado.

No se modificó el vendor original. Su installed.php conserva SHA256
376af3824d287ed8fc53d3d43ba5c884bc8b2cc70554f9b117ee59c453514bde.
No se añadieron bypasses del gate ni se relajó la vigencia de 24 horas.

Ante errores esperados, el comando informa las rutas agregadas, retiradas o
modificadas y termina con código 1 sin traceback. Un cambio real sigue exigiendo
verificación y evidencia nueva.

## Comprobaciones observadas

- 16 tests del gate pasan. Incluyen equivalencia/idempotencia de referencias raíz,
  rechazo de cambios de dependencia o PHP adicional, formato inesperado y rutas
  divergentes; permanecen los negativos de antigüedad, resultados, locks y herramientas.
- Los dos installed.php reales —ZIP anterior y archivo regenerado— producen bytes
  idénticos después de la transformación. Sintaxis PHP del archivo resultante válida.
- CLI con evidencia anterior: rechazo esperado y ruta divergente visible sin traceback.
- Regresión MariaDB: 64 tests / 349 aserciones, exit 0. HTTP MFA/API/OAuth/MCP,
  31 vistas, permisos/scopes, 25 rutas denegadas/5 permitidas y Chromium pasan.
- Se conservaron los resultados vigentes de SQLite 152/561 y de 80 fallos HTTP:
  sus archivos sujetos no cambiaron y el sellador comprobó todos sus hashes.
- ZIP extraído: hashes completos y cuatro fallos reales de conexión local devuelven
  503 sin datos internos. Segunda ejecución del comando: mismo archivo y mismo SHA256.

## Entrega y continuidad

Paquete: `.runtime/releases/projecta-0.2.0-preview-88f1e99112a1.zip`.
SHA256: `94a7f487c28aabbc9d91f9379fcd0330f8652e3f6560016f57d308a0cae0c865`.
3.845 archivos, preview con productionAuthorized=false.

Evidencia renovada: `.runtime/f020/security-evidence.json`. El comando del README
no cambia. Las condiciones de hosting y los riesgos pendientes del informe de
seguridad anterior se mantienen; generar este ZIP no autoriza producción.

Base conservada en `.runtime/f020/packaging-fix-baseline`. Recuperación: revertir
únicamente esta corrección de scripts/documentación y restaurar evidencia compatible;
no restaurar vendor ni datos. Los otros 19 registros de features se conservan.
