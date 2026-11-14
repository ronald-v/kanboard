<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvPrivacyModel extends RvBaseModel
{
    private function systemAccess()
    {
        return ! $this->userSession->isLogged() && (session_get('scope') === 'API' || PHP_SAPI === 'cli');
    }

    public function lead($projectId)
    {
        return $this->systemAccess() || ($this->userSession->isLogged() && $this->role($projectId, $this->userSession->getId()) === 'project-manager');
    }

    public function leaderProjectIds()
    {
        if ($this->systemAccess() || $this->userSession->isAdmin()) {
            return array_column($this->db->table('projects')->columns('id')->findAll(), 'id');
        }
        $id = $this->userSession->getId();
        // Match native precedence: a direct role overrides all group roles.
        // One fresh query avoids 2+2N round trips, without caching permissions.
        $rows = $this->sql(
            "SELECT p.id, u.role AS direct_role, EXISTS (
                SELECT 1 FROM project_has_groups cg JOIN group_has_users cm ON cm.group_id=cg.group_id
                WHERE cg.project_id=p.id AND cm.user_id=? AND cg.role NOT IN ('project-manager','project-member','project-viewer')
             ) AS custom_roles FROM projects p LEFT JOIN project_has_users u ON u.project_id=p.id AND u.user_id=?
             WHERE u.role='project-manager' OR ((u.user_id IS NULL OR u.role='') AND EXISTS (
                SELECT 1 FROM project_has_groups g JOIN group_has_users m ON m.group_id=g.group_id
                WHERE g.project_id=p.id AND m.user_id=? AND g.role='project-manager'))",
            array($id, $id, $id)
        )->fetchAll(\PDO::FETCH_ASSOC);
        $leaders = array();
        foreach ($rows as $row) {
            // Custom roles can tie with manager in the native hierarchy. Resolve
            // those uncommon cases through the original authorization model.
            if ($row['direct_role'] === 'project-manager' || ! $row['custom_roles'] || $this->role($row['id'], $id) === 'project-manager') {
                $leaders[] = (int) $row['id'];
            }
        }
        return $leaders;
    }

    public function task(array $row)
    {
        if ($row && ! $this->lead($row['project_id'])) {
            $row['time_spent'] = $this->sql('SELECT COALESCE(SUM(t.time_spent),0) FROM subtask_time_tracking t JOIN subtasks s ON s.id=t.subtask_id WHERE s.task_id=? AND t.user_id=?', array($row['id'], $this->userSession->getId()))->fetchColumn();
        }
        return $row;
    }

    public function subtask(array $row)
    {
        if ($row && ! $this->lead($this->subtaskModel->getProjectId($row['id']))) {
            $row['time_spent'] = $this->sql('SELECT COALESCE(SUM(time_spent),0) FROM subtask_time_tracking WHERE subtask_id=? AND user_id=?', array($row['id'], $this->userSession->getId()))->fetchColumn();
        }
        return $row;
    }

    public function decorate($query, $kind)
    {
        if ($this->systemAccess() || $this->userSession->isAdmin()) { return $query; }
        // PicoDB subquery() accepts SQL only, like native getTimerQuery(). These
        // literals are server-side integer IDs, never request strings.
        $actor = (int) $this->userSession->getId();
        $leaders = implode(',', array_map('intval', $this->leaderProjectIds())) ?: '-1';
        if ($kind === 'task') {
            $expression = "CASE WHEN tasks.project_id IN ($leaders) THEN tasks.time_spent ELSE (SELECT COALESCE(SUM(rt.time_spent),0) FROM subtask_time_tracking rt JOIN subtasks rs ON rs.id=rt.subtask_id WHERE rs.task_id=tasks.id AND rt.user_id=$actor) END";
        } else {
            $expression = "CASE WHEN (SELECT rk.project_id FROM tasks rk WHERE rk.id=subtasks.task_id) IN ($leaders) THEN subtasks.time_spent ELSE (SELECT COALESCE(SUM(rt.time_spent),0) FROM subtask_time_tracking rt WHERE rt.subtask_id=subtasks.id AND rt.user_id=$actor) END";
        }
        return $query->subquery($expression, 'time_spent');
    }
}
