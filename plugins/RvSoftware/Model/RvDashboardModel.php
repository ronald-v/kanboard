<?php

namespace Kanboard\Plugin\RvSoftware\Model;

use Kanboard\Core\Base;

class RvDashboardModel extends Base
{
    public function projects($userId)
    {
        $ids = $this->projectPermissionModel->getActiveProjectIds((int) $userId);
        return $ids ? $this->db->table('projects')->in('id', $ids)->asc('name')->findAll() : array();
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
