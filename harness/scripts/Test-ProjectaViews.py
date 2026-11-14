"""HTTP inventory of retained views; does not claim visual/browser acceptance."""
import json
import urllib.error
from projecta_http import ROOT,authenticated,request,csrf

fixture=json.loads((ROOT/'.runtime/projecta/ui-fixture.json').read_text())
params='&'+'&'.join(str(k)+'='+str(v) for k,v in fixture.items())
session=authenticated()
routes=[('RvHomeController','show','RvSoftware'),('RvHomeController','projects','RvSoftware'),('RvTimeController','show','RvSoftware'),('RvReviewController','show','RvSoftware'),('RvBagController','show','RvSoftware'),('RvReportController','show','RvSoftware'),
    ('DashboardController','show',''),('ProjectListController','show',''),('BoardViewController','show',''),('TaskViewController','show',''),('TaskModificationController','edit',''),('TaskCreationController','show',''),('SubtaskController','create',''),('ProjectViewController','show',''),('ProjectEditController','show',''),('ProjectPermissionController','index',''),
    ('UserListController','show',''),('UserCreationController','show',''),('UserViewController','show',''),('UserModificationController','show',''),('UserCredentialController','changePassword',''),('GroupListController','index',''),('GroupCreationController','show',''),
    ('ConfigController','application',''),('ConfigController','project',''),('ConfigController','board',''),('ConfigController','email',''),('ConfigController','api',''),('TagController','index',''),('LinkController','show',''),('RvAccessController','manage','RvSoftware')]
results=[]
for controller,action,plugin in routes:
    url=f'?controller={controller}&action={action}&plugin={plugin}'+params
    response,body=request(session,url)
    assert response.status==200, (controller,action,response.status)
    if '<html' in body:
        assert 'rv-projecta' in body and '· Projecta' in body,(controller,action,'Missing Projecta layout')
    else:
        assert '<form' in body or 'table' in body,(controller,action,'Empty modal content')
    assert 'adaptive-favicon.svg' not in body
    if controller=='RvBagController': assert 'Proyecto seleccionado:' in body and 'aria-current="page"' in body
    results.append({'controller':controller,'action':action,'status':200,'visual':'pending'})
for controller,action in [('CurrencyController','show'),('PluginController','show'),('ConfigController','integrations'),('ConfigController','webhook'),('UserViewController','external'),('BoardViewController','readonly')]:
    try:
        request(session,f'?controller={controller}&action={action}'+params)
        raise AssertionError('Disabled route accessible: '+controller+'/'+action)
    except urllib.error.HTTPError as error: assert error.code==404
_,body=request(session,'?controller=ConfigController&action=application')
response,body=request(session,'?controller=ConfigController&action=save',{'csrf_token':csrf(body),'application_language':'es_ES'})
assert response.status==200
assert 'SMTP pendiente de configuración' in request(session,'?controller=ConfigController&action=email')[1]
(ROOT/'.runtime/projecta/views-http.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(f'PASS {len(results)} retained HTTP views, Projecta metadata/icons, selected project, configuration save and 6 disabled direct routes. Visual review pending.')
