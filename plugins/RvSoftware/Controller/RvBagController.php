<?php
namespace Kanboard\Plugin\RvSoftware\Controller;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Kanboard\Controller\BaseController;

class RvBagController extends BaseController
{
    public function show($error = '')
    {
        $projectId = $this->request->getIntegerParam('project_id');
        $projects = $this->rvBagModel->projects();
        if (! $projectId && $projects) { $projectId = (int)$projects[0]['id']; }
        $bags = array(); $client = null;
        try {
            if ($projectId) {
                $client = $this->rvBagModel->client($projectId);
                $bags = $this->rvBagModel->bags($projectId);
                foreach ($bags as &$bag) { $bag['entries'] = $this->rvBagModel->entries($bag['id']); }
                unset($bag);
            }
        } catch (DomainException $e) { $this->response->text('Acceso denegado',403); return; }
        $this->response->html($this->helper->layout->app('RvSoftware:bag/index',array('title'=>'Clientes y bolsas','projects'=>$projects,'projectId'=>$projectId,'client'=>$client,'clients'=>$this->rvBagModel->clients(),'bags'=>$bags,'error'=>$error)));
    }

    private function date($value, $exclusiveEnd = false)
    {
        if ($value === '') { return null; }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { throw new DomainException('Fecha no válida.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('America/Bogota'));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))) { throw new DomainException('Fecha no válida.'); }
        return ($exclusiveEnd ? $date->modify('+1 day') : $date)->getTimestamp();
    }

    private function hours($value)
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,6})(?:\.(\d{1,2}))?$/D',$value,$match)) { throw new DomainException('Indica horas positivas, con máximo dos decimales.'); }
        return (int)$match[1]*3600 + (int)str_pad($match[2] ?? '',2,'0')*36;
    }

    public function save()
    {
        $this->checkCSRFForm();
        $v = $this->request->getValues();
        try {
            switch ($v['operation'] ?? '') {
                case 'client': $this->rvBagModel->assignClient((int)($v['project_id'] ?? 0),$v['name'] ?? '',(int)($v['client_id'] ?? 0),$v['operation_key'] ?? ''); break;
                case 'bag': $this->rvBagModel->createBag((int)($v['project_id'] ?? 0),$v['name'] ?? '',$this->hours($v['hours'] ?? ''),$this->date($v['from'] ?? ''),$this->date($v['until'] ?? '',true),$v['operation_key'] ?? ''); break;
                case 'extend': $this->rvBagModel->amend((int)($v['bag_id'] ?? 0),$this->hours($v['hours'] ?? ''),$v['reason'] ?? '',$v['operation_key'] ?? ''); break;
                case 'archive': $this->rvBagModel->amend((int)($v['bag_id'] ?? 0),0,$v['reason'] ?? '',$v['operation_key'] ?? '',true); break;
                default: throw new DomainException('Operación no válida.');
            }
            $this->response->redirect($this->helper->url->to('RvBagController','show',array('plugin'=>'RvSoftware','project_id'=>(int)($v['project_id'] ?? 0))));
        } catch (DomainException $e) { $this->show($e->getMessage()); }
    }
}
