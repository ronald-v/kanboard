<?php

namespace Kanboard\Plugin\RvSoftware\Model;

use Kanboard\Core\Base;

class RvDashboardModel extends Base
{
    public function projects($userId)
    {
        $statement = $this->db->getConnection()->prepare('SELECT p.* FROM projects p WHERE p.is_active=1
            AND (EXISTS (SELECT 1 FROM project_has_users u WHERE u.project_id=p.id AND u.user_id=?)
            OR EXISTS (SELECT 1 FROM project_has_groups g JOIN group_has_users m ON m.group_id=g.group_id WHERE g.project_id=p.id AND m.user_id=?)) ORDER BY p.name');
        $statement->execute(array((int)$userId,(int)$userId));
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function overview($userId)
    {
        $projects = $this->projects($userId);
        $ids = array_column($projects, 'id');
        $tasks = array();
        $total = 0;
        if ($ids) {
            $query = $this->taskFinderModel->getProjectUserOverviewQuery($ids, 1)->eq('tasks.owner_id', (int) $userId);
            $total = $query->count();
            $tasks = $this->taskFinderModel->getProjectUserOverviewQuery($ids, 1)
                ->eq('tasks.owner_id', (int) $userId)->asc('tasks.date_due')->limit(10)->findAll();
        }
        return array('projects' => $projects, 'tasks' => $tasks, 'task_count' => $total);
    }
}
