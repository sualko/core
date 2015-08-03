<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
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
namespace OCA\Files_external\Tests\Controller;

use \OCP\AppFramework\Http;

use \OCA\Files_external\Controller\GlobalStoragesController;
use \OCA\Files_external\Service\GlobalStoragesService;
use \OCA\Files_external\Lib\StorageConfig;
use \OCA\Files_external\NotFoundException;
use \OCA\Files_External\Lib\BackendConfig;
use \OCA\Files_External\Lib\AuthMechConfig;

abstract class StoragesControllerTest extends \Test\TestCase {

	/**
	 * @var GlobalStoragesController
	 */
	protected $controller;

	/**
	 * @var GlobalStoragesService
	 */
	protected $service;

	public function setUp() {
		\OC_Mount_Config::$skipTest = true;
	}

	public function tearDown() {
		\OC_Mount_Config::$skipTest = false;
	}

	protected function getAuthMechConfigMock($scheme = 'null', $class = '\OCA\Files_External\Lib\Auth\NullMechanism') {
		$authMechConfig = $this->getMockBuilder('\OCA\Files_External\Lib\AuthMechConfig')
			->disableOriginalConstructor()
			->getMock();
		$authMechConfig->method('getScheme')
			->willReturn($scheme);
		$authMechConfig->method('getClass')
			->willReturn($class);

		$authMech = $this->getMock('\OCA\Files_External\Lib\Auth\IMechanism');
		$authMechConfig->method('getImplementation')
			->willReturn($authMech);

		return $authMechConfig;
	}

