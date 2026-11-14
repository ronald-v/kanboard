<main class="rv-auth"><section class="rv-auth-card"><h1>Credencial API creada</h1><p>Se muestra una sola vez. Guárdala en tu gestor de secretos; tiene vigencia de 90 días y conserva tus permisos.</p>
<code class="rv-token"><?= $this->text->e($apiToken) ?></code>
<p><?= $this->url->link('Volver a seguridad','RvAccessController','manage',['plugin'=>'RvSoftware']) ?></p></section></main>
