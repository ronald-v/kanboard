# DEC-006: completar la preparación de base

Fecha: 2026-11-12T04:33:02-05:00.
Feature: F-001; seguimiento a DEC-004/005.
Evidencia: suite completa de 1240 tests ejecutada, exit 2: 2 errores por
ldap_escape ausente y 1 fallo de sanitize_path para ruta relativa en Windows.

Decisión: habilitar LDAP incluido en PHP solo por proceso. Corregir en
app/functions.php la resolución relativa antes de detectar la unidad y normalizar
barras mixtas para unidades Windows. Los tests relativos usan separador nativo;
se añaden dos aserciones de rutas Windows mixtas. No se reduce cobertura ni se
cambian expectativas de navegación o límites de directorio.

Impacto: excepción del núcleo justificada por ejecución local de la base que no
puede corregirse con configuración/plugin. Regresión dirigida FunctionTest,
FileStorageTest y LdapBackendGroupProviderTest: exit 0, 22 tests/50 aserciones.
Las 35 omisiones anteriores y el warning se conservan como diagnósticos, sin
inventar validación. Recuperación: revertir el commit F-001 y extensiones por proceso.

Siguiente acción: repetir suite completa por cambios reales; actualizar evidencia
y estado antes de commit/push. Revisar funciones y storage al actualizar upstream.
