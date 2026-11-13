<section class="rv-home" aria-labelledby="rv-title">
    <div class="rv-page-heading"><div><p class="rv-eyebrow">MI ESPACIO DE TRABAJO</p><h1 id="rv-title">Tu trabajo, en un solo lugar.</h1><p>Prioriza lo que sigue y mantén tu tiempo al día.</p></div>
    <?= $this->url->link('Ver proyectos', 'RvHomeController', 'projects', array('plugin' => 'RvSoftware'), false, 'rv-button') ?></div>
    <div class="rv-stats"><article><p>Proyectos activos</p><strong><?= count($overview['projects']) ?></strong><small>En los que participas</small></article><article><p>Mis tareas pendientes</p><strong><?= (int) $overview['task_count'] ?></strong><small>De tus proyectos activos</small></article><article><p>Mi tiempo</p><strong>Sesiones</strong><?= $this->url->link('Consultar mis registros', 'RvTimeController', 'show', array('plugin' => 'RvSoftware')) ?></article></div>
    <article class="rv-panel"><h2>Mis próximas tareas</h2>
        <?php if (! $overview['tasks']): ?><p class="rv-empty">Aún no tienes tareas asignadas en tus proyectos activos.</p><?php endif ?>
        <?php foreach ($overview['tasks'] as $task): ?><div class="rv-task-row"><div><?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?><small><?= $this->text->e($task['project_name']) ?></small></div><span><?= $this->text->e($task['column_name']) ?></span><span><?= $task['date_due'] ? $this->dt->date($task['date_due']) : 'Sin vencimiento' ?></span></div><?php endforeach ?>
    </article>
    <article class="rv-panel"><h2>Proyectos a mano</h2><div class="rv-projects">
        <?php foreach (array_slice($overview['projects'], 0, 6) as $project): ?><article><h3><?= $this->url->link($this->text->e($project['name']), 'BoardViewController', 'show', array('project_id' => $project['id'])) ?></h3><?= $this->url->link('Abrir tablero →', 'BoardViewController', 'show', array('project_id' => $project['id'])) ?></article><?php endforeach ?>
        <?php if (! $overview['projects']): ?><p class="rv-empty">Cuando participes en un proyecto, aparecerá aquí.</p><?php endif ?>
    </div></article>
</section>
