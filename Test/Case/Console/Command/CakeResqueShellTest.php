<?php

App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('ShellDispatcher', 'Console');
App::uses('Shell', 'Console');
App::uses('CakeResqueShell', 'CakeResque.Console/Command');

class CakeResqueShellTest extends CakeTestCase
{

	public function setUp() {
		parent::setUp();
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->CakeResque = $this->getMockClass(
			'CakeResque',
			array('enqueue', 'enqueueIn', 'enqueueAt', 'getJobStatus', 'getFailedJobLog', 'getWorkers')
		);

		$this->ResqueStatus = $this->getMock(
			'ResqueStatus',
			array('getPausedWorker', 'clearWorker', 'isSchedulerWorker', 'setPausedWorker', 'setActiveWorker', 'isRunningSchedulerWorker', 'getWorkers'));

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill'),
			array($out, $out, $in)
		);

		$this->Shell->expects($this->any())->method('_kill')->will($this->returnValue(array('code' => 0, 'message' => '')));

		$this->Shell->ResqueStatus = $this->ResqueStatus;
	}

	public function tearDown() {
		parent::tearDown();
		unset($this->Dispatch, $this->Shell);
	}

/**
 * @covers CakeResqueShell::debug
 */
	public function testDebug() {
		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('<success>[DEBUG] test string</success>'));
		$this->Shell->debug('test string');
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingWithNoJobIdReturnError() {
		$this->Shell->expects($this->exactly(2))->method('out');

		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->never())->method('getJobStatus');
		$CakeResque::staticExpects($this->never())->method('getFailedJobLog');

		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('Tracking job status'));

		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('error'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingJobWithUnknownStatus() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(false));

		$CakeResque::staticExpects($this->never())->method('getFailedJobLog');

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Status'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/unknown/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/warning/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingCompletedJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_COMPLETE));

		$CakeResque::staticExpects($this->never())->method('getFailedJobLog');

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/complete/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingRunningJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_RUNNING));

		$CakeResque::staticExpects($this->never())->method('getFailedJobLog');

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/running/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingWaitingJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_WAITING));

		$CakeResque::staticExpects($this->never())->method('getFailedJobLog');

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/waiting/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingFailedJobWithEmptyLog() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_FAILED));

		$CakeResque::staticExpects($this->once())
			->method('getFailedJobLog')
			->will($this->returnValue(array()));

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->exactly(3))->method('out');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/failed/'));
		$this->Shell->track();
	}

	public function testTrackingFailedJobWithStringLog() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_FAILED));

		$CakeResque::staticExpects($this->once())
			->method('getFailedJobLog')
			->will($this->returnValue(array("log++")));

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/failed/'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->matchesRegularExpression('/details/'));
		$this->Shell->expects($this->at(6))->method('out')->with($this->matchesRegularExpression('/log/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::track
 */
	public function testTrackingFailedJobWithArrayLog() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())
			->method('getJobStatus')
			->will($this->returnValue(Resque_Job_Status::STATUS_FAILED));

		$CakeResque::staticExpects($this->once())
			->method('getFailedJobLog')
			->will($this->returnValue(array("key" => "name")));

		$this->Shell->args = array('dd');
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/failed/'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->matchesRegularExpression('/details/'));
		$this->Shell->expects($this->at(5))->method('out')->with($this->matchesRegularExpression('/key/i'));
		$this->Shell->expects($this->at(6))->method('out')->with($this->matchesRegularExpression('/name/'));
		$this->Shell->track();
	}

/**
 * @covers CakeResqueShell::enqueue
 */
	public function testEnqueueJobWithoutArguments() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->never())->method('enqueue');

		$this->Shell->expects($this->exactly(2))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/adding/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/usage/i'));
		$this->Shell->enqueue();
	}

/**
 * @covers CakeResqueShell::enqueue
 */
	public function testEnqueueJobWithWrongNumberOfArguments() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->args = array('queue', 'class');

		$CakeResque::staticExpects($this->never())->method('enqueue');

		$this->Shell->expects($this->exactly(2))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/adding/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/usage/i'));
		$this->Shell->enqueue();
	}

/**
 * @covers CakeResqueShell::enqueue
 */
	public function testEnqueueJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->args = array('queue', 'class', 'args');

		$id = md5(time() / 10);

		$CakeResque::staticExpects($this->once())->method('enqueue')->will($this->returnValue($id));

		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/adding/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/succesfully/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/' . $id . '/i'));
		$this->Shell->enqueue();
	}

