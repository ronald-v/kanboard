<?php
namespace KanboardTests\units\Plugin;

use Kanboard\Core\Security\Role;

class RvSoftwarePerformanceTest extends RvSoftwareBase
{
    public function testUserListingRendersProfileFieldsAndEscapesGroupTooltips()
    {
        $c = $this->container;
        $c['userSession']->initialize($c['userModel']->getById(1));
        $user = $c['userModel']->create(['username'=>'listing-render-user','email'=>'synthetic@example.invalid']);
        $group = $c['groupModel']->create('Group "<script>group</script>"');
        $c['groupMemberModel']->addUser($group,$user);
        $paginator = $c['userPagination']->getListingPaginator();
        $html = $c['template']->render('user_list/listing',['paginator'=>$paginator,'values'=>[]]);
        $this->assertStringContainsString('listing-render-user',$html);
        $this->assertStringContainsString('synthetic@example.invalid',$html);
        $this->assertStringContainsString('Group &quot;&lt;script&gt;group&lt;/script&gt;&quot;',$html);
        $this->assertStringNotContainsString('<script>group</script>',$html);
        $this->assertSame(\Kanboard\Helper\UserHelper::class,get_class($c['helper']->user));
    }

    public function testBulkBagEntriesStayWithinTheAuthorizedProject()
    {
        $c = $this->container;
        $c['userSession']->initialize($c['userModel']->getById(1));
        $first = $c['projectModel']->create(['name'=>'Bulk entries first']);
        $second = $c['projectModel']->create(['name'=>'Bulk entries second']);
        foreach ([$first,$second] as $project) {
            $c['rvBagModel']->assignClient($project,'Bulk client',0,'entries-client-'.$project);
            $bags[$project] = $c['rvBagModel']->createBag($project,'Bulk bag',3600,null,null,'entries-bag-'.$project);
        }
        $entries = $c['rvBagModel']->entriesByProject($first);
        $this->assertEquals($c['rvBagModel']->entries($bags[$first]), $entries[$bags[$first]]);
        $this->assertArrayNotHasKey($bags[$second], $entries);
        $viewer = $c['userModel']->create(['username'=>'bulk-entries-viewer']);
        $c['projectUserRoleModel']->addUser($first,$viewer,Role::PROJECT_VIEWER);
        $c['userSession']->initialize($c['userModel']->getById($viewer));
        $this->expectException(\DomainException::class);
        $c['rvBagModel']->entriesByProject($first);
    }

    public function testBulkActionsMatchNativeParametersMembershipAndRevocation()
    {
        $c = $this->container;
        $native = new \Kanboard\Model\ActionModel($c);
        $user = $c['userModel']->create(['username'=>'bulk-action-user']);
        $direct = $c['projectModel']->create(['name'=>'Action direct']);
        $groupProject = $c['projectModel']->create(['name'=>'Action group']);
        $inactive = $c['projectModel']->create(['name'=>'Action inactive']);
        $c['projectModel']->disable($inactive);
        $group = $c['groupModel']->create('Action group');
        $c['groupMemberModel']->addUser($group,$user);
        $c['projectUserRoleModel']->addUser($direct,$user,Role::PROJECT_MEMBER);
        foreach ([$groupProject,$inactive] as $project) { $c['projectGroupRoleModel']->addGroup($project,$group,Role::PROJECT_MEMBER); }
        foreach ([$direct,$groupProject,$inactive] as $project) {
            $c['actionModel']->create(['project_id'=>$project,'event_name'=>\Kanboard\Model\TaskModel::EVENT_CREATE,
                'action_name'=>'\\Kanboard\\Action\\TaskAssignColorColumn','params'=>['column_id'=>1,'color_id'=>'red']]);
        }
        $model = $c['actionModel'];
        $this->assertEquals($native->getAllByUser($user), $model->getAllByUser($user));
        $this->assertCount(2, $model->getAllByUser($user));
        $c['groupMemberModel']->removeUser($group,$user);
        $this->assertCount(1, $model->getAllByUser($user));
        $c['projectUserRoleModel']->removeUser($direct,$user);
        $this->assertSame([], $model->getAllByUser($user));
    }

