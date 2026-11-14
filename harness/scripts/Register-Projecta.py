"""Register the explicitly approved Projecta delivery; never starts the application."""
import json
from pathlib import Path
from datetime import datetime
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[2]
stamp = datetime.now(ZoneInfo('America/Bogota')).isoformat()
specs = [
    ('013', 'Rendimiento medido', 'performance', ['performance', 'database', 'testing'], [], 'p95 lecturas <=800 ms y escrituras <=1500 ms con 10 usuarios, 100 proyectos y 10000 tareas; evidencia antes/después.'),
    ('014', 'Acceso Projecta y MFA obligatorio', 'security', ['security', 'architecture', 'testing'], [], 'Alta TOTP obligatoria, recuperación, límites atómicos y sesiones restringidas; negativos comprobados.'),
    ('015', 'Identidad Projecta y UI completa', 'ui-ux', ['ui-brand', 'architecture', 'testing'], [], 'Nombre/iconos Projecta, cabecera azul, proyecto seleccionado visible y revisión por vista retenida.'),
    ('016', 'Recorte controlado y SMTP preparado', 'architect', ['engineering', 'architecture', 'testing'], [], 'Funciones innecesarias inaccesibles; SMTP por entorno sin secretos versionados ni envíos reales.'),
    ('017', 'API individual y CRUD seguro', 'api', ['api-mcp', 'security', 'testing'], ['F-014'], 'CRUD y permisos por objeto probados; tokens globales/contraseña deshabilitados; historia protegida.'),
    ('018', 'MCP remoto Projecta', 'mcp', ['api-mcp', 'security', 'architecture', 'testing'], ['F-017'], 'OAuth/PKCE y CRUD por protocolo probados; paquete remoto; validación ChatGPT separada si Plus/Pro no permite escritura.'),
]
def read(name):
    return json.loads((ROOT/name).read_text(encoding='utf-8-sig'))
def write(name, value):
    (ROOT/name).write_text(json.dumps(value, ensure_ascii=False, indent=4 if name.endswith('feature-list.json') else 2)+'\n', encoding='utf-8')

state, cases, mapping = (read(p) for p in ['harness/feature-list.json','harness/test-cases/cases.json','harness/prompt-map.json'])
assert not any(f['id']=='F-013' for f in state['features']), 'Already registered'
assert state['activeFeatureId'] is None
for number, name, role, context, deps, acceptance in specs:
    fid = 'F-'+number
    status = 'in_progress' if number=='013' else 'planned'
    prompts = list(dict.fromkeys(['harness/prompts/'+r+'.md' for r in [role,'dev','testing','security']]))
    contexts = ['harness/context/'+c+'.md' for c in context]
    cid = 'TC-'+fid+'-01'
    feature = dict(id=fid,name=name,description=name+'. Autorización explícita: DEC-026.',status=status,targetVersion=None,dependsOn=deps,
        acceptanceCriteria=[acceptance],subtasks=[dict(id=fid+'-S1',name=name,status=status,assignedAgent=role,agentNotes='Consulta de roles por el mismo ejecutor; sin delegación.',evidence=[],blockers=[],nextAction='Implementar y verificar el alcance aprobado.')],
        relatedPrompts=prompts,relatedContext=contexts,relatedFiles=['plugins/RvSoftware/Plugin.php'],testCases=[cid],evidence=[],blockers=[],nextAction='Implementar y verificar el alcance aprobado.',notes='Plan y decisiones aprobados en esta conversación. No autoriza producción.',suspensionApproval=None)
    state['features'].append(feature)
    cases['cases'].append(dict(id=cid,featureId=fid,objective=name,preconditions=['Entorno aislado y datos sintéticos.'],syntheticData='Usuarios y objetos sintéticos; sin base del usuario.',steps=['Ejecutar verificaciones de aceptación y revisar efectos.'],command=None,expectedResult=acceptance,verificationType='Integración y verificación funcional',status='planned',observedResult=None,evidence=[],limitations='No ejecutado todavía; hosting/cliente real se verifican por separado.'))
    mapping['changes'].append(dict(id='MAP-'+fid,changeType=name,responsible=role,prompts=prompts,context=contexts,features=[fid],codeZones=['plugins/RvSoftware','app'],testCases=[cid]))
state.update(activeFeatureId='F-013',updatedAt=stamp)
for name,value in [('harness/feature-list.json',state),('harness/test-cases/cases.json',cases),('harness/prompt-map.json',mapping)]:
    write(name,value)
print('Registered F-013..F-018; only F-013 active.')
