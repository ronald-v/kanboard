<main class="rv-auth"><section class="rv-auth-card" aria-labelledby="mfa-title">
<p class="rv-eyebrow">PROJECTA · ACCESO SEGURO</p>
<h1 id="mfa-title"><?= $factor ? 'Verifica tu identidad' : 'Protege tu cuenta' ?></h1>
<?php if ($error): ?><p class="alert alert-error" role="alert"><?= $this->text->e($error) ?></p><?php endif ?>
<?php if (! $factor): ?>
<p>Agrega Projecta a tu aplicación autenticadora y escribe el código de seis dígitos para continuar.</p>
<img class="rv-qr" src="<?= $this->url->href('RvAccessController','qrcode',['plugin'=>'RvSoftware']) ?>" alt="Código QR para configurar Projecta" width="246" height="246">
<details><summary>Configurar sin cámara</summary><p>Introduce esta clave en tu aplicación autenticadora:</p><code><?= $this->text->e($secret) ?></code></details>
<?php else: ?><p>Usa tu aplicación autenticadora o uno de tus códigos de recuperación.</p><?php endif ?>
<form method="post" action="<?= $this->url->href('RvAccessController','verify',['plugin'=>'RvSoftware']) ?>">
<?= $this->form->csrf() ?><label for="rv-code">Código de verificación</label>
<input id="rv-code" name="code" type="text" inputmode="text" autocomplete="one-time-code" maxlength="32" required autofocus spellcheck="false">
<button class="btn btn-blue" type="submit">Verificar y continuar</button>
</form>
<p><?= $this->url->link('Cerrar sesión','AuthController','logout',['csrf_token'=>$this->app->getToken()->getCSRFToken()]) ?></p>
</section></main>
