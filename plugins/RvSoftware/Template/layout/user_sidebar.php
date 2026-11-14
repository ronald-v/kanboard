<nav class="sidebar" aria-label="Perfil de usuario"><ul>
<?php foreach ([['UserViewController','show','Resumen'],['UserViewController','lastLogin','Últimos accesos'],['UserModificationController','show','Editar perfil'],['AvatarFileController','show','Avatar'],['UserCredentialController','changePassword','Cambiar contraseña'],['UserViewController','notifications','Notificaciones']] as $item): ?>
<?php if (($this->user->isAdmin() || $this->user->isCurrentUser($user['id'])) && $this->user->hasAccess($item[0],$item[1])): ?><li <?= $this->app->checkMenuSelection($item[0],$item[1]) ?>><?= $this->url->link($item[2],$item[0],$item[1],['user_id'=>$user['id']]) ?></li><?php endif ?>
<?php endforeach ?>
<?php if ($this->user->isCurrentUser($user['id'])): ?><li><?= $this->url->link('Seguridad y API','RvAccessController','manage',['plugin'=>'RvSoftware']) ?></li><?php endif ?>
<?= $this->hook->render('template:user:sidebar:information',['user'=>$user]) ?><?= $this->hook->render('template:user:sidebar:actions',['user'=>$user]) ?>
</ul></nav>
