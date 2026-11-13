<?php

namespace KanboardTests\units\Plugin;

use DomainException;
use PDOException;
use Kanboard\Core\Security\Role;

class RvSoftwareTimeTest extends RvSoftwareBase
{
    private $userId;
    private $taskId;
    private $subtaskId;

    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->container;
        $this->userId = $c['userModel']->create(array('username' => 'time-member'));
        $project = $c['projectModel']->create(array('name' => 'Time project'));
        $c['projectUserRoleModel']->addUser($project, $this->userId, Role::PROJECT_MEMBER);
        $this->taskId = $c['taskCreationModel']->create(array('title' => 'Time task', 'project_id' => $project));
        $this->subtaskId = $c['subtaskModel']->create(array('title' => 'Session source', 'task_id' => $this->taskId, 'user_id' => $this->userId));
        $c['userSession']->initialize($c['userModel']->getById($this->userId));
    }

    private function record($seconds, $key = 'test-operation')
    {
        $end = time() - 60;
        return $this->container['rvTimeModel']->manual($this->subtaskId, $end - $seconds, $end, 'Synthetic note', $key);
    }

    public function testExactSecondsAndSingleProjection()
    {
        $c = $this->container;
        foreach (array(1, 35, 36, 3601) as $seconds) {
            $id = $this->record($seconds, 'precision-'.$seconds);
            $this->assertEquals($seconds, $c['rvTimeModel']->session($id)['duration_seconds']);
        }
        $this->assertEquals(3673, $c['db']->table('rv_time_meta')->sum('duration_seconds'));
        $this->assertEquals(1.02, (float) $c['subtaskModel']->getById($this->subtaskId)['time_spent']);
        $this->assertEquals(1.02, (float) $c['taskFinderModel']->getById($this->taskId)['time_spent']);
    }

    public function testRetryAndPayloadConflict()
    {
        $c = $this->container;
        $end = time() - 60;
        $id = $c['rvTimeModel']->manual($this->subtaskId, $end - 3600, $end, 'Note', 'same-operation');
        $this->assertEquals($id, $c['rvTimeModel']->manual($this->subtaskId, $end - 3600, $end, 'Note', 'same-operation'));
        $this->assertEquals(1, $c['db']->table('subtask_time_tracking')->count());
        $this->assertEquals(1, $c['db']->table('rv_time_audit')->count());
        $this->expectException(DomainException::class);
        $c['rvTimeModel']->manual($this->subtaskId, $end - 1800, $end, 'Changed', 'same-operation');
    }

    public function testTwoSubtasksAreNotCountedTwice()
    {
        $c = $this->container;
        $this->record(3600, 'first-subtask');
        $second = $c['subtaskModel']->create(array('title' => 'Second', 'task_id' => $this->taskId));
        $end = time() - 60;
        $c['rvTimeModel']->manual($second, $end - 1800, $end, '', 'second-subtask');
        $this->assertEquals(5400, $c['db']->table('rv_time_meta')->sum('duration_seconds'));
        $this->assertEquals(1.5, (float) $c['taskFinderModel']->getById($this->taskId)['time_spent']);
    }

    public function testTimerPersistsSingleActiveAndStopRetry()
    {
        $c = $this->container;
        $id = $c['rvTimeModel']->start($this->subtaskId, $this->userId);
        $fresh = new \Kanboard\Plugin\RvSoftware\Model\RvTimeModel($c);
        $this->assertEquals($id, $fresh->start($this->subtaskId, $this->userId));
        $second = $c['subtaskModel']->create(array('title' => 'Other', 'task_id' => $this->taskId));
        $this->assertFalse($c['subtaskTimeTrackingModel']->logStartTime($second, $this->userId));
        $this->assertEquals(1, $c['db']->table('subtask_time_tracking')->eq('end', 0)->count());
        $c['db']->table('subtask_time_tracking')->eq('id', $id)->update(array('start' => time() - 120));
        $this->assertTrue($c['subtaskTimeTrackingModel']->logEndTime($this->subtaskId, $this->userId));
        $seconds = (int) $fresh->session($id)['duration_seconds'];
        $this->assertGreaterThanOrEqual(120, $seconds);
        $this->assertTrue($c['subtaskTimeTrackingModel']->logEndTime($this->subtaskId, $this->userId));
        $this->assertEquals(2, $c['db']->table('rv_time_audit')->count());
        $this->assertEquals(round($seconds / 3600, 2), (float) $c['subtaskModel']->getById($this->subtaskId)['time_spent']);
    }

    public function testCorrectionAuditAndRetry()
    {
        $c = $this->container;
        $id = $this->record(3600);
        $end = time() - 60;
        $this->assertEquals($id, $c['rvTimeModel']->correct($id, $end - 1800, $end, 'Corrected', 'Synthetic correction', 'correct-operation'));
        $this->assertEquals($id, $c['rvTimeModel']->correct($id, $end - 1800, $end, 'Corrected', 'Synthetic correction', 'correct-operation'));
        $this->assertEquals(2, $c['db']->table('rv_time_audit')->count());
        $this->assertEquals(1800, $c['rvTimeModel']->session($id)['duration_seconds']);
        $this->assertEquals(0.5, (float) $c['taskFinderModel']->getById($this->taskId)['time_spent']);
    }

    public function testForeignAndAggregateWritesAreRejected()
    {
        $c = $this->container;
        $id = $this->record(3600);
        $this->assertFalse($c['subtaskModel']->update(array('id' => $this->subtaskId, 'time_spent' => 12)));
        $this->assertFalse($c['taskModificationModel']->update(array('id' => $this->taskId, 'time_spent' => 12)));
        $other = $c['userModel']->create(array('username' => 'time-outsider'));
        $c['userSession']->initialize($c['userModel']->getById($other));
        $this->expectException(DomainException::class);
        $c['rvTimeModel']->correct($id, time() - 120, time() - 60, '', 'Denied', 'foreign-correct');
    }

    public function testInvalidDatesRollbackWithoutRows()
    {
        $c = $this->container;
        try {
            $c['rvTimeModel']->manual($this->subtaskId, time(), time() - 1, '', 'invalid-period');
            $this->fail('Invalid period accepted');
        } catch (DomainException $error) {
            $this->assertEquals(0, $c['db']->table('subtask_time_tracking')->count());
            $this->assertEquals(0, $c['db']->table('rv_time_audit')->count());
        }
    }

    public function testTrackedHistoryPreventsCascadingDeletion()
    {
        $this->record(3600);
        $this->expectException(PDOException::class);
        $this->container['db']->getConnection()->exec('DELETE FROM users WHERE id='.(int) $this->userId);
    }

    public function testNestedTransactionDoesNotCommitCaller()
    {
        $c = $this->container;
        $pdo = $c['db']->getConnection();
        $pdo->beginTransaction();
        $this->record(60);
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        $this->assertEquals(0, $c['db']->table('subtask_time_tracking')->count());
    }

    public function testAuditFailureRollsBackSessionAndProjections()
    {
        $c = $this->container;
        $model = new class($c) extends \Kanboard\Plugin\RvSoftware\Model\RvTimeModel {
            public function audit($id, $action, $before, $after, $reason, $key, $hash = '')
            {
                throw new \RuntimeException('Synthetic audit failure');
            }
        };
        try {
            $model->manual($this->subtaskId, time() - 120, time() - 60, '', 'rollback-audit');
            $this->fail('Injected failure did not abort');
        } catch (\RuntimeException $error) {
            $this->assertEquals(0, $c['db']->table('subtask_time_tracking')->count());
            $this->assertEquals(0, $c['db']->table('rv_time_meta')->count());
            $this->assertEquals(0, (float) $c['taskFinderModel']->getById($this->taskId)['time_spent']);
            $this->assertFalse($c['db']->getConnection()->inTransaction());
        }
    }

    public function testMidnightAndFutureBoundary()
    {
        $c = $this->container;
        $end = (new \DateTimeImmutable('2026-10-01 00:00:20', new \DateTimeZone('America/Bogota')))->getTimestamp();
        $id = $c['rvTimeModel']->manual($this->subtaskId, $end - 60, $end, '', 'cross-midnight');
        $this->assertEquals(60, $c['rvTimeModel']->session($id)['duration_seconds']);
        $this->expectException(DomainException::class);
        $c['rvTimeModel']->manual($this->subtaskId, time(), time() + 3600, '', 'future-period');
    }
}
