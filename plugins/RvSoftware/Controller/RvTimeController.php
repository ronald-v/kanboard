<?php

namespace Kanboard\Plugin\RvSoftware\Controller;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Kanboard\Controller\BaseController;

class RvTimeController extends BaseController
{
    public function show($error = '')
    {
        $this->response->html($this->helper->layout->app('RvSoftware:time/index', array(
            'title' => 'Mi tiempo', 'error' => $error,
            'sessions' => $this->rvTimeModel->mine(),
            'subtasks' => $this->rvTimeModel->availableSubtasks(),
            'operationKey' => bin2hex(random_bytes(16)),
            'now' => (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d\TH:i:s'),
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $values = $this->request->getValues();
        try {
            $action = $values['operation'] ?? '';
            $userId = $this->userSession->getId();
            if ($action === 'start') {
                $this->rvTimeModel->start((int) ($values['subtask_id'] ?? 0), $userId);
            } elseif ($action === 'stop') {
                $this->rvTimeModel->stop((int) ($values['subtask_id'] ?? 0), $userId);
            } else {
                $input = $values['end_at'] ?? '';
                if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/D', $input)) { throw new DomainException('Revisa la fecha y hora de fin.'); }
                $date = DateTimeImmutable::createFromFormat(strlen($input) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s', $input, new DateTimeZone('America/Bogota'));
                $dateErrors = DateTimeImmutable::getLastErrors();
                $seconds = filter_var($values['duration_seconds'] ?? '', FILTER_VALIDATE_INT);
                if (! $date || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $seconds === false || $seconds < 0) { throw new DomainException('Revisa la fecha y la duración en segundos.'); }
                $end = $date->getTimestamp();
                if ($action === 'correct') {
                    $this->rvTimeModel->correct((int) ($values['session_id'] ?? 0), $end - $seconds, $end, $values['note'] ?? '', $values['reason'] ?? '', $values['operation_key'] ?? '');
                } elseif ($action === 'manual') {
                    $this->rvTimeModel->manual((int) ($values['subtask_id'] ?? 0), $end - $seconds, $end, $values['note'] ?? '', $values['operation_key'] ?? '');
                } else { throw new DomainException('Operación no válida.'); }
            }
            $this->response->redirect($this->helper->url->to('RvTimeController', 'show', array('plugin' => 'RvSoftware')));
        } catch (DomainException $error) {
            $this->show($error->getMessage());
        }
    }
}
