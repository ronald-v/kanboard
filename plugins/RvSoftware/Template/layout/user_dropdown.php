<div class="dropdown"><a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="Menú de usuario"><?= $this->avatar->currentUserSmall('avatar-inline') ?><i class="fa fa-caret-down" aria-hidden="true"></i></a><ul>
<li class="no-hover"><strong><?= $this->text->e($this->user->getFullname()) ?></strong></li>
<li><?= $this->url->icon('home','Mi perfil','UserViewController','show',['user_id'=>$this->user->getId()]) ?></li>
<li><?= $this->url->icon('shield','Seguridad y recuperación','RvAccessController','manage',['plugin'=>'RvSoftware']) ?></li>
<?php if ($this->user->hasAccess('UserListController','show')): ?>
<li><?= $this->url->icon('user','Usuarios','UserListController','show') ?></li><li><?= $this->url->icon('group','Grupos','GroupListController','index') ?></li><li><?= $this->url->icon('cog','Configuración','ConfigController','application') ?></li>
<?php endif ?>
<?= $this->hook->render('template:header:dropdown') ?>
<li><?= $this->url->icon('sign-out','Cerrar sesión','AuthController','logout',[],true) ?></li>
</ul></div>
