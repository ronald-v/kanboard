# DEC-033 ? Resultado local de seguridad

Fecha: 2026-11-14T20:38:10-05:00. Feature: F-020.

Evidencia: [informe y paquete](../evidence/2026-11-14-f020-security.md).
Base conservada: estado compartido previo bajo .runtime/f020/baseline y archivos
versionados. Se compararon los 19 registros previos: permanecen iguales. El candidato
integrado se verifica con Test-Harness y sus escenarios antes del cierre.

Decisi?n t?cnica del ejecutor: aceptar los criterios locales comprobados y conservar
las correcciones de errores, tablero, rutas, proxy y cach? de adjuntos. El ajuste de
streaming evita una regresi?n introducida por el b?fer. Se a?aden excepciones m?nimas
de n?cleo porque un plugin no puede contener un fallo anterior a su propia carga.
Los fallos de runner/copia y las regresiones detectadas se retestearon; no se convirtieron
intentos interrumpidos en evidencia de aprobaci?n. Modelo de trabajo: mismo ejecutor,
sin subagentes ni revisi?n independiente.

Impacto: F-020 validated localmente y sin activa. Casos F-020 actualizados; anteriores
conservados. No autoriza despliegue, cambios de cuentas/infraestructura ni uso de datos
reales. Se mantienen riesgos de cuota y verificaci?n externa, cobertura parcial de
librer?as copiadas y bloqueos F-012/F-015/F-019. Ning?n cambio en vendor/libs ni migraciones.

Recuperaci?n: revertir ?nicamente el diff propio comparado, preservando cambios ajenos
previos y configuraci?n/PROJECTA_KEY. El paquete es preview y excluye harness/pruebas/
secretos; manifiesto productionAuthorized=false. No hay rollback de DB que ejecutar.

Siguiente: verificar configuraci?n, cuota/tr?fico y recuperaci?n efectivos del hosting
bajo autorizaci?n vigente; no inferirla de la creaci?n del ZIP ni de esta decisi?n.
