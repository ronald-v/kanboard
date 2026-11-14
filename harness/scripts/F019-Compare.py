"""Compare paired synthetic results; never infer real acceptance from loopback."""
from pathlib import Path
import argparse
import json

WORK = Path(__file__).resolve().parents[2]/'.runtime/f019'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--normal-before',type=Path)
parser.add_argument('--normal-after',type=Path)
args = parser.parse_args()
if args.normal_before or args.normal_after:
    if not (args.normal_before and args.normal_after):
        parser.error('Both normal-usage summary files are required')
    before, after = (json.loads(p.read_text(encoding='utf-8')) for p in (args.normal_before,args.normal_after))
    expected = [
        'RvHomeController/show navigation','RvHomeController/projects navigation','RvTimeController/show navigation',
        'RvBagController/show navigation','RvReportController/show navigation','DashboardController/show navigation',
        'BoardViewController/show navigation','TaskViewController/show navigation','UserListController/show navigation','ConfigController/application navigation',
        'ProjectCreationController/create form_open','ProjectEditController/show form_open','TaskCreationController/show form_open',
        'TaskModificationController/edit form_open','SubtaskController/create form_open','SubtaskController/edit form_open',
        'UserCreationController/show form_open','UserModificationController/show form_open',
        'ProjectCreationController/save save','ProjectEditController/update save','TaskCreationController/save save',
        'TaskModificationController/update save','SubtaskController/save save','SubtaskController/update save',
        'UserCreationController/save save','UserModificationController/save save']
    results = {}
    for name in expected:
        first, last = before.get(name), after.get(name)
        if not first or not last or min(first['samples'],last['samples'])<100 or first.get('p95_ms') is None or last.get('p95_ms') is None:
            results[name] = {'status':'pending sufficient paired samples'}
            continue
        reductions = {k:round(100*(1-last[k]/first[k]),2) for k in ('median_ms','p95_ms')}
        passed = first['errors']==last['errors']==0 and all(value>=50 for value in reductions.values())
        results[name] = {'status':'meets goal' if passed else 'does not meet goal','reduction_percent':reductions,'errors':[first['errors'],last['errors']]}
    complete = all(row['status']=='meets goal' for row in results.values())
    (WORK/'normal-comparison.json').write_text(json.dumps({'meets_goal':complete,'flows':results},indent=2))
    print('Normal usage: '+str(sum(row['status']=='meets goal' for row in results.values()))+'/26 flows meet both reductions >=50% with sufficient samples and zero errors.')
    raise SystemExit(0 if complete else 1)
report = {'real_acceptance':'pending normal-usage paired samples', 'comparisons':{}}
for kind in ('http','browser'):
    for clients in (1,10):
        label = kind+'-'+str(clients)
        before_file, after_file = (WORK/(phase+'-'+label+'.json') for phase in ('before','after'))
        if not before_file.exists() or not after_file.exists():
            continue
        before, after = (json.loads(p.read_text()) for p in (before_file,after_file))
        assert before['server_processes'] == after['server_processes'] == 1
        assert before['clients'] == after['clients'] == clients
        assert before.get('deadline_ms',30000) == after.get('deadline_ms',30000)
        assert set(before['flows']) == set(after['flows'])
        flows = {}
        for name, first in before['flows'].items():
            last = after['flows'][name]
            assert first['samples'] == last['samples'] and first['samples'] >= 100
            row = {'before_ms':{k:first[k] for k in ('median_ms','p95_ms')}, 'after_ms':{k:last[k] for k in ('median_ms','p95_ms')},
                   'reduction_percent':{k:round(100*(1-last[k]/first[k]),2) for k in ('median_ms','p95_ms')}}
            row['meets_50_percent'] = all(value >= 50 for value in row['reduction_percent'].values())
            if 'final_response_db_requests' in first:
                row['mysql_questions'] = [first['final_response_db_requests'],last['final_response_db_requests']]
            flows[name] = row
        report['comparisons'][label] = flows
(WORK/'comparison.json').write_text(json.dumps(report,indent=2))
for label, flows in report['comparisons'].items():
    passed = sum(row['meets_50_percent'] for row in flows.values())
    print(label+': '+str(passed)+'/'+str(len(flows))+' flows meet BOTH median/p95 reduction >=50%; real acceptance pending.')
