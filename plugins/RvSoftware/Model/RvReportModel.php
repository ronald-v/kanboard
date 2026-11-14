<?php
namespace Kanboard\Plugin\RvSoftware\Model;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

class RvReportModel extends RvBaseModel
{
    public function options()
    {
        $actor = $this->actor();
        $ids = $this->userSession->isAdmin() ? array_column($this->db->table('projects')->columns('id')->findAll(),'id') : $this->projectPermissionModel->getProjectIds($actor);
        $projects = $this->db->table('projects')->in('id',$ids ?: array(-1))->asc('name')->findAll();
        $users = array($actor=>$this->userModel->getById($actor));
        foreach ($this->rvTimeModel->visible() as $row) { if (! isset($users[$row['user_id']])) { $users[$row['user_id']] = $this->userModel->getById($row['user_id']); } }
        $choices = array('project_id'=>array(),'user_id'=>array(),'client_id'=>array(),'bag_id'=>array());
        foreach ($projects as $project) {
            $choices['project_id'][$project['id']] = $project['name'];
            if ($this->role($project['id'],$actor) === 'project-manager') {
                foreach ($this->rvBagModel->bags($project['id']) as $bag) { $choices['bag_id'][$bag['id']] = $project['name'].' / '.$bag['name']; }
            }
        }
        foreach ($users as $user) { $choices['user_id'][$user['id']] = $user['username']; }
        foreach ($this->rvBagModel->clients() as $client) { $choices['client_id'][$client['id']] = $client['name']; }
        return $choices;
    }
    private function day($value)
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { throw new DomainException('Usa fechas válidas AAAA-MM-DD.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('America/Bogota'));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))) { throw new DomainException('Fecha no válida.'); }
        return $date;
    }

    public function report($from, $until, array $filters = array())
    {
        $actor = $this->actor();
        $begin = $this->day($from)->getTimestamp();
        $finish = $this->day($until)->modify('+1 day')->getTimestamp();
        if ($finish <= $begin) { throw new DomainException('El fin debe ser igual o posterior al inicio.'); }
        $where = array('t.`end`>0','t.`start`<?','t.`end`>?');
        $params = array($finish,$begin);
        if (! $this->userSession->isAdmin()) {
            $ids = $this->projectPermissionModel->getProjectIds($actor) ?: array(-1);
            $leaders = $this->rvPrivacyModel->leaderProjectIds() ?: array(-1);
            $where[] = 'k.project_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';
            $params = array_merge($params,$ids);
            $where[] = '(t.user_id=? OR k.project_id IN ('.implode(',',array_fill(0,count($leaders),'?')).'))';
            $params = array_merge($params,array($actor),$leaders);
        }
        foreach (array('project_id'=>'k.project_id','user_id'=>'t.user_id','bag_id'=>'m.bag_id','client_id'=>'pc.client_id') as $key=>$column) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== 0 && $filters[$key] !== '0') {
                if (filter_var($filters[$key],FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1))) === false) { throw new DomainException('Filtro no válido.'); }
                if ($key === 'project_id' && ! $this->role($filters[$key],$actor)) { throw new DomainException('Proyecto no accesible.'); }
                if ($key === 'bag_id') { $this->rvBagModel->bag($filters[$key]); }
                if ($key === 'client_id' && ! in_array((int)$filters[$key],array_map('intval',array_column($this->rvBagModel->clients(),'id')),true)) { throw new DomainException('Cliente no accesible.'); }
                $where[] = $column.'=?'; $params[] = (int)$filters[$key];
            }
        }
        foreach (array('status'=>array('pending','approved'),'billable'=>array('0','1')) as $key=>$allowed) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                if (! in_array((string)$filters[$key],$allowed,true)) { throw new DomainException('Estado o clasificación no válido.'); }
                $where[] = 'm.'.$key.'=?'; $params[] = (string)$filters[$key];
            }
        }
        $rows = $this->sql('SELECT t.id,t.user_id,t.`start`,t.`end`,m.duration_seconds,m.note,m.status,m.billable,m.version,
            m.bag_id,k.id AS task_id,k.title AS task_title,k.project_id,p.name AS project_name,s.title AS subtask_title,
            u.username,c.name AS client_name,b.name AS bag_name,
            COALESCE((SELECT -SUM(e.seconds) FROM rv_bag_entries e WHERE e.session_id=t.id),0) AS consumed_session_seconds
            FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id JOIN subtasks s ON s.id=t.subtask_id
            JOIN tasks k ON k.id=s.task_id JOIN projects p ON p.id=k.project_id JOIN users u ON u.id=t.user_id
            LEFT JOIN rv_project_clients pc ON pc.project_id=k.project_id LEFT JOIN rv_clients c ON c.id=pc.client_id
            LEFT JOIN rv_bags b ON b.id=m.bag_id WHERE '.implode(' AND ',$where).' ORDER BY t.`start`,t.id',$params)->fetchAll(PDO::FETCH_ASSOC);
        $totals = array('worked_seconds'=>0,'billable_seconds'=>0,'approved_seconds'=>0,'consumed_seconds'=>0,'unreconciled_sessions'=>0);
        foreach ($rows as &$row) {
            $row['worked_seconds'] = max(0,min((int)$row['end'],$finish)-max((int)$row['start'],$begin));
            $row['billable_seconds'] = (int)$row['billable'] === 1 ? $row['worked_seconds'] : 0;
            $row['approved_seconds'] = $row['status'] === 'approved' ? $row['worked_seconds'] : 0;
            $expected = $row['status'] === 'approved' && (int)$row['billable'] === 1 ? (int)$row['duration_seconds'] : 0;
            $row['reconciled'] = $expected === (int)$row['consumed_session_seconds'] && (! $expected || $row['bag_id']);
            $row['consumed_seconds'] = $row['reconciled'] && $expected > 0 ? $row['worked_seconds'] : 0;
            if (! $row['reconciled']) { $totals['unreconciled_sessions']++; }
            foreach (array('worked_seconds','billable_seconds','approved_seconds','consumed_seconds') as $metric) { $totals[$metric] += $row[$metric]; }
            if ($this->role($row['project_id'],$actor) !== 'project-manager') {
                // Member reports expose own work; commercial client/bag details
                // and balances remain leader-only (BR-08).
                $row['client_name'] = null; $row['bag_name'] = null; $row['bag_id'] = null;
            }
        }
        unset($row);
        return array('timezone'=>'America/Bogota','from'=>$from,'until'=>$until,'totals'=>$totals,'rows'=>$rows,'history_note'=>'Históricos opacos sin sesiones se excluyen; no representan cero trabajo. Correcciones posteriores actualizan este reporte actual y conservan auditoría.');
    }

    public function safeCell($value)
    {
        $value = (string)$value;
        return preg_match('/^[\x00-\x20]*[=+@-]/u',$value) || preg_match('/^[\t\r\n]/',$value) ? "'".$value : $value;
    }

    public function csv(array $report)
    {
        $data = array(array('Sesion','Version','Proyecto','Tarea','Subtarea','Usuario','Inicio America/Bogota','Fin America/Bogota','Trabajado s en periodo','Facturable s en periodo','Aprobado s en periodo','Consumido s en periodo','Estado','Clasificacion','Cliente','Bolsa','Nota','Conciliado'));
        foreach ($report['rows'] as $row) {
            $date = function ($timestamp) { return (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s'); };
            $data[] = array_map(array($this,'safeCell'),array($row['id'],$row['version'],$row['project_name'],$row['task_title'],$row['subtask_title'],$row['username'],$date($row['start']),$date($row['end']),$row['worked_seconds'],$row['billable_seconds'],$row['approved_seconds'],$row['consumed_seconds'],$row['status'],$row['billable'] === null ? 'sin clasificar' : ((int)$row['billable'] === 1 ? 'facturable' : 'no facturable'),$row['client_name'],$row['bag_name'],$row['note'],$row['reconciled'] ? 'si' : 'no'));
        }
        return $data;
    }
}
