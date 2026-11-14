<?php
namespace KanboardTests\units\Plugin;

use Kanboard\Core\Security\Role;

class RvSoftwarePerformanceTest extends RvSoftwareBase
{
    public function testBulkLeadershipPreservesDirectPrecedenceAndImmediateRevocation()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'performance-leader']);
        $other = $c['userModel']->create(['username'=>'performance-other']);
        $project = $c['projectModel']->create(['name'=>'Group leadership']);
        $group = $c['groupModel']->create('Synthetic managers');
        $c['groupMemberModel']->addUser($group,$user);
        $c['projectGroupRoleModel']->addGroup($project,$group,Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById($user));
        $this->assertContains($project,$c['rvPrivacyModel']->leaderProjectIds());
        $c['projectUserRoleModel']->addUser($project,$user,Role::PROJECT_VIEWER);
        $this->assertNotContains($project,$c['rvPrivacyModel']->leaderProjectIds());
        $c['projectUserRoleModel']->removeUser($project,$user);
        $this->assertContains($project,$c['rvPrivacyModel']->leaderProjectIds());
        $c['projectGroupRoleModel']->removeGroup($project,$group);
        $this->assertNotContains($project,$c['rvPrivacyModel']->leaderProjectIds());
        $c['userSession']->initialize($c['userModel']->getById($other));
        $this->assertSame([],$c['rvPrivacyModel']->leaderProjectIds());
    }

    public function testCustomGroupRoleTiesUseNativeAuthorization()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'performance-custom']);
        $project = $c['projectModel']->create(['name'=>'Custom group precedence']);
        $custom = $c['groupModel']->create('Synthetic custom');
        $managers = $c['groupModel']->create('Synthetic managers');
        $c['projectRoleModel']->create($project, 'Custom restricted');
        foreach ([$custom, $managers] as $group) { $c['groupMemberModel']->addUser($group, $user); }
        $c['projectGroupRoleModel']->addGroup($project, $custom, 'Custom restricted');
        $c['projectGroupRoleModel']->addGroup($project, $managers, Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById($user));
        $native = $c['projectUserRoleModel']->getUserRole($project, $user) === Role::PROJECT_MANAGER;
        $this->assertSame($native, in_array($project, $c['rvPrivacyModel']->leaderProjectIds()));
        $c['projectUserRoleModel']->addUser($project, $user, Role::PROJECT_MANAGER);
        $this->assertContains($project, $c['rvPrivacyModel']->leaderProjectIds());
        $c['projectUserRoleModel']->removeUser($project, $user);
        $c['projectGroupRoleModel']->removeGroup($project, $custom);
        $this->assertContains($project, $c['rvPrivacyModel']->leaderProjectIds());
    }
}
