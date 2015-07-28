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
			->method('removeVisibility');
		$backendNotAllowed = $this->getBackendMock('\User\Mount\NotAllowed');
		$backendNotAllowed->expects($this->once())
			->method('removeVisibility')
			->with(BackendService::VISIBILITY_PERSONAL);

		$service->registerBackend($backendAllowed);
		$service->registerBackend($backendNotAllowed);
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

		$availableBackends = $service->getAvailableBackends();
		$this->assertArrayHasKey('\Backend\Available', $availableBackends);
		$this->assertArrayNotHasKey('Backend\NotAvailable', $availableBackends);
	}

	public function testGetUserBackends() {
		$service = new BackendService($this->config, $this->l10n);

		$backendAllowed = $this->getBackendMock('\User\Mount\Allowed');
		$backendAllowed->expects($this->once())
			->method('isVisibleFor')
			->with(BackendService::VISIBILITY_PERSONAL)
			->will($this->returnValue(true));
		$backendNotAllowed = $this->getBackendMock('\User\Mount\NotAllowed');
		$backendNotAllowed->expects($this->once())
			->method('isVisibleFor')
			->with(BackendService::VISIBILITY_PERSONAL)
			->will($this->returnValue(false));

		$service->registerBackend($backendAllowed);
		$service->registerBackend($backendNotAllowed);

		$userBackends = $service->getBackendsVisibleFor(BackendService::VISIBILITY_PERSONAL);
		$this->assertArrayHasKey('\User\Mount\Allowed', $userBackends);
		$this->assertArrayNotHasKey('\User\Mount\NotAllowed', $userBackends);
	}

}

