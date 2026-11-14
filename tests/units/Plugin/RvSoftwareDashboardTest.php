<?php

namespace KanboardTests\units\Plugin;

use Kanboard\Core\Security\Role;

class RvSoftwareDashboardTest extends RvSoftwareBase
{
    public function testMembershipAndRevocationScopeProjectsAndTasks()
    {
        $c = $this->container;
        $user = $c['userModel']->create(array('username' => 'synthetic-member'));
        $project = $c['projectModel']->create(array('name' => 'Allowed'));
        $other = $c['projectModel']->create(array('name' => 'Forbidden'));
        $c['projectUserRoleModel']->addUser($project, $user, Role::PROJECT_MEMBER);
        $c['taskCreationModel']->create(array('title' => 'Assigned', 'project_id' => $project, 'owner_id' => $user));
        $c['taskCreationModel']->create(array('title' => 'Foreign', 'project_id' => $other, 'owner_id' => $user));
        $overview = $c['rvDashboardModel']->overview($user);
        $this->assertCount(1, $overview['projects']);
        $this->assertEquals(1, $overview['task_count']);
        $this->assertEquals('Assigned', $overview['tasks'][0]['title']);
        $c['projectUserRoleModel']->removeUser($project, $user);
        $overview = $c['rvDashboardModel']->overview($user);
        $this->assertSame(array(), $overview['projects']);
        $this->assertSame(array(), $overview['tasks']);
        $this->assertEquals(0, $overview['task_count']);
    }

    public function testEmptyStateAndNativeViewerRestrictions()
    {
        $this->assertSame(array(), $this->container['rvDashboardModel']->overview(1)['projects']);
        $acl = $this->container['projectAuthorization'];
        $this->assertFalse($acl->isAllowed('TaskCreationController', 'show', Role::PROJECT_VIEWER));
        $this->assertTrue($acl->isAllowed('TaskCreationController', 'show', Role::PROJECT_MEMBER));
    }
}
