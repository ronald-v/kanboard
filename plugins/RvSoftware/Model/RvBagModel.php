<?php
namespace Kanboard\Plugin\RvSoftware\Model;

use DomainException;
use PDO;

class RvBagModel extends RvBaseModel
{
    public function authorize($projectId)
    {
        $actor = $this->actor();
        if (! $this->db->table('projects')->eq('id', $projectId)->exists() || $this->role($projectId, $actor) !== 'project-manager') {
            throw new DomainException('Solo el líder del proyecto o administrador accede a sus bolsas.');
        }
        return $actor;
    }

    public function projects()
    {
        $ids = $this->rvPrivacyModel->leaderProjectIds();
        return $this->db->table('projects')->in('id', $ids ?: array(-1))->asc('name')->findAll();
    }

    public function bags($projectId)
    {
        $this->authorize($projectId);
        return $this->sql('SELECT b.*,COALESCE((SELECT SUM(e.seconds) FROM rv_bag_entries e WHERE e.bag_id=b.id),0) AS balance_seconds FROM rv_bags b WHERE b.project_id=? ORDER BY b.id DESC', array($projectId))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function client($projectId)
    {
        $this->authorize($projectId);
        return $this->sql('SELECT c.* FROM rv_clients c JOIN rv_project_clients p ON p.client_id=c.id WHERE p.project_id=?', array($projectId))->fetch(PDO::FETCH_ASSOC);
    }

    public function clients()
    {
        $this->actor();
        if ($this->userSession->isAdmin()) { return $this->db->table('rv_clients')->asc('name')->findAll(); }
        $ids = $this->rvPrivacyModel->leaderProjectIds() ?: array(-1);
        return $this->sql('SELECT DISTINCT c.* FROM rv_clients c JOIN rv_project_clients p ON p.client_id=c.id WHERE p.project_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY c.name',$ids)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bag($id)
    {
        $row = $this->db->table('rv_bags')->eq('id', $id)->findOne();
        if (! $row) { throw new DomainException('No tienes acceso a esta bolsa.'); }
        $this->authorize($row['project_id']);
        return $row;
    }

    public function entries($bagId)
    {
        $this->bag($bagId);
        return $this->db->table('rv_bag_entries')->eq('bag_id', $bagId)->asc('id')->findAll();
    }

    public function commercialAudit($project, $action, $before, $after, $reason, $key, $hash)
    {
        $this->sql('INSERT INTO rv_commercial_audit(project_id,actor_id,action,reason,before_json,after_json,created_at,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?)', array($project,$this->actor(),$action,$reason,json_encode($before),json_encode($after),time(),$key,$hash));
    }

    private function replay($key, $hash)
    {
        $row = $this->db->table('rv_commercial_audit')->eq('operation_key', $key)->findOne();
        if ($row && ! hash_equals($row['request_hash'], $hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
        return $row ? json_decode($row['after_json'], true) : null;
    }

    public function assignClient($projectId, $name, $clientId, $key)
    {
        $actor = $this->authorize($projectId);
        $name = $this->text($name, 200);
        $key = $this->operationKey('client', $actor, $key);
        $hash = hash('sha256', json_encode(array((int) $projectId,$name,(int) $clientId)));
        return $this->atomic($actor, function () use ($projectId,$name,$clientId,$key,$hash) {
            if ($done = $this->replay($key, $hash)) { return (int) $done['client_id']; }
            $before = $this->client($projectId);
            if ($this->db->table('rv_bags')->eq('project_id', $projectId)->exists()) { throw new DomainException('El cliente se conserva al existir bolsas. Crea otro proyecto para otro cliente.'); }
            if ($clientId) {
                $visible = $this->userSession->isAdmin() || $this->sql('SELECT COUNT(*) FROM rv_project_clients WHERE client_id=? AND project_id IN ('.implode(',',array_fill(0,max(1,count($this->rvPrivacyModel->leaderProjectIds())),'?')).')', array_merge(array($clientId), $this->rvPrivacyModel->leaderProjectIds() ?: array(-1)))->fetchColumn();
                if (! $visible || ! $this->db->table('rv_clients')->eq('id', $clientId)->exists()) { throw new DomainException('Cliente no accesible.'); }
            } else {
                if (! $name) { throw new DomainException('Indica el nombre del cliente.'); }
                $this->sql('INSERT INTO rv_clients(name,created_at) VALUES (?,?)', array($name,time()));
                $clientId = (int) $this->db->getConnection()->lastInsertId();
            }
            if ($before) { $this->sql('UPDATE rv_project_clients SET client_id=? WHERE project_id=?', array($clientId,$projectId)); }
            else { $this->sql('INSERT INTO rv_project_clients(project_id,client_id) VALUES (?,?)', array($projectId,$clientId)); }
            $this->commercialAudit($projectId,'assign_client',$before,array('client_id' => $clientId),'Asignación de cliente',$key,$hash);
            return (int) $clientId;
        }, 0, $projectId);
    }

    public function seconds($value, $positive = true)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || abs((float) $value) > 2147483647 || ($positive && $value <= 0)) { throw new DomainException('La cantidad de segundos no es válida.'); }
        return (int) $value;
    }

    public function createBag($projectId, $name, $seconds, $from, $until, $key)
    {
        $actor = $this->authorize($projectId);
        $name = $this->text($name,200);
        $seconds = $this->seconds($seconds);
        foreach (array($from,$until) as $date) { if ($date !== null && (filter_var($date,FILTER_VALIDATE_INT) === false || $date <= 0)) { throw new DomainException('Vigencia no válida.'); } }
        if (! $name || ($from !== null && $until !== null && $until <= $from)) { throw new DomainException('Revisa nombre y vigencia.'); }
        $key = $this->operationKey('bag', $actor, $key);
        $hash = hash('sha256', json_encode(array((int)$projectId,$name,$seconds,$from,$until)));
        return $this->atomic($actor, function () use ($projectId,$name,$seconds,$from,$until,$key,$hash) {
            if ($done = $this->replay($key,$hash)) { return (int) $done['bag_id']; }
            if (! $this->client($projectId)) { throw new DomainException('Asigna primero un cliente al proyecto.'); }
            $this->sql('INSERT INTO rv_bags(project_id,name,valid_from,valid_until,created_at) VALUES (?,?,?,?,?)',array($projectId,$name,$from,$until,time()));
            $id = (int) $this->db->getConnection()->lastInsertId();
            $this->entry($id,$seconds,'grant',null,null,'Capacidad inicial',$key,$hash);
            $this->commercialAudit($projectId,'create_bag',null,array('bag_id'=>$id),'Creación de bolsa',$key,$hash);
            return $id;
        }, 0, $projectId);
    }

    public function entry($bagId,$seconds,$kind,$session,$version,$reason,$key,$hash)
    {
        $this->sql('INSERT INTO rv_bag_entries(bag_id,seconds,kind,session_id,session_version,actor_id,reason,created_at,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?)',array($bagId,$seconds,$kind,$session,$version,$this->actor(),$reason,time(),$key,$hash));
    }

    public function amend($bagId, $seconds, $reason, $key, $archive = false)
    {
        $before = $this->bag($bagId);
        $actor = $this->actor();
        $seconds = $archive ? 0 : $this->seconds($seconds);
        $reason = $this->text($reason);
        if (! $reason) { throw new DomainException('Indica el motivo.'); }
        $key = $this->operationKey('amend', $actor, $key);
        $hash = hash('sha256',json_encode(array((int)$bagId,$seconds,$reason,(bool)$archive)));
        return $this->atomic($actor, function () use ($bagId,$seconds,$reason,$key,$hash,$archive) {
            if ($done = $this->replay($key,$hash)) { return (int) $done['id']; }
            $before = $this->bag($bagId);
            if ($before['archived']) { throw new DomainException('La bolsa está archivada; conserva su historia.'); }
            if ($archive) { $this->sql('UPDATE rv_bags SET archived=1 WHERE id=?',array($bagId)); }
            else { $this->entry($bagId,$seconds,'extension',null,null,$reason,$key,$hash); }
            $this->commercialAudit($before['project_id'],$archive ? 'archive_bag' : 'extend_bag',$before,$this->bag($bagId),$reason,$key,$hash);
            return (int) $bagId;
        }, 0, $before['project_id']);
    }
}