    public function testTaskListKeepsOnlyTheMembersOwnWorkedTime()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'list-time-user']);
        $other = $c['userModel']->create(['username'=>'list-time-other']);
        $project = $c['projectModel']->create(['name'=>'Private list time']);
        foreach ([$user,$other] as $id) { $c['projectUserRoleModel']->addUser($project,$id,Role::PROJECT_MEMBER); }
        $task = $c['taskCreationModel']->create(['title'=>'List task','project_id'=>$project]);
        $subtask = $c['subtaskModel']->create(['title'=>'List subtask','task_id'=>$task]);
        $end = time()-60;
        $c['userSession']->initialize($c['userModel']->getById($user));
        $c['rvTimeModel']->manual($subtask,$end-3600,$end,'','list-time-user-op');
        $c['userSession']->initialize($c['userModel']->getById($other));
        $c['rvTimeModel']->manual($subtask,$end-1800,$end,'','list-time-other-op');
        $this->assertEquals(0.5, $c['taskFinderModel']->getAll($project)[0]['time_spent']);
        $c['userSession']->initialize($c['userModel']->getById($user));
        $this->assertEquals(1, $c['taskFinderModel']->getAll($project)[0]['time_spent']);
        $c['userSession']->initialize($c['userModel']->getById(1));
        $this->assertEquals(1.5, $c['taskFinderModel']->getAll($project)[0]['time_spent']);
    }

    public function testSingleProjectListMatchesNativeAndDoesNotCacheMembership()
    {
        $c = $this->container;
        $native = new \Kanboard\Model\ProjectUserRoleModel($c);
        $user = $c['userModel']->create(['username'=>'single-list-user']);
        $other = $c['userModel']->create(['username'=>'single-list-other']);
        $direct = $c['projectModel']->create(['name'=>'Alpha direct']);
        $groupProject = $c['projectModel']->create(['name'=>'Beta group']);
        $inactive = $c['projectModel']->create(['name'=>'Inactive']);
        $c['projectModel']->disable($inactive);
        $group = $c['groupModel']->create('Single list group');
        $c['groupMemberModel']->addUser($group,$user);
        foreach ([$direct,$groupProject,$inactive] as $project) { $c['projectGroupRoleModel']->addGroup($project,$group,Role::PROJECT_MEMBER); }
        $c['projectUserRoleModel']->addUser($direct,$user,Role::PROJECT_VIEWER);
        $model = $c['projectUserRoleModel'];
        $this->assertEquals($native->getProjectsByUser($user), $model->getProjectsByUser($user));
        $this->assertEquals($native->getActiveProjectsByUser($user), $model->getActiveProjectsByUser($user));
        $this->assertSame([], $model->getProjectsByUser($other));
        $c['groupMemberModel']->removeUser($group,$user);
        $this->assertEquals([$direct=>'Alpha direct'], $model->getProjectsByUser($user));
        $c['projectUserRoleModel']->removeUser($direct,$user);
        $this->assertSame([], $model->getProjectsByUser($user));
    }

    public function testUserGroupListingUsesOneQueryAndReleasesItsPresentationData()
    {
        $c = $this->container;
        $this->assertInstanceOf(\Kanboard\Plugin\RvSoftware\Helper\UserHelper::class,$c['helper']->rvUserListing);
        $user = $c['userModel']->create(['username'=>'listing-user']);
        $other = $c['userModel']->create(['username'=>'listing-other']);
        $group = $c['groupModel']->create('Listing group');
        $c['groupMemberModel']->addUser($group,$user);
        $helperContainer = new \Pimple\Container(['db'=>$c['db'], 'groupMemberModel'=>$c['groupMemberModel']]);
        $helper = new \Kanboard\Plugin\RvSoftware\Helper\UserHelper($helperContainer);
        $paginator = $c['userPagination']->getListingPaginator();
        $template = $this->createMock(\Kanboard\Core\Template::class);
        $template->expects($this->once())->method('render')->willReturnCallback(function () use ($helper,$user,$other,$paginator) {
            $paginator->getCollection(); // Native listing reads the same collection.
            $this->assertSame(['Listing group'], $helper->getUsersGroupNames($user)['full_list']);
            $this->assertSame([], $helper->getUsersGroupNames($other)['full_list']);
            return 'listing';
        });
        $helperContainer['template'] = $template;
        $before = $c['db']->getStatementHandler()->getNbQueries();
        $this->assertSame('listing', $helper->renderListing($paginator,[]));
        $this->assertSame(2, $c['db']->getStatementHandler()->getNbQueries()-$before);
        $c['groupMemberModel']->removeUser($group,$user);
        $this->assertSame([], $helper->getUsersGroupNames($user)['full_list']);
    }

    public function testBulkRolesPreserveDirectPrecedenceAndFreshPermissions()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'bulk-role-user']);
        $project = $c['projectModel']->create(['name'=>'Bulk role project']);
        $group = $c['groupModel']->create('Bulk role group');
        $c['groupMemberModel']->addUser($group, $user);
        $c['projectGroupRoleModel']->addGroup($project, $group, Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById($user));
        $model = $c['rvTimeModel'];
        $this->assertSame(Role::PROJECT_MANAGER, $model->projectRoles([$project], $user)[$project]);
        $c['projectUserRoleModel']->addUser($project, $user, Role::PROJECT_VIEWER);
        $this->assertSame(Role::PROJECT_VIEWER, $model->projectRoles([$project], $user)[$project]);
        $c['projectUserRoleModel']->removeUser($project, $user);
        $c['projectGroupRoleModel']->removeGroup($project, $group);
        $this->assertEmpty($model->projectRoles([$project], $user)[$project]);
        $this->assertSame([], $model->projectRoles([], $user));
    }

    public function testBulkRolesMatchNativeCustomGroupRoles()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'bulk-custom-user']);
        $project = $c['projectModel']->create(['name'=>'Bulk custom project']);
        $custom = $c['groupModel']->create('Bulk custom group');
        $managers = $c['groupModel']->create('Bulk managers group');
        $c['projectRoleModel']->create($project, 'Custom bulk');
        foreach ([$custom, $managers] as $group) { $c['groupMemberModel']->addUser($group, $user); }
        $c['projectGroupRoleModel']->addGroup($project, $custom, 'Custom bulk');
        $c['projectGroupRoleModel']->addGroup($project, $managers, Role::PROJECT_MANAGER);
        $this->assertSame($c['projectUserRoleModel']->getUserRole($project,$user), $c['rvTimeModel']->projectRoles([$project],$user)[$project]);
    }

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
