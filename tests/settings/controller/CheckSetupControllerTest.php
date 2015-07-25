<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
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

namespace OC\Settings\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OC_Util;
use Test\TestCase;

/**
 * Class CheckSetupControllerTest
 *
 * @package OC\Settings\Controller
 */
class CheckSetupControllerTest extends TestCase {
	/** @var CheckSetupController */
	private $checkSetupController;
	/** @var IRequest */
	private $request;
	/** @var IConfig */
	private $config;
	/** @var IClientService */
	private $clientService;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var OC_Util */
	private $util;

	public function setUp() {
		parent::setUp();

		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()->getMock();
		$this->config = $this->getMockBuilder('\OCP\IConfig')
			->disableOriginalConstructor()->getMock();
		$this->config = $this->getMockBuilder('\OCP\IConfig')
			->disableOriginalConstructor()->getMock();
		$this->clientService = $this->getMockBuilder('\OCP\Http\Client\IClientService')
			->disableOriginalConstructor()->getMock();
		$this->util = $this->getMockBuilder('\OC_Util')
			->disableOriginalConstructor()->getMock();
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()->getMock();

		$this->checkSetupController = new CheckSetupController(
			'settings',
			$this->request,
			$this->config,
			$this->clientService,
			$this->urlGenerator,
			$this->util
		);
	}

	public function testIsInternetConnectionWorkingDisabledViaConfig() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->will($this->returnValue(false));

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'isInternetConnectionWorking'
			)
		);
	}

	public function testIsInternetConnectionWorkingCorrectly() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->will($this->returnValue(true));

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->at(0))
			->method('get')
			->with('https://www.owncloud.org/', []);
		$client->expects($this->at(1))
			->method('get')
			->with('http://www.owncloud.org/', []);

		$this->clientService->expects($this->once())
			->method('newClient')
			->will($this->returnValue($client));


		$this->assertTrue(
			self::invokePrivate(
				$this->checkSetupController,
				'isInternetConnectionWorking'
			)
		);
	}

	public function testIsInternetConnectionHttpsFail() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->will($this->returnValue(true));

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->at(0))
			->method('get')
			->with('https://www.owncloud.org/', [])
			->will($this->throwException(new \Exception()));

		$this->clientService->expects($this->once())
			->method('newClient')
			->will($this->returnValue($client));

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'isInternetConnectionWorking'
			)
		);
	}

	public function testIsInternetConnectionHttpFail() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->will($this->returnValue(true));

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->at(0))
			->method('get')
			->with('https://www.owncloud.org/', []);
		$client->expects($this->at(1))
			->method('get')
			->with('http://www.owncloud.org/', [])
			->will($this->throwException(new \Exception()));

		$this->clientService->expects($this->once())
			->method('newClient')
			->will($this->returnValue($client));

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'isInternetConnectionWorking'
			)
		);
	}

	public function testIsMemcacheConfiguredFalse() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('memcache.local', null)
			->will($this->returnValue(null));

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'isMemcacheConfigured'
			)
		);
	}

	public function testIsMemcacheConfiguredTrue() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('memcache.local', null)
			->will($this->returnValue('SomeProvider'));

		$this->assertTrue(
			self::invokePrivate(
				$this->checkSetupController,
				'isMemcacheConfigured'
			)
		);
	}

	public function testForwardedForHeadersWorkingFalse() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('trusted_proxies', [])
			->willReturn(['1.2.3.4']);
		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'forwardedForHeadersWorking'
			)
		);
	}

	public function testForwardedForHeadersWorkingTrue() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('trusted_proxies', [])
			->willReturn(['1.2.3.4']);
		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn('4.3.2.1');

		$this->assertTrue(
			self::invokePrivate(
				$this->checkSetupController,
				'forwardedForHeadersWorking'
			)
		);
	}

	public function testCheck() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->will($this->returnValue(true));
		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with('memcache.local', null)
			->will($this->returnValue('SomeProvider'));
		$this->config->expects($this->at(2))
			->method('getSystemValue')
			->with('trusted_proxies', [])
			->willReturn(['1.2.3.4']);

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn('4.3.2.1');

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->at(0))
			->method('get')
			->with('https://www.owncloud.org/', []);
		$client->expects($this->at(1))
			->method('get')
			->with('http://www.owncloud.org/', [])
			->will($this->throwException(new \Exception()));

		$this->clientService->expects($this->once())
			->method('newClient')
			->will($this->returnValue($client));

		$this->util->expects($this->once())
			->method('isHtaccessWorking')
			->will($this->returnValue(true));
		$this->urlGenerator->expects($this->at(0))
			->method('linkToDocs')
			->with('admin-performance')
			->willReturn('http://doc.owncloud.org/server/go.php?to=admin-performance');
		$this->urlGenerator->expects($this->at(1))
			->method('linkToDocs')
			->with('admin-security')
			->willReturn('https://doc.owncloud.org/server/8.1/admin_manual/configuration_server/hardening.html');
		$this->urlGenerator->expects($this->at(2))
			->method('linkToDocs')
			->with('admin-reverse-proxy')
			->willReturn('reverse-proxy-doc-link');

		$expected = new DataResponse(
			[
				'serverHasInternetConnection' => false,
				'dataDirectoryProtected' => true,
				'isMemcacheConfigured' => true,
				'memcacheDocs' => 'http://doc.owncloud.org/server/go.php?to=admin-performance',
				'isUrandomAvailable' => self::invokePrivate($this->checkSetupController, 'isUrandomAvailable'),
				'securityDocs' => 'https://doc.owncloud.org/server/8.1/admin_manual/configuration_server/hardening.html',
				'forwardedForHeadersWorking' => true,
				'reverseProxyDocs' => 'reverse-proxy-doc-link',
			]
		);
		$this->assertEquals($expected, $this->checkSetupController->check());
	}
}
