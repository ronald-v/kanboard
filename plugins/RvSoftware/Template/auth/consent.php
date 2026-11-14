<main class="rv-auth"><section class="rv-auth-card"><p class="rv-eyebrow">PROJECTA · CONEXIÓN SEGURA</p><h1>Conectar ChatGPT</h1><p>ChatGPT podrá actuar con tus permisos de Projecta. Puedes revocar la conexión desde tu cuenta.</p>
<ul><?php foreach ($scopes as $scope): ?><li><?= $this->text->e(['read'=>'Consultar proyectos, tareas y subtareas','write'=>'Crear y modificar proyectos, tareas y subtareas','delete'=>'Eliminar registros sin historial protegido'][$scope->getIdentifier()]) ?></li><?php endforeach ?></ul>
<form method="post" action="<?= $this->url->href('RvOAuthController','authorize',['plugin'=>'RvSoftware']) ?>"><?= $this->form->csrf() ?>
<?php foreach (['client_id','response_type','redirect_uri','scope','state','resource','code_challenge','code_challenge_method'] as $name): ?><?php if (isset($params[$name]) && is_string($params[$name])): ?><input type="hidden" name="<?= $this->text->e($name) ?>" value="<?= $this->text->e($params[$name]) ?>"><?php endif ?><?php endforeach ?>
<button class="btn btn-blue" name="decision" value="allow">Autorizar conexión</button><button class="btn" name="decision" value="deny">Cancelar</button>
</form></section></main>