/**
 * @covers CakeResqueShell::enqueueIn
 */
	public function testEnqueueInJobWithWrongNumberOfArguments() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->args = array('queue', 'class');

		$CakeResque::staticExpects($this->never())->method('enqueueIn');

		$this->Shell->expects($this->exactly(2))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/scheduling/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/usage/i'));
		$this->Shell->enqueueIn();
	}

/**
 * @covers CakeResqueShell::enqueueIn
 */
	public function testEnqueueInJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->args = array(0, 'queue', 'class', 'args');

		$id = md5(time() / 10);

		$CakeResque::staticExpects($this->once())->method('enqueueIn')->will($this->returnValue($id));

		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/scheduling/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/succesfully/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/' . $id . '/i'));
		$this->Shell->enqueueIn();
	}

/**
 * @covers CakeResqueShell::enqueueAt
 */
	public function testEnqueueAtJobWithWrongNumberOfArguments() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->args = array('queue', 'class');

		$CakeResque::staticExpects($this->never())->method('enqueueAt');

		$this->Shell->expects($this->exactly(2))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/scheduling/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/usage/i'));
		$this->Shell->enqueueAt();
	}

/**
 * @covers CakeResqueShell::enqueueAt
 */
	public function testEnqueueAtJob() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->args = array(0, 'queue', 'class', 'args');

		$id = md5(time() / 10);

		$CakeResque::staticExpects($this->once())->method('enqueueAt')->will($this->returnValue($id));

		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/scheduling/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/succesfully/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/' . $id . '/i'));
		$this->Shell->enqueueAt();
	}

	// PAUSE -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseWorkerWhenThereIsNoWorkers() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));

		$this->Shell->expects($this->exactly(3))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('There is no active workers to pause'));

		$this->ResqueStatus->expects($this->never())->method('setPausedWorker');

		$this->Shell->pause();
	}

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseWorkerWhenThereIsOnlyOneWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Pausing 956 ...'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->matchesRegularExpression('/done/i'));

		$this->ResqueStatus->expects($this->once())->method('setPausedWorker');

		$this->Shell->params['all'] = false;
		$this->Shell->pause();
	}

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseWorkerWhenThereIsMultipleWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Active workers list'));
		$this->Shell->expects($this->at(2))->method('out')->with($this->stringContains('    [  1] - host:956:queuename'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->stringContains('    [  2] - host:957:queuename'));
		$this->Shell->expects($this->at(4))->method('out')->with($this->stringContains('    [all] - '));

		$this->Shell->expects($this->once())->method('in')->will($this->returnValue(2));

		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('Pausing 957 ...'));
		$this->Shell->expects($this->at(8))->method('out')->with($this->stringContains('done'));

		$this->ResqueStatus->expects($this->exactly(1))->method('setPausedWorker')->with('host:957:queuename');

		$this->Shell->params['all'] = false;
		$this->Shell->pause();
	}

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseWorkerWhenThereIsAlreadySomePausedWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseWorkerAllAtOnceWithAllOption() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));

		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Pausing 956 ...'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->stringContains('done'));
		$this->Shell->expects($this->at(4))->method('out')->with($this->stringContains('Pausing 957 ...'));
		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('done'));

		$this->ResqueStatus->expects($this->exactly(2))->method('setPausedWorker');

		$this->Shell->params['all'] = true;
		$this->Shell->pause();
	}

/**
 * @covers CakeResqueShell::pause
 */
	public function testPauseAllWorker() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/pausing/i'));

		$this->Shell->expects($this->once())->method('in')->will($this->returnValue("all"));

		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('Pausing 956 ...'));
		$this->Shell->expects($this->at(8))->method('out')->with($this->stringContains('done'));
		$this->Shell->expects($this->at(9))->method('out')->with($this->stringContains('Pausing 957 ...'));
		$this->Shell->expects($this->at(11))->method('out')->with($this->stringContains('done'));

		$this->ResqueStatus->expects($this->exactly(2))->method('setPausedWorker');

		$this->Shell->params['all'] = false;
		$this->Shell->pause();
	}

	// STOP -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::stop
 */
	public function testStopWorkerWhenThereIsNoWorkers() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->any())->method('getWorkers')->will($this->returnValue(array()));

		$this->Shell->expects($this->exactly(3))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('There is no active workers to kill'));

		$this->Shell->stop();
	}

