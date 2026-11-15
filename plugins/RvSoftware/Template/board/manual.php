<?php
// Keep the control inside the node replaced by native AJAX board refreshes.
$__template_args['board_private_refresh_interval'] = 0;
$board = $this->render('kanboard:board/table_container', $__template_args);
$opening = strpos($board, '>');
if ($opening === false) { throw new \RuntimeException('Invalid native board container'); }
echo substr($board, 0, $opening + 1);
?>
<p class="rv-board-refresh"><a class="btn" href="<?= !empty($not_editable)
    ? $this->url->href('BoardViewController', 'readonly', ['token' => $this->request->getStringParam('token')])
    : $this->url->href('BoardViewController', 'show', ['project_id' => $project['id']]) ?>">Actualizar</a>
    <span>Los cambios de otros usuarios aparecen al actualizar.</span></p>
<?php echo substr($board, $opening + 1); ?>
