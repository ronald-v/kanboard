<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvProjectUserRoleModel extends \Kanboard\Model\ProjectUserRoleModel
{
    public function getProjectsByUser($user_id, $status = array(\Kanboard\Model\ProjectModel::ACTIVE, \Kanboard\Model\ProjectModel::INACTIVE))
    {
        if (! $status) { return array(); }
        $sql = 'SELECT p.id,p.name FROM projects p WHERE p.is_active IN ('.implode(',', array_fill(0, count($status), '?')).')
            AND (EXISTS (SELECT 1 FROM project_has_users u WHERE u.project_id=p.id AND u.user_id=?)
            OR EXISTS (SELECT 1 FROM project_has_groups g JOIN group_has_users m ON m.group_id=g.group_id WHERE g.project_id=p.id AND m.user_id=?))';
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array_merge($status, array($user_id, $user_id)));
        $projects = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
        asort($projects);
        return $projects;
    }
}
