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

namespace OCA\Files_External\Tests;

use \OCA\Files_External\Lib\AuthMechConfig;

class AuthMechConfigTest extends \Test\TestCase {

	public function testJsonSerialization() {
		$param = $this->getMockBuilder('\OCA\Files_External\Lib\BackendParameter')
			->disableOriginalConstructor()
			->getMock();
		$param->method('getName')->willReturn('foo');

		$authMechConfig = new AuthMechConfig('scheme', '\Auth\Mechanism', 'auth', [$param]);
		$authMechConfig->setCustomJs('foo/bar.js');

		$json = $authMechConfig->jsonSerialize();

		$this->assertEquals('scheme', $json['scheme']);
		$this->assertEquals('auth', $json['name']);
		$this->assertEquals('foo/bar.js', $json['custom']);

		$configuration = $json['configuration'];
		$this->assertArrayHasKey('foo', $configuration);
	}

	public function validateStorageProvider() {
		return [
			[true, 'scheme', ['foo' => true, 'bar' => true, 'baz' => true]],
			[false, 'scheme', ['foo' => true, 'bar' => false]], // parameter not satisfied
			[true, 'foobar', ['foo' => true]],
			[false, 'barfoo', ['foo' => true]], // auth scheme not supported
		];
	}

	/**
	 * @dataProvider validateStorageProvider
	 */
	public function testValidateStorage($expectedSuccess, $scheme, $params) {
		$backendParams = [];
		foreach ($params as $name => $valid) {
			$param = $this->getMockBuilder('\OCA\Files_External\Lib\BackendParameter')
				->disableOriginalConstructor()
				->getMock();
			$param->method('getName')
				->willReturn($name);
			$param->expects($this->atMost(1))
				->method('validateValue')
				->willReturn($valid);
			$backendParams[] = $param;
		}

		$storageConfig = $this->getMockBuilder('\OCA\Files_External\Lib\StorageConfig')
			->disableOriginalConstructor()
			->getMock();
		$storageConfig->expects($this->atMost(1))
			->method('getBackendOptions')
			->willReturn([]);

		$backendConfig = $this->getMockBuilder('\OCA\Files_External\Lib\BackendConfig')
			->disableOriginalConstructor()
			->getMock();
		$backendConfig->expects($this->once())
			->method('getAuthSchemes')
			->willReturn(['scheme' => true, 'foobar' => true]);

		$storageConfig->expects($this->once())
			->method('getBackend')
			->willReturn($backendConfig);

		$authMechConfig = new AuthMechConfig($scheme, '\Auth\Mechanism', 'auth', $backendParams);

		$this->assertEquals($expectedSuccess, $authMechConfig->validateStorage($storageConfig));
	}

}
