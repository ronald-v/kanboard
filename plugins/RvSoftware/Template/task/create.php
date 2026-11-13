<div class="page-header"><h2><?= $this->text->e($project['name']) ?> &gt; Nueva tarea</h2></div>
<form method="post" action="<?= $this->url->href('TaskCreationController', 'save', array('project_id' => $project['id'])) ?>" autocomplete="off" class="rv-task-form">
<?= $this->form->csrf() ?>
<?= $this->task->renderTitleField($values, $errors) ?>
<?= $this->task->renderAssigneeField($users_list, $values, $errors) ?>
<?= $this->hook->render('template:task:form:first-column', array('values' => $values, 'errors' => $errors)) ?>
<details class="rv-advanced" <?= $errors ? 'open' : '' ?>><summary>Campos avanzados</summary><div class="rv-form-grid">
<div><?= $this->task->renderDescriptionField($values, $errors), $this->task->renderDescriptionTemplateDropdown($project['id']), $this->task->renderTagField($project) ?></div>
<div><?= $this->task->renderColorField($values), $this->task->renderCategoryField($categories_list, $values, $errors), $this->task->renderSwimlaneField($swimlanes_list, $values, $errors), $this->task->renderColumnField($columns_list, $values, $errors), $this->task->renderPriorityField($project, $values) ?>
<?= $this->hook->render('template:task:form:second-column', array('values' => $values, 'errors' => $errors)) ?></div>
<div><?= $this->task->renderDueDateField($values, $errors), $this->task->renderStartDateField($values, $errors), $this->task->renderTimeEstimatedField($values, $errors), $this->task->renderTimeSpentField($values, $errors), $this->task->renderScoreField($values, $errors), $this->task->renderReferenceField($values, $errors) ?>
<?= $this->hook->render('template:task:form:third-column', array('values' => $values, 'errors' => $errors)) ?></div>
</div></details>
<details class="rv-advanced"><summary><?= t('Add attachments') ?></summary><?= $this->task->renderFileUpload($screenshot, $files) ?></details>
<?= $this->hook->render('template:task:form:bottom-before-buttons', array('values' => $values, 'errors' => $errors)) ?>
<?php if (! isset($duplicate)): ?><?= $this->form->checkbox('another_task', t('Create another task'), 1, isset($values['another_task']) && $values['another_task'] == 1), $this->form->checkbox('duplicate_multiple_projects', t('Duplicate to multiple projects'), 1, false) ?><?php endif ?>
<?= $this->modal->submitButtons() ?>
</form>
