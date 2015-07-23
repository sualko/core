<?php
/**
 * @author Robin McCorkell <rmccorkell@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Files_External\Tests\Service;

use \OCA\Files_External\Service\BackendService;
use \OCA\Files_External\Lib\BackendConfig;

class BackendServiceTest extends \Test\TestCase {

	/** @var \OCP\IConfig */
	protected $config;

	/** @var \OCP\IL10N */
	protected $l10n;

	protected function setUp() {
		$this->config = $this->getMock('\OCP\IConfig');
		$this->l10n = $this->getMock('\OCP\IL10N');
	}

	protected function getBackendMock($class) {
		$backend = $this->getMockBuilder('\OCA\Files_External\Lib\BackendConfig')
			->disableOriginalConstructor()
			->getMock();
		$backend->method('getClass')->will($this->returnValue($class));
		return $backend;
	}

	public function testRegisterBackend() {
		$service = new BackendService($this->config, $this->l10n);

		$backend = $this->getBackendMock('\Foo\Bar');
		$service->registerBackend($backend);
		$this->assertEquals($backend, $service->getBackend('\Foo\Bar'));
	}

	public function testUserMountingBackends() {
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->will($this->returnValueMap([
				['files_external', 'allow_user_mounting', 'yes', 'yes'],
				['files_external', 'user_mounting_backends', '', '\User\Mount\Allowed']
			]));

		$service = new BackendService($this->config, $this->l10n);

		$backendAllowed = $this->getBackendMock('\User\Mount\Allowed');
		$backendAllowed->expects($this->never())
			->method('setVisibility');
		$backendNotAllowed = $this->getBackendMock('\User\Mount\NotAllowed');
		$backendNotAllowed->expects($this->once())
			->method('setVisibility')
			->with(BackendConfig::VISIBILITY_ADMIN);

		$service->registerBackend($backendAllowed);
		$service->registerBackend($backendNotAllowed);
	}

	public function testGetBackendsSorted() {
		$service = new BackendService($this->config, $this->l10n);

		$backendFirst = $this->getBackendMock('\Backend\First');
		$backendFirst->method('getText')->willReturn('aaa');
		$backendSecond = $this->getBackendMock('\Backend\Second');
		$backendSecond->method('getText')->willReturn('bbb');
		$backendThird = $this->getBackendMock('\Backend\Third');
		$backendThird->method('getText')->willReturn('ccc');

		$service->registerBackend($backendSecond);
		$service->registerBackend($backendFirst);
		$service->registerBackend($backendThird);

		// bug with uasort and debug code, suppress errors
		// https://bugs.php.net/bug.php?id=50688
		$backends = @$service->getBackends();

		$firstSeen = false;
		$secondSeen = false;
		$thirdSeen = false;
		foreach ($backends as $class => $backend) {
			switch ($class) {
			case '\Backend\First':
				$this->assertFalse($firstSeen);
				$this->assertFalse($secondSeen);
				$this->assertFalse($thirdSeen);
				$firstSeen = true;
				break;
			case '\Backend\Second':
				$this->assertTrue($firstSeen);
				$this->assertFalse($secondSeen);
				$this->assertFalse($thirdSeen);
				$secondSeen = true;
				break;
			case '\Backend\Third':
				$this->assertTrue($firstSeen);
				$this->assertTrue($secondSeen);
				$this->assertFalse($thirdSeen);
				$thirdSeen = true;
				break;
			}
		}
		$this->assertTrue($firstSeen);
		$this->assertTrue($secondSeen);
		$this->assertTrue($thirdSeen);
	}

	public function testGetAvailableBackends() {
		$service = new BackendService($this->config, $this->l10n);

		$backendAvailable = $this->getBackendMock('\Backend\Available');
		$backendAvailable->expects($this->once())
			->method('checkDependencies')
			->will($this->returnValue([]));
		$backendNotAvailable = $this->getBackendMock('\Backend\NotAvailable');
		$backendNotAvailable->expects($this->once())
			->method('checkDependencies')
			->will($this->returnValue([
				$this->getMockBuilder('\OCA\Files_External\Lib\BackendDependency')
					->disableOriginalConstructor()
					->getMock()
			]));

		$service->registerBackend($backendAvailable);
		$service->registerBackend($backendNotAvailable);

		// bug with uasort and debug code, suppress errors
		// https://bugs.php.net/bug.php?id=50688
		$availableBackends = @$service->getAvailableBackends();
		$this->assertArrayHasKey('\Backend\Available', $availableBackends);
		$this->assertArrayNotHasKey('Backend\NotAvailable', $availableBackends);
	}

	public function testGetUserBackends() {
		$service = new BackendService($this->config, $this->l10n);

		$backendAllowed = $this->getBackendMock('\User\Mount\Allowed');
		$backendAllowed->expects($this->once())
			->method('isVisibleFor')
			->with(BackendConfig::VISIBILITY_PERSONAL)
			->will($this->returnValue(true));
		$backendNotAllowed = $this->getBackendMock('\User\Mount\NotAllowed');
		$backendNotAllowed->expects($this->once())
			->method('isVisibleFor')
			->with(BackendConfig::VISIBILITY_PERSONAL)
			->will($this->returnValue(false));

		$service->registerBackend($backendAllowed);
		$service->registerBackend($backendNotAllowed);

		// bug with uasort and debug code, suppress errors
		// https://bugs.php.net/bug.php?id=50688
		$userBackends = @$service->getUserBackends();
		$this->assertArrayHasKey('\User\Mount\Allowed', $userBackends);
		$this->assertArrayNotHasKey('\User\Mount\NotAllowed', $userBackends);
	}

}

