<a class="rv-skip" href="#rv-content">Saltar al contenido</a>
<aside class="rv-sidebar" aria-label="Espacio Projecta">
    <a class="rv-brand" href="<?= $this->url->href('RvHomeController', 'show', array('plugin' => 'RvSoftware')) ?>"><img src="<?= $this->url->dir() ?>assets/img/favicon.png?v=projecta-1" alt="" width="40" height="40"><span>Projecta<small>by rvSoftware</small></span></a>
    <p class="rv-workspace">EQUIPO DE DESARROLLO</p>
    <div class="rv-desktop-nav"><?= $this->render('RvSoftware:layout/navigation') ?></div>
    <details class="rv-mobile-nav"><summary>Menú de navegación</summary><?= $this->render('RvSoftware:layout/navigation') ?></details>
    <div class="rv-profile"><?= $this->text->e($this->user->getFullname()) ?></div>
</aside>
<span id="rv-content" tabindex="-1"></span>