/**
 * @covers CakeResqueShell::stop
 */
	public function testStopWorkerWhenThereIsOnlyOneWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::stop
 */
	public function testStopWorkerWhenThereIsMultipleWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::stop
 */
	public function testStopWorkerWhenThereIsAlreadySomeStoppedWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::stop
 */
	public function testStopWorkerAllAtOnce() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	// CLEANUP -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::cleanup
 */
	public function testCleanupWorkerWhenThereIsNoWorkers() {
		$shell = $this->Shell;

		$shell::$cakeResque = $CakeResque = $this->CakeResque;

		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));

		$this->Shell->expects($this->exactly(3))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/Cleaning up/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('There is no active workers to clean up'));

		$this->Shell->cleanup();
	}

/**
 * @covers CakeResqueShell::cleanup
 */
	public function testCleanupWorkerWhenThereIsOnlyOneWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/Cleaning up/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Cleaning up 956 ...'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->matchesRegularExpression('/done/i'));

		$this->Shell->params['all'] = false;
		$this->Shell->cleanup();
	}

/**
 * @covers CakeResqueShell::cleanup
 */
	public function testCleanupWorkerWhenThereIsMultipleWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/Cleaning up/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Active workers list'));
		$this->Shell->expects($this->at(2))->method('out')->with($this->stringContains('    [  1] - host:956:queuename'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->stringContains('    [  2] - host:957:queuename'));
		$this->Shell->expects($this->at(4))->method('out')->with($this->stringContains('    [all] - '));

		$this->Shell->expects($this->once())->method('in')->will($this->returnValue(2));

		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('Cleaning up 957 ...'));
		$this->Shell->expects($this->at(8))->method('out')->with($this->stringContains('done'));

		$this->Shell->params['all'] = false;
		$this->Shell->cleanup();
	}

/**
 * @covers CakeResqueShell::cleanup
 */
	public function testCleanupWorkerAllAtOnceWithAllOption() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/Cleaning up/i'));

		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Cleaning up 956 ...'));
		$this->Shell->expects($this->at(3))->method('out')->with($this->stringContains('done'));
		$this->Shell->expects($this->at(4))->method('out')->with($this->stringContains('Cleaning up 957 ...'));
		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('done'));

		$this->Shell->params['all'] = true;
		$this->Shell->cleanup();
	}

/**
 * @covers CakeResqueShell::cleanup
 */
	public function testCleanupAllWorker() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array("host:956:queuename", "host:957:queuename")));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/Cleaning up/i'));

		$this->Shell->expects($this->once())->method('in')->will($this->returnValue("all"));

		$this->Shell->expects($this->at(6))->method('out')->with($this->stringContains('Cleaning up 956 ...'));
		$this->Shell->expects($this->at(8))->method('out')->with($this->stringContains('done'));
		$this->Shell->expects($this->at(9))->method('out')->with($this->stringContains('Cleaning up 957 ...'));
		$this->Shell->expects($this->at(11))->method('out')->with($this->stringContains('done'));

		$this->Shell->params['all'] = false;
		$this->Shell->cleanup();
	}

	// LOAD -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::load
 */
	public function testLoadEmpty() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		Configure::write('CakeResque.Queues', null);
		Configure::write('CakeResque.Scheduler.enabled', false);

		$this->Shell->expects($this->exactly(3))->method('out');
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/loading/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('no configured queues to load'));

		$this->Shell->load();
	}

/**
 * @covers CakeResqueShell::load
 */
	public function testLoad() {
		Configure::write('CakeResque.Queues', array(array(), array(), array()));
		Configure::write('CakeResque.Scheduler.enabled', false);

		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill', 'start', 'startscheduler', 'stop'),
			array($out, $out, $in)
		);

		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/loading/i'));
		$this->Shell->expects($this->exactly(3))->method('start');
		$this->Shell->expects($this->never())->method('startscheduler');
		$this->Shell->expects($this->exactly(2))->method('out');

		Configure::write('CakeResque.Scheduler.enabled', false);

		$this->Shell->load();
	}

/**
 * @covers CakeResqueShell::load
 */
	public function testLoadWithSchedulerEnabled() {
		Configure::write('CakeResque.Queues', array(array(), array(), array()));
		Configure::write('CakeResque.Scheduler.enabled', false);

		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill', 'start', 'startscheduler', 'stop'),
			array($out, $out, $in)
		);

		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/loading/i'));
		$this->Shell->expects($this->exactly(3))->method('start');
		$this->Shell->expects($this->once())->method('startscheduler');
		$this->Shell->expects($this->exactly(2))->method('out');

		Configure::write('CakeResque.Scheduler.enabled', true);

		$this->Shell->load();
	}