	public function testAddStorage() {
		$authMechConfig = $this->getAuthMechConfigMock();
		$authMechConfig->method('validateStorage')
			->willReturn(true);

		$storageConfig = new StorageConfig(1);
		$storageConfig->setMountPoint('mount');
		$storageConfig->setBackend(new BackendConfig('\OC\Files\Storage\SMB', 'smb', []));
		$storageConfig->setAuthMechanism($authMechConfig);
		$storageConfig->setBackendOptions([]);

		$this->service->expects($this->once())
			->method('createStorage')
			->will($this->returnValue($storageConfig));
		$this->service->expects($this->once())
			->method('addStorage')
			->will($this->returnValue($storageConfig));

		$response = $this->controller->create(
			'mount',
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$data = $response->getData();
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$this->assertEquals($storageConfig, $data);
	}

	public function testUpdateStorage() {
		$authMechConfig = $this->getAuthMechConfigMock();
		$authMechConfig->method('validateStorage')
			->willReturn(true);

		$storageConfig = new StorageConfig(1);
		$storageConfig->setMountPoint('mount');
		$storageConfig->setBackend(new BackendConfig('\OC\Files\Storage\SMB', 'smb', []));
		$storageConfig->setAuthMechanism($authMechConfig);
		$storageConfig->setBackendOptions([]);

		$this->service->expects($this->once())
			->method('createStorage')
			->will($this->returnValue($storageConfig));
		$this->service->expects($this->once())
			->method('updateStorage')
			->will($this->returnValue($storageConfig));

		$response = $this->controller->update(
			1,
			'mount',
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$data = $response->getData();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($storageConfig, $data);
	}

	function mountPointNamesProvider() {
		return array(
			array(''),
			array('/'),
			array('//'),
		);
	}

	/**
	 * @dataProvider mountPointNamesProvider
	 */
	public function testAddOrUpdateStorageInvalidMountPoint($mountPoint) {
		$storageConfig = new StorageConfig(1);
		$storageConfig->setMountPoint($mountPoint);
		$storageConfig->setBackend(new BackendConfig('\OC\Files\Storage\SMB', 'smb', []));
		$storageConfig->setAuthMechanism($this->getAuthMechConfigMock());
		$storageConfig->setBackendOptions([]);

		$this->service->expects($this->exactly(2))
			->method('createStorage')
			->will($this->returnValue($storageConfig));
		$this->service->expects($this->never())
			->method('addStorage');
		$this->service->expects($this->never())
			->method('updateStorage');

		$response = $this->controller->create(
			$mountPoint,
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$this->assertEquals(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		$response = $this->controller->update(
			1,
			$mountPoint,
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$this->assertEquals(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testAddOrUpdateStorageInvalidBackend() {
		$this->service->expects($this->exactly(2))
			->method('createStorage')
			->will($this->throwException(new \InvalidArgumentException()));
		$this->service->expects($this->never())
			->method('addStorage');
		$this->service->expects($this->never())
			->method('updateStorage');

		$response = $this->controller->create(
			'mount',
			'\OC\Files\Storage\InvalidStorage',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$this->assertEquals(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		$response = $this->controller->update(
			1,
			'mount',
			'\OC\Files\Storage\InvalidStorage',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$this->assertEquals(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testUpdateStorageNonExisting() {
		$authMechConfig = $this->getAuthMechConfigMock();
		$authMechConfig->method('validateStorage')
			->willReturn(true);

		$storageConfig = new StorageConfig(255);
		$storageConfig->setMountPoint('mount');
		$storageConfig->setBackend(new BackendConfig('\OC\Files\Storage\SMB', 'smb', []));
		$storageConfig->setAuthMechanism($authMechConfig);
		$storageConfig->setBackendOptions([]);

		$this->service->expects($this->once())
			->method('createStorage')
			->will($this->returnValue($storageConfig));
		$this->service->expects($this->once())
			->method('updateStorage')
			->will($this->throwException(new NotFoundException()));

		$response = $this->controller->update(
			255,
			'mount',
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testDeleteStorage() {
		$this->service->expects($this->once())
			->method('removeStorage');

		$response = $this->controller->destroy(1);
		$this->assertEquals(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function testDeleteStorageNonExisting() {
		$this->service->expects($this->once())
			->method('removeStorage')
			->will($this->throwException(new NotFoundException()));

		$response = $this->controller->destroy(255);
		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testGetStorage() {
		$backendConfig = new BackendConfig('\OC\Files\Storage\SMB', 'smb', []);
		$authMechConfig = $this->getAuthMechConfigMock();
		$storageConfig = new StorageConfig(1);
		$storageConfig->setMountPoint('test');
		$storageConfig->setBackend($backendConfig);
		$storageConfig->setAuthMechanism($authMechConfig);
		$storageConfig->setBackendOptions(['user' => 'test', 'password', 'password123']);
		$storageConfig->setMountOptions(['priority' => false]);

		$this->service->expects($this->once())
			->method('getStorage')
			->with(1)
			->will($this->returnValue($storageConfig));
		$response = $this->controller->show(1);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($storageConfig, $response->getData());
	}

	public function validateStorageProvider() {
		return [
			[true, true, true],
			[false, true, false],
			[true, false, false],
			[false, false, false]
		];
	}

	/**
	 * @dataProvider validateStorageProvider
	 */
	public function testValidateStorage($backendValidate, $authMechValidate, $expectSuccess) {
		$backendConfig = $this->getMockBuilder('\OCA\Files_External\Lib\BackendConfig')
			->setConstructorArgs(['\OC\Files\Storage\SMB', 'smb', []])
			->getMock();
		$backendConfig->method('validateStorage')
			->will($this->returnValue($backendValidate));
		$backendConfig->method('isVisibleFor')
			->will($this->returnValue(true)); // not testing visibility here

		$authMechConfig = $this->getAuthMechConfigMock();
		$authMechConfig->method('validateStorage')
			->will($this->returnValue($authMechValidate));

		$storageConfig = new StorageConfig();
		$storageConfig->setMountPoint('mount');
		$storageConfig->setBackend($backendConfig);
		$storageConfig->setAuthMechanism($authMechConfig);
		$storageConfig->setBackendOptions([]);

		$this->service->expects($this->once())
			->method('createStorage')
			->will($this->returnValue($storageConfig));

		if ($expectSuccess) {
			$this->service->expects($this->once())
				->method('addStorage')
				->with($storageConfig)
				->will($this->returnValue($storageConfig));
		} else {
			$this->service->expects($this->never())
				->method('addStorage');
		}

		$response = $this->controller->create(
			'mount',
			'\OC\Files\Storage\SMB',
			'\OCA\Files_External\Lib\Auth\NullMechanism',
			array(),
			[],
			[],
			[],
			null
		);

		if ($expectSuccess) {
			$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		} else {
			$this->assertEquals(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		}
	}

}
