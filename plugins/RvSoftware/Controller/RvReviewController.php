<?php
namespace Kanboard\Plugin\RvSoftware\Controller;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Kanboard\Controller\BaseController;

class RvReviewController extends BaseController
{
    public function show($error = '')
    {
        $rows = $this->rvTimeModel->visible();
        foreach ($rows as &$row) { $row['can_review'] = $this->rvTimeModel->role($row['project_id'], $this->userSession->getId()) === 'project-manager'; }
        unset($row);
        $this->response->html($this->helper->layout->app('RvSoftware:time/review', array('title' => 'Revisión de tiempo', 'rows' => $rows, 'error' => $error)));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $values = $this->request->getValues();
        try {
            $billable = filter_var($values['billable'] ?? '', FILTER_VALIDATE_INT);
            if (! in_array($billable, array(0,1), true)) { throw new DomainException('Selecciona la clasificación.'); }
            if (($values['operation'] ?? '') === 'rectify') {
                $input = $values['end_at'] ?? '';
                if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/D', $input)) { throw new DomainException('La fecha no es válida.'); }
                $date = DateTimeImmutable::createFromFormat(strlen($input) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s', $input, new DateTimeZone('America/Bogota'));
                $errors = DateTimeImmutable::getLastErrors();
                $seconds = filter_var($values['duration_seconds'] ?? '', FILTER_VALIDATE_INT);
                if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $seconds === false || $seconds < 0) { throw new DomainException('Revisa fecha y duración.'); }
                $this->rvTimeModel->rectify((int) ($values['session_id'] ?? 0), $date->getTimestamp() - $seconds, $date->getTimestamp(), $billable, $values['note'] ?? '', $values['reason'] ?? '', $values['operation_key'] ?? '');
            } elseif (in_array($values['operation'] ?? '', array('approve','classify'), true)) {
                $this->rvTimeModel->review((int) ($values['session_id'] ?? 0), $billable, $values['operation'] === 'approve', $values['reason'] ?? '', $values['operation_key'] ?? '');
            } else { throw new DomainException('Operación no válida.'); }
            $this->response->redirect($this->helper->url->to('RvReviewController', 'show', array('plugin' => 'RvSoftware')));
        } catch (DomainException $error) { $this->show($error->getMessage()); }
    }
}
