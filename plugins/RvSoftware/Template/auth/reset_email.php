<p><?= t('To reset your password click on this link:') ?></p>
<?php $resetUrl=$this->url->to('PasswordResetController','change',['token'=>$token],'',true) ?>
<p><a href="<?= $this->text->e($resetUrl) ?>">Restablecer contraseña de Projecta</a></p>
<hr><p>Projecta · rvSoftware</p>
