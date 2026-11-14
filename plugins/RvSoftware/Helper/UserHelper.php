<?php
namespace Kanboard\Plugin\RvSoftware\Helper;

class UserHelper extends \Kanboard\Helper\UserHelper
{
    private $listingGroups = null;

    public function renderListing($paginator, array $values)
    {
        $previous = $this->listingGroups;
        $collection = $paginator->getCollection();
        $paginator->setCollection($collection);
        $ids = array_column($collection, 'id');
        $this->listingGroups = array_fill_keys($ids, array());
        try {
            if ($ids && SHOW_GROUP_MEMBERSHIPS_IN_USERLIST) {
                $rows = $this->db->table('group_has_users')->columns('group_has_users.user_id', 'groups.name')
                    ->join('groups', 'id', 'group_id')->in('group_has_users.user_id', $ids)->asc('groups.name')->findAll();
                foreach ($rows as $row) { $this->listingGroups[$row['user_id']][] = $row['name']; }
            }
            return $this->template->render('kanboard:user_list/listing', array('paginator'=>$paginator, 'values'=>$values));
        } finally {
            // Presentation data lives only while this listing is being rendered.
            $this->listingGroups = $previous;
        }
    }

    public function getUsersGroupNames($userID)
    {
        if ($this->listingGroups === null || ! array_key_exists($userID, $this->listingGroups)) {
            return parent::getUsersGroupNames($userID);
        }
        $groups = $this->listingGroups[$userID];
        $limited = SHOW_GROUP_MEMBERSHIPS_IN_USERLIST_WITH_LIMIT > 0 ? array_slice($groups, 0, SHOW_GROUP_MEMBERSHIPS_IN_USERLIST_WITH_LIMIT) : $groups;
        return array('full_list'=>$groups, 'limited_list'=>$limited, 'has_groups'=>count($groups)>0, 'total'=>count($groups), 'shown'=>count($limited));
    }
}
