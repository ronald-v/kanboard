# Validación de la entrega del harness

Fecha de comprobación: 2026-11-11T22:37:09.5298868-05:00 (America/Bogota).
Alcance autorizado: DEC-001. Modalidad: un agente con roles secuenciales, sin revisión
independiente. Fuente: ejecución local de scripts; no aplicación ni producción.

## Resultados ejecutados

```powershell
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1
```

Primer comando: exit 0; 12 features, 23 casos, 9 prompts, 10 escenarios.
Segundo comando: exit 0; 18 fixtures comprobados, cero fallos.
PowerShell 5.1.19041.6456; ambos scripts analizados por el parser sin errores.
AGENTS.md: 5.492 caracteres, menor al límite 7.600.

| Fixture ejecutado | Esperado | Observado |
| --- | --- | --- |
| zip-without-git | Exit 0 y aviso de procedencia limitada | Coincide |
| repository-absent | Exit 1 por marcador de Kanboard ausente | Coincide |
| invalid-status | Exit 1, estado no admitido | Coincide |
| duplicate-id | Exit 1, ID repetido | Coincide |
| two-active-features | Exit 1, más de una activa | Coincide |
| dependency-cycle | Exit 1, ciclo | Coincide |
| missing-path | Exit 1, referencia inexistente | Coincide |
| validated-no-evidence | Exit 1, evidencia vacía | Coincide |
| validated-null-evidence | Exit 1, evidencia null | Coincide |
| validated-no-observed | Exit 1, resultado observado ausente | Coincide |
| blocked-no-cause | Exit 1, bloqueo sin causa | Coincide |
| unsatisfied-dependency | Exit 1, inicio con dependencia no validada | Coincide |
| done-without-suspension | Exit 1, otra activa sin aceptación de suspensión | Coincide |
| malformed-json | Exit 1, JSON ilegible | Coincide |
| outside-reference | Exit 1, ruta fuera del repositorio | Coincide |
| parallel-state-file | Exit 1, segundo archivo de estado | Coincide |
| recorded-failure-stays-blocked | Exit 0, resultado sintético fallido sigue blocked | Coincide |
| preserve-dirty-git | Exit 0, hash y cambio M se conservan | Coincide |

Los fixtures contienen copias del harness y marcadores sintéticos de rutas, no
datos ni código completo de Kanboard. El caso Git crea un repositorio temporal
real con un archivo ficticio modificado. Se verifica el destino antes de eliminar
el directorio temporal propio. No se modifica Git del proyecto.
El primer ensayo de 17 fixtures produjo advertencias de Git por el marcador de
.gitattributes; se cambió a comentario y se repitió la suite ampliada a 18 sin
esas advertencias. No hubo suite PHP ejecutada.

## Checklist de aceptación de la entrega
- [x] Base real identificada y límites documentados.
- [x] Reutilización de Kanboard y orden configuración/plugin/núcleo explícitos.
- [x] Instrucciones, mapa, estado, checkpoint y contextos separados.
- [x] Nueve prompts con rol, contexto, entradas/salidas, límites, checklist y escalamiento.
- [x] Estados exclusivos, IDs únicos y rutas existentes comprobados.
- [x] Ninguna feature activa; dependencias sin ciclos; bloqueo F-001 preservado.
- [x] Cada feature tiene aceptación y caso; evidencia pendiente no inventada.
- [x] validated exige evidencia, observado y subtareas/casos finalizados.
- [x] Licencia, autenticación, permisos, migraciones y código del producto sin cambios.
- [x] Sin SP-only, exclusión global SQL ni estructura DB/ impuesta.
- [x] Trabajado/facturable/consumido/facturado separados; BR-01..10 pendientes.
- [x] Concurrencia, precisión, auditoría, reconciliación y permisos contemplados.
- [x] Dashboard reportado, sin reproducción ni corrección atribuida.
- [x] Activos oficiales diferenciados del diseño no aprobado.
- [x] Actualización upstream y recuperación conjunta documentadas.
- [x] Despliegue PHP separa producto/harness/secretos; empaquetado aún pendiente.
- [x] JSON/rutas/enlaces verificados; comandos existentes y ejecutados diferenciados.
- [x] Tres intentos sin progreso, alcance y continuidad definidos.
- [x] Validación estática diferenciada del funcionamiento del producto.

## Límites y escenarios completos
El verificador comprueba declaraciones y estructura; no demuestra que el texto
de una evidencia sea verdadero. Tampoco verifica automáticamente toda transición
histórica, aprobación humana o conducta futura de agentes.
HS-01/02/03 quedan done con comprobación parcial mediante fixtures; falta evaluar
comportamiento completo del agente. HS-04/05/08 están blocked; HS-06/09/10 pendientes.
HS-07 está validated por el bloqueo real de herramientas observado. Consultar
[escenarios](../test-cases/harness-scenarios.json) para esperado/observado.

## Fallos y pendientes del producto
Composer validate exit 0 con advertencias; plataforma exit 2 por GD; PDO sin drivers;
PHPUnit exit 1 por ejecutable ausente. No hubo servidor, migración, prueba HTTP,
concurrencia de bolsa, actualización ni despliegue. Reglas y Hostinger pendientes.
Estos bloqueos no se convierten en criterios aprobados.
