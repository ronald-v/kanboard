<section class="rv-panel"><h1>Seguridad de tu cuenta</h1><p>El segundo factor es obligatorio. Para cambiarlo necesitas tu contraseña y un código nuevo del autenticador o de recuperación.</p>
<?php if ($error): ?><p class="alert" role="status"><?= $this->text->e($error) ?></p><?php endif ?>
<form class="rv-entry-form" method="post" action="<?= $this->url->href('RvAccessController','change',['plugin'=>'RvSoftware']) ?>">
<?= $this->form->csrf() ?>
<label>Contraseña actual<input type="password" name="password" autocomplete="current-password" required></label>
<label>Código de verificación<input name="code" autocomplete="one-time-code" maxlength="32" required></label>
<button class="btn btn-blue" name="operation" value="recovery">Renovar códigos de recuperación</button>
<button class="btn" name="operation" value="replace">Reemplazar autenticador</button>
<button class="btn" name="operation" value="oauth-revoke">Revocar conexión de ChatGPT</button>
<details><summary>Credenciales individuales de API</summary><p>Usa una credencial distinta para cada integración. Solo se muestra al crearla y caduca en 90 días.</p><label>Nombre de la integración<input name="label" maxlength="100" autocomplete="off"></label><button class="btn" name="operation" value="api-create">Crear credencial API</button>
<?php if ($tokens): ?><label>Credencial a revocar<select name="token_id"><?php foreach ($tokens as $token): ?><option value="<?= $this->text->e($token['token_hash']) ?>"><?= $this->text->e($token['label']) ?></option><?php endforeach ?></select></label><button class="btn" name="operation" value="api-revoke">Revocar credencial</button><?php endif ?></details>
<?php if ($this->user->isAdmin()): ?><details><summary>Recuperación administrativa</summary><p>Reinicia el factor de un usuario. Se revocan sus accesos y se registra la operación.</p><label>ID de usuario<input type="number" name="target_id" min="1"></label><button class="btn" name="operation" value="reset-user">Reiniciar factor del usuario</button></details><?php endif ?>
</form></section>
