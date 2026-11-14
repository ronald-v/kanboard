<a class="rv-skip" href="#rv-content">Saltar al contenido</a>
<aside class="rv-sidebar" aria-label="Espacio rvSoftware">
    <a class="rv-brand" href="<?= $this->url->href('RvHomeController', 'show', array('plugin' => 'RvSoftware')) ?>"><img src="<?= $this->url->dir() ?>plugins/RvSoftware/Assets/logo-oscuro.png" alt="rvSoftware" width="225" height="42"></a>
    <p class="rv-workspace">EQUIPO DE DESARROLLO</p>
    <div class="rv-desktop-nav"><?= $this->render('RvSoftware:layout/navigation') ?></div>
    <details class="rv-mobile-nav"><summary>Menú de navegación</summary><?= $this->render('RvSoftware:layout/navigation') ?></details>
    <div class="rv-profile"><?= $this->text->e($this->user->getFullname()) ?></div>
</aside>
<span id="rv-content" tabindex="-1"></span>
