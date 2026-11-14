"""Explicit state transitions by the leader; requires existing evidence for closure."""
import json
import sys
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parents[2]
fid,status,evidence,next_action=sys.argv[1:5]
p=ROOT/'harness/feature-list.json'
state=json.loads(p.read_text(encoding='utf-8-sig'))
f=next(x for x in state['features'] if x['id']==fid)
assert status in state['statusValues']
assert status!='done', 'Use validated or a real blocked state before starting another feature'
if status=='in_progress':
    assert state['activeFeatureId'] in (None,fid)
    assert all(next(x for x in state['features'] if x['id']==d)['status']=='validated' for d in f['dependsOn'])
    state['activeFeatureId']=fid
else:
    assert (ROOT/evidence).is_file()
    if state['activeFeatureId']==fid: state['activeFeatureId']=None
for item in [f]+f['subtasks']:
    item.update(status=status,nextAction=next_action,blockers=[next_action] if status=='blocked' else [])
    if evidence!='-': item['evidence']=list(dict.fromkeys(item['evidence']+[evidence]))
if status=='validated':
    cp=ROOT/'harness/test-cases/cases.json'
    cases=json.loads(cp.read_text(encoding='utf-8-sig'))
    for case in cases['cases']:
        if case['featureId']==fid: case.update(status=status,observedResult=next_action,evidence=[evidence],limitations='Alcance y limitaciones reales descritos en evidencia; mismo ejecutor.')
    cp.write_text(json.dumps(cases,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
state['updatedAt']=datetime.now(ZoneInfo('America/Bogota')).isoformat()
p.write_text(json.dumps(state,ensure_ascii=False,indent=4)+'\n',encoding='utf-8')
print(fid,status)
