<?php
namespace Kanboard\Plugin\RvSoftware\Model;

use DomainException;
use PDO;

class RvLedgerModel extends RvBaseModel
{
    // Called inside the session owner's + task + project transaction. No second
    // work record: entries reference the native session and metadata version.
    public function apply(array $row, $bagId, $rectifying = false)
    {
        $projectId = $this->subtaskModel->getProjectId($row['subtask_id']);
        $existing = $row['bag_id'] ? (int)$row['bag_id'] : null;
        if ($existing && $bagId && (int)$bagId !== $existing) { throw new DomainException('La bolsa de una sesión con historia se conserva.'); }
        $bagId = $existing ?: ($bagId ? (int)$bagId : null);
        $desired = $row['status'] === 'approved' && (int)$row['billable'] === 1 ? -(int)$row['duration_seconds'] : 0;
        if ($bagId) {
            $bag = $this->rvBagModel->bag($bagId);
            if ((int)$bag['project_id'] !== (int)$projectId) { throw new DomainException('La bolsa debe pertenecer al proyecto de la sesión.'); }
        }
        if ($row['status'] === 'approved' && (int)$row['billable'] === 1) {
            if (! $bagId) { throw new DomainException('Selecciona la bolsa para aprobar trabajo facturable.'); }
            if ($bag['archived'] && ! ($rectifying && $existing)) { throw new DomainException('No se aprueba trabajo nuevo en bolsas archivadas.'); }
            if (($bag['valid_from'] !== null && (int)$row['start'] < (int)$bag['valid_from']) || ($bag['valid_until'] !== null && (int)$row['end'] > (int)$bag['valid_until'])) {
                throw new DomainException('La sesión debe estar dentro de la vigencia. Divide el registro si abarca periodos diferentes.');
            }
        }
        if (! $bagId || $row['status'] !== 'approved') { return; }
        $actual = (int)$this->sql('SELECT COALESCE(SUM(seconds),0) FROM rv_bag_entries WHERE session_id=?',array($row['id']))->fetchColumn();
        $delta = $desired - $actual;
        $this->sql('UPDATE rv_time_meta SET bag_id=? WHERE session_id=?',array($bagId,$row['id']));
        $this->rvBagModel->entry($bagId,$delta,$rectifying ? 'compensation' : 'consumption',$row['id'],$row['version'],$rectifying ? 'Rectificación auditada de sesión' : 'Aprobación de trabajo facturable','origin:'.$row['id'].':'.$row['version'],hash('sha256',json_encode(array($bagId,$desired,$actual))));
    }

    public function adjust($bagId,$seconds,$reason,$key)
    {
        $bag = $this->rvBagModel->bag($bagId);
        $actor = $this->actor();
        $seconds = $this->rvBagModel->seconds($seconds,false);
        $reason = $this->text($reason);
        if (! $reason || ! $seconds) { throw new DomainException('Indica cantidad distinta de cero y motivo del ajuste.'); }
        $key = $this->operationKey('adjust',$actor,$key);
        $hash = hash('sha256',json_encode(array((int)$bagId,$seconds,$reason)));
        return $this->atomic($actor,function () use ($bagId,$seconds,$reason,$key,$hash) {
            $old = $this->db->table('rv_bag_entries')->eq('operation_key',$key)->findOne();
            if ($old) {
                if (! hash_equals($old['request_hash'],$hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
                return (int)$old['id'];
            }
            $this->rvBagModel->bag($bagId);
            $this->rvBagModel->entry($bagId,$seconds,'adjustment',null,null,$reason,$key,$hash);
            return (int)$this->db->getConnection()->lastInsertId();
        },0,$bag['project_id']);
    }

    public function reconcile($projectId)
    {
        $this->rvBagModel->authorize($projectId);
        $rows = $this->sql("SELECT t.id,m.bag_id,m.status,m.billable,m.duration_seconds,
            COALESCE((SELECT SUM(e.seconds) FROM rv_bag_entries e WHERE e.session_id=t.id),0) AS ledger_seconds
            FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id
            JOIN subtasks s ON s.id=t.subtask_id JOIN tasks k ON k.id=s.task_id WHERE k.project_id=?",array($projectId))->fetchAll(PDO::FETCH_ASSOC);
        $differences = array();
        foreach ($rows as $row) {
            $expected = $row['status'] === 'approved' && (int)$row['billable'] === 1 ? -(int)$row['duration_seconds'] : 0;
            if ($expected !== (int)$row['ledger_seconds'] || ($row['status'] === 'approved' && (int)$row['billable'] === 1 && ! $row['bag_id'])) {
                $row['expected_seconds'] = $expected; $differences[] = $row;
            }
        }
        return array('bags'=>$this->rvBagModel->bags($projectId),'differences'=>$differences);
    }
}
