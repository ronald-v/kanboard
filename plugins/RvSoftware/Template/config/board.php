<div class="page-header"><h2><?= t('Board settings') ?></h2></div>
<p>Los tableros se actualizan manualmente. Usa «Actualizar» para ver cambios de otros usuarios.</p>
<form method="post" action="<?= $this->url->href('ConfigController', 'save', ['redirect' => 'board']) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <fieldset>
        <?= $this->form->label(t('Task highlight period'), 'board_highlight_period') ?>
        <?= $this->form->number('board_highlight_period', $values, $errors) ?>
        <p class="form-help"><?= t('Period (in second) to consider a task was modified recently (0 to disable, 2 days by default)') ?></p>
    </fieldset>
    <?= $this->hook->render('template:config:board', ['values' => $values, 'errors' => $errors]) ?>
    <div class="form-actions"><button type="submit" class="btn btn-blue"><?= t('Save') ?></button></div>
</form>
