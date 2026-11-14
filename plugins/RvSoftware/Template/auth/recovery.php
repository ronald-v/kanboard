<main class="rv-auth"><section class="rv-auth-card" aria-labelledby="recovery-title">
<p class="rv-eyebrow">PROJECTA · RECUPERACIÓN</p><h1 id="recovery-title">Guarda tus códigos</h1>
<p>Se muestran una sola vez. Cada código permite un acceso si pierdes tu autenticador. Guárdalos en un lugar seguro.</p>
<ul class="rv-recovery-list"><?php foreach ($codes as $code): ?><li><code><?= $this->text->e($code) ?></code></li><?php endforeach ?></ul>
<a class="btn btn-blue" href="<?= $this->text->e($returnUrl) ?>">Ya los guardé · Continuar</a>
</section></main>
