<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvActionModel extends \Kanboard\Model\ActionModel
{
    public function getAllByUser($user_id)
    {
        $statement = $this->db->getConnection()->prepare('SELECT a.*,ap.name AS parameter_name,ap.value AS parameter_value
            FROM actions a JOIN projects p ON p.id=a.project_id LEFT JOIN action_has_params ap ON ap.action_id=a.id
            WHERE p.is_active=1 AND (EXISTS (SELECT 1 FROM project_has_users u WHERE u.project_id=p.id AND u.user_id=?)
            OR EXISTS (SELECT 1 FROM project_has_groups g JOIN group_has_users m ON m.group_id=g.group_id WHERE g.project_id=p.id AND m.user_id=?))
            ORDER BY a.id,ap.id');
        $statement->execute(array($user_id,$user_id));
        $actions = array();
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $name = $row['parameter_name'];
            $value = $row['parameter_value'];
            unset($row['parameter_name'], $row['parameter_value']);
            if (! isset($actions[$row['id']])) { $actions[$row['id']] = $row + array('params'=>array()); }
            if ($name !== null) { $actions[$row['id']]['params'][$name] = $value; }
        }
        return array_values($actions);
    }
}
