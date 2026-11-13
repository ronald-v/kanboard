<nav class="rv-nav" aria-label="Navegación principal">
    <?= $this->url->link('Inicio', 'RvHomeController', 'show', array('plugin' => 'RvSoftware')) ?>
    <?= $this->url->link('Proyectos', 'RvHomeController', 'projects', array('plugin' => 'RvSoftware')) ?>
    <?= $this->url->link('Mi tiempo', 'RvTimeController', 'show', array('plugin' => 'RvSoftware')) ?>
    <?= $this->url->link('Clientes y bolsas', 'RvBagController', 'show', array('plugin' => 'RvSoftware')) ?>
    <?= $this->hook->render('template:rvsoftware:navigation') ?>
    <details class="rv-tools"><summary>Más herramientas</summary>
        <?= $this->url->link('Dashboard completo', 'DashboardController', 'show') ?>
        <?= $this->url->link('Listado de proyectos', 'ProjectListController', 'show') ?>
        <?= $this->url->link('Mi actividad', 'ActivityController', 'user', array('user_id' => $this->user->getId())) ?>
        <?php if ($this->user->hasAccess('ConfigController', 'index')): ?>
            <?= $this->url->link('Administración', 'ConfigController', 'index') ?>
        <?php endif ?>
    </details>
</nav>
