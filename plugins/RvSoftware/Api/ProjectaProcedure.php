<?php
namespace Kanboard\Plugin\RvSoftware\Api;

use Kanboard\Api\Authorization\ProjectAuthorization;
use Kanboard\Api\Authorization\TaskAuthorization;

class ProjectaProcedure extends \Kanboard\Api\Procedure\BaseProcedure
{
    private function page($offset, $limit)
    {
        if (! is_int($offset) || ! is_int($limit) || $offset<0 || $limit<1 || $limit>100) { throw new \InvalidArgumentException('Invalid pagination'); }
    }

    public function projectaListProjects($offset=0, $limit=50)
    {
        $this->page($offset,$limit);
        $ids=$this->userSession->isAdmin() ? array_column($this->db->table('projects')->columns('id')->findAll(),'id') : $this->projectPermissionModel->getProjectIds($this->userSession->getId());
        $query=$this->db->table('projects')->columns('id','name','description','is_active')->in('id',$ids ?: [-1])->asc('id');
        return ['items'=>$query->offset($offset)->limit($limit)->findAll(),'offset'=>$offset,'limit'=>$limit];
    }

    public function projectaListTasks($project_id, $offset=0, $limit=50)
    {
        $this->page($offset,$limit);
        ProjectAuthorization::getInstance($this->container)->check('TaskProcedure','getAllTasks',$project_id);
        return ['items'=>$this->taskFinderModel->getExtendedQuery()->eq('tasks.project_id',$project_id)->asc('tasks.id')->offset($offset)->limit($limit)->findAll(),'offset'=>$offset,'limit'=>$limit];
    }

    public function projectaListSubtasks($task_id, $offset=0, $limit=50)
    {
        $this->page($offset,$limit);
        TaskAuthorization::getInstance($this->container)->check('SubtaskProcedure','getAllSubtasks',$task_id);
        return ['items'=>$this->subtaskModel->getQuery()->eq('subtasks.task_id',$task_id)->asc('subtasks.position')->asc('subtasks.id')->offset($offset)->limit($limit)->findAll(),'offset'=>$offset,'limit'=>$limit];
    }
}
