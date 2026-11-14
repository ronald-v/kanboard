<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvDashboardPagination extends \Kanboard\Pagination\DashboardPagination
{
    public function getOverview($userId)
    {
        // Preserve arbitrary per-project query hooks and native sort/page links.
        if ($this->request->getStringParam('pagination') !== '' || $this->hook->exists('pagination:dashboard:task:query') || ! $this->supportsWindows()) {
            return parent::getOverview($userId);
        }
        $projects = $this->projectUserRoleModel->getActiveProjectsByUser($userId);
        if (! $projects) { return array(); }
        $ranked = $this->rankedTasks($userId, array_keys($projects));
        if (! $ranked) { return array(); }
        $query = $this->taskFinderModel->getExtendedQuery()->in('tasks.id', array_column($ranked, 'id'));
        $tasks = $this->taskListSubtaskAssigneeFormatter->withUserId($userId)->withQuery($query)->format();
        $byId = array_column($tasks, null, 'id');
        $groups = array();
        $totals = array();
        foreach ($ranked as $row) {
            if (isset($byId[$row['id']])) { $groups[$row['project_id']][] = $byId[$row['id']]; }
            $totals[$row['project_id']] = (int)$row['rv_total'];
        }
        $result = array();
        foreach ($projects as $id=>$name) {
            if (empty($groups[$id])) { continue; }
            $paginator = $this->paginator
                ->setUrl('DashboardController','show',array('user_id'=>$userId,'pagination'=>'tasks-'.$id),'project-tasks-'.$id)
                ->setMax(15)->setOrder('tasks.priority')->setDirection('DESC')
                ->setTotal($totals[$id])->setCollection($groups[$id]);
            $result[] = array('project_id'=>$id,'project_name'=>$name,'paginator'=>$paginator);
        }
        return $result;
    }

    private function rankedTasks($userId, array $projectIds)
    {
        $query = $this->taskFinderModel->getUserQuery($userId)->in('tasks.project_id', $projectIds)
            ->columns('tasks.id','tasks.project_id')
            ->subquery('ROW_NUMBER() OVER (PARTITION BY tasks.project_id ORDER BY tasks.priority DESC)', 'rv_rank')
            ->subquery('COUNT(*) OVER (PARTITION BY tasks.project_id)', 'rv_total');
        $statement = $this->db->getConnection()->prepare('SELECT * FROM ('.$query->buildSelectQuery().') rv_ranked WHERE rv_rank<=15 ORDER BY project_id,rv_rank');
        $statement->execute($query->getConditionBuilder()->getValues());
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function supportsWindows()
    {
        $version = $this->db->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        if (DB_DRIVER === 'sqlite') { return version_compare($version,'3.25.0','>='); }
        if (DB_DRIVER !== 'mysql') { return false; }
        if (! preg_match('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+)/', $version, $match)) { return false; }
        return version_compare($match[1], stripos($version,'MariaDB') !== false ? '10.2.0' : '8.0.0', '>=');
    }
}