/**
 * @covers CakeResqueShell::load
 */
	public function testLoadWithSchedulerWorker() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		Configure::write('CakeResque.Queues', null);
		Configure::write('CakeResque.Scheduler.enabled', true);
		//$CakeResque::staticExpects($this->once())->method('getWorkers')->will($this->returnValue(array()));
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/stopping/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	// RESUME -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::resume
 */
	public function testResumeWithNotPausedWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/resuming/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::resume
 */
	public function testResumeWithSomeWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/resuming/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::resume
 */
	public function testResumeAllAtOnceWithAllOption() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/resuming/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::resume
 */
	public function testResumeAllWorkers() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/resuming/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	// START SCHEDULER WORKER -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::startscheduler
 */
	public function testStartScheduler() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('Creating the scheduler worker'));
		$this->markTestIncomplete('This test has not been implemented yet.');

		Configure::write('CakeResque.Scheduler.enabled', true);
	}

/**
 * @covers CakeResqueShell::startscheduler
 */
	public function testStartSchedulerWhenSchedulingIsDisabled() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('Creating the scheduler worker'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/Scheduler Worker is not enabled/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/error/i'));
		$this->Shell->expects($this->exactly(2))->method('out');

		Configure::write('CakeResque.Scheduler.enabled', false);
		$this->Shell->startscheduler();
	}

/**
 * @covers CakeResqueShell::startscheduler
 */
	public function testStartSchedulerWhenSchedulerIsAlreadyStarted() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('Creating the scheduler worker'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/The scheduler worker is already running/i'));
		$this->Shell->expects($this->at(1))->method('out')->with($this->matchesRegularExpression('/warning/i'));
		$this->Shell->expects($this->exactly(2))->method('out');

		$this->ResqueStatus->expects($this->once())->method('isRunningSchedulerWorker')->with(true)->will($this->returnValue(true));

		Configure::write('CakeResque.Scheduler.enabled', true);
		$this->Shell->startscheduler();
	}

	// RESTART -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::restart
 */
	public function testRestartWhenThereIsNoActiveWorkers() {
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill', 'start', 'startscheduler', 'stop'),
			array($out, $out, $in)
		);

		$this->Shell->ResqueStatus = $this->ResqueStatus;

		$this->ResqueStatus
		->expects($this->once())
		->method('getWorkers')
		->will($this->returnValue(array()));

		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Restarting workers'));
		$this->Shell->expects($this->at(2))->method('out')->with($this->stringContains('No active workers found'));
		$this->Shell->expects($this->at(2))->method('out')->with($this->matchesRegularExpression('/warning/i'));
		$this->Shell->expects($this->exactly(2))->method('out');

		$this->Shell->expects($this->once())->method('stop');
		$this->Shell->expects($this->once())->method('start');
		$this->Shell->expects($this->never())->method('startscheduler');

		Configure::write('CakeResque.Scheduler.enabled', false);

		$this->Shell->restart();
	}

/**
 * @covers CakeResqueShell::restart
 */
	public function testRestartWhenThereIsActiveWorkers() {
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill', 'start', 'startscheduler', 'stop'),
			array($out, $out, $in)
		);

		$this->Shell->ResqueStatus = $this->ResqueStatus;

		$this->ResqueStatus
		->expects($this->once())
		->method('getWorkers')
		->will($this->returnValue(array('a' => array('type' => 'scheduler'), 'b' => array(), 'c' => array())));

		$this->Shell->expects($this->at(1))->method('out')->with($this->stringContains('Restarting workers'));
		$this->Shell->expects($this->exactly(2))->method('out');

		$this->Shell->expects($this->exactly(2))->method('start');
		$this->Shell->expects($this->once())->method('startscheduler');

		Configure::write('CakeResque.Scheduler.enabled', false);

		$this->Shell->params['debug'] = false;
		$this->Shell->restart();
	}

	// START -------------------------------------------------------------------------------------------------

/**
 * @covers CakeResqueShell::start
 */
	public function testStart() {
		$shell = $this->Shell;
		$shell::$cakeResque = $CakeResque = $this->CakeResque;
		$this->Shell->expects($this->at(0))->method('out')->with($this->matchesRegularExpression('/creating/i'));
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

/**
 * @covers CakeResqueShell::start
 */
	public function testStartWithInvalidArguments() {
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Shell = $this->getMock(
			'CakeResqueShell',
			array('in', 'out', 'hr', '_kill', 'startscheduler', 'stop', '_validate'),
			array($out, $out, $in)
		);

		$this->Shell->expects($this->at(0))->method('out')->with($this->stringContains('Creating workers'));
		$this->Shell->expects($this->once())->method('_validate')->will($this->returnValue(false));
		$this->Shell->expects($this->exactly(1))->method('out');

		$this->Shell->start();
	}

}