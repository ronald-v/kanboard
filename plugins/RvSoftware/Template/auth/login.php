<main class="rv-auth"><section class="rv-auth-card" aria-labelledby="login-title">
<a class="rv-auth-brand" href="<?= $this->url->href('AuthController','login') ?>"><img src="<?= $this->url->dir() ?>assets/img/favicon.png?v=projecta-1" width="56" height="56" alt=""><span>Projecta<small>by rvSoftware</small></span></a>
<p class="rv-eyebrow">TU EQUIPO, EN EL MISMO PROYECTO</p><h1 id="login-title">Bienvenido a Projecta</h1><p>Organiza proyectos, tareas y el tiempo de tu equipo.</p>
<?php if (isset($errors['login'])): ?><p class="alert alert-error" role="alert"><?= $this->text->e($errors['login']) ?></p><?php endif ?>
<?= $this->hook->render('template:auth:login-form:before') ?>
<form method="post" action="<?= $this->url->href('AuthController','check') ?>">
<?= $this->form->csrf() ?>
<div class="rv-auth-field"><label for="form-username">Usuario</label><?= $this->form->text('username',$values,$errors,['required','autofocus','autocomplete="username"','autocapitalize="none"','spellcheck="false"']) ?></div>
<div class="rv-auth-field"><label for="form-password">Contraseña</label><?= $this->form->password('password',[],$errors,['required','autocomplete="current-password"']) ?></div>
<?php if (! empty($captcha)): ?><label for="form-captcha">Verificación de acceso</label><img src="<?= $this->url->href('CaptchaController','image') ?>" alt="Texto de verificación"><input id="form-captcha" name="captcha" required autocomplete="off"><?php endif ?>
<button class="btn btn-blue" type="submit">Ingresar a Projecta</button>
</form>
<?php if ($this->app->config('password_reset')==1): ?><p class="rv-auth-link"><?= $this->url->link('¿Olvidaste tu contraseña?','PasswordResetController','create') ?></p><?php endif ?>
<p class="rv-auth-security"><i class="fa fa-lock" aria-hidden="true"></i> Tu cuenta está protegida con verificación en dos pasos.</p>
<?= $this->hook->render('template:auth:login-form:after') ?>
</section><p class="rv-auth-footer">Projecta · Un producto de rvSoftware</p></main>
