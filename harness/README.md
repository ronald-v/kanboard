# Harness de Kanboard rvSoftware

Actualización de alcance: [DEC-003](feedback/2026-11-12-DEC-003-desarrollo-secuencial.md)
autoriza implementar secuencialmente F-001..F-012, ramas `feat/001`..`feat/012`,
commits `dev feature 001`..`dev feature 012` y push al origin existente.
Decisiones comerciales, diseño y producción mantienen sus condiciones explícitas.
El estado actual siempre está en feature-list.json; los párrafos de entrega
original siguientes describen el diagnóstico histórico. Preparación aislada y
comandos reproducibles en [testing](context/testing.md).

Este harness organiza trabajo de desarrollo sobre la copia real de Kanboard.
No se instala en Hostinger ni añade un servicio a la aplicación. Está formado
por Markdown, registros JSON y un verificador PowerShell. No hay agentes
autónomos persistentes, CI del harness ni orquestador configurados.

## Entorno y uso
Entorno comprobado: Windows, PowerShell, Git, PHP CLI 8.3.27 y Composer 2.8.12.
GD, controladores PDO y PHPUnit faltan en la línea base; véase
[testing](context/testing.md). No instalar dependencias para leer el harness.

Desde la raíz `C:\Proyectos\kanboard`:

```powershell
git status --short
Get-Content AGENTS.md -Encoding UTF8
Get-Content harness/feature-list.json -Raw -Encoding UTF8
Get-Content harness/current-task.md -Encoding UTF8
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
```

El archivo de entrada es `AGENTS.md`. No se requiere adaptador adicional en
este entorno. Los prompts son instrucciones que un agente puede aplicar
secuencialmente; no son ejecutables ni equivalen a revisores independientes.

## Elegir y ejecutar una feature
1. Revisar alcance vigente en [proyecto](context/project.md) y feedback.
2. Revisar cambios ajenos y bloqueo/dependencias de la feature. No limpiar Git.
3. Seleccionar una sola feature autorizada; cambiarla a `in_progress` y poner
   su ID en `activeFeatureId`. Las dependencias deben estar `validated`.
4. Consultar `prompt-map.json` y solo el contexto necesario. Definir criterios,
   archivos permitidos, pruebas y recuperación antes de implementar.
5. Ejecutar, observar y revisar diff. Registrar comandos, códigos de salida,
   resultado, limitaciones y fecha en evidencia, sin secretos.
6. Actualizar feature/subtareas y casos, luego checkpoint. `done` no cierra el
   ciclo; `validated` exige aceptación comprobada. Si se suspende una feature
   `done` para otra, registrar consentimiento en `suspensionApproval` como ruta
   a feedback real. No inventarlo.
7. Validar el harness y entregar resultado. Si no hay siguiente feature autorizada,
   informar propuesta y detener el desarrollo del producto.

Una feature bloqueada conserva `blockers` y `nextAction`; `activeFeatureId` vuelve
a null si no existe trabajo activo. Se puede retomar al verificar la condición,
sin borrar historia. Tras tres intentos sin progreso sobre la misma causa,
documentar bloqueo e intentos. Nunca reducir criterios para aprobar.

## Contratos de archivos
- `feature-list.json`: estado único, IDs F-001...F-012 y subtareas estables;
  `targetVersion: null` hasta acordar versión propia, distinta de la base upstream.
- `prompt-map.json`: rutas existentes de prompts/contextos/código y IDs de casos;
  no contiene estados de ejecución. Las rutas futuras del plugin se describen
  como propuestas en contextos, nunca como código existente en el mapa.
- `current-task.md`: feature/checkpoint, resultado, archivos, pruebas, bloqueo y
  siguiente paso; remite al estado canónico.
- `feedback/`: decisiones fechadas con historia conservada.
- `test-cases/cases.json`: casos del producto; no incluye implementaciones falsas.
- `test-cases/harness-scenarios.json`: evaluaciones reales y pendientes del harness.
- `evidence/`: resúmenes depurados; artefactos grandes se referencian fuera de aquí.

## Validación disponible
`Test-Harness.ps1` comprueba estructura, campos, estados, referencias, DAG,
actividad, evidencias declaradas, contratos de prompts y enlaces Markdown locales.
Solo lee archivos y no carga PHP ni conecta a una base. Código de salida 0 indica
coherencia estática; 1 indica problemas. No demuestra que una afirmación escrita
sea verdadera ni ejecuta los casos del producto. Revisar evidencia humana.

```powershell
powershell -NoProfile -File harness/scripts/Test-Harness.ps1
powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1
```

El segundo comando prueba rechazos del validador con fixtures temporales aislados
y los elimina dentro de un directorio verificado. No simula agentes ni funciones
de Kanboard. Las suites PHP siguen bloqueadas hasta preparar el entorno.

Entrega comprobada: 12 features, 23 casos, 9 prompts y 18 fixtures del validador.
Inventario: AGENTS.md; 4 archivos de coordinación en harness/; 9 contextos;
9 prompts; 2 registros de casos/escenarios; 2 scripts; 2 evidencias y 2 decisiones.
Resultados y checklist en [evidencia de validación](evidence/2026-11-11-harness-validation.md).

## Alcance de esta entrega
Generar el harness fue autorizado; las 12 features funcionales no fueron ejecutadas.
F-001 conserva la inspección comprobada y el bloqueo de ejecución. El diagnóstico
del Dashboard sigue reportado, sin reproducción. El despliegue permanece separado.
El harness está diseñado para permanecer en desarrollo: el empaquetado actual
todavía necesita exclusiones específicas antes de producir una entrega desplegable.
