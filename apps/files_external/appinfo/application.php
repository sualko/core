<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Ross Nicoll <jrn@jrn.me.uk>
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

namespace OCA\Files_External\Appinfo;

use \OCA\Files_External\Controller\AjaxController;
use \OCP\AppFramework\App;
use \OCP\IContainer;
use \OCA\Files_External\Service\BackendService;
use \OCA\Files_External\Lib\BackendConfig;
use \OCA\Files_External\Lib\AuthMechConfig;
use \OCA\Files_External\Lib\BackendParameter;
use \OCA\Files_External\Lib\Auth\IMechanism;

/**
 * @package OCA\Files_External\Appinfo
 */
class Application extends App {
	public function __construct(array $urlParams=array()) {
		parent::__construct('files_external', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('AjaxController', function (IContainer $c) {
			return new AjaxController(
				$c->query('AppName'),
				$c->query('Request')
			);
		});

		$this->loadBackends();
		$this->loadAuthMechanisms();
	}

	/**
	 * Load storage backends provided by this app
	 */
	protected function loadBackends() {
		$container = $this->getContainer();
		$l = $container->query('OCP\\IL10N');
		$service = $container->query('OCA\\Files_External\\Service\\BackendService');

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Local', $l->t('Local'), [
				(new BackendParameter('datadir', $l->t('Location'))),
			]))
			->setAllowedVisibility(BackendService::VISIBILITY_ADMIN)
			->setPriority(BackendConfig::PRIORITY_DEFAULT + 50)
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\AmazonS3', $l->t('Amazon S3'), [
				(new BackendParameter('key', $l->t('Access Key'))),
				(new BackendParameter('secret', $l->t('Secret Key')))
					->setType(BackendParameter::VALUE_PASSWORD),
				(new BackendParameter('bucket', $l->t('Bucket'))),
				(new BackendParameter('hostname', $l->t('Hostname')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('port', $l->t('Port')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('region', $l->t('Region')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('use_ssl', $l->t('Enable SSL')))
					->setType(BackendParameter::VALUE_BOOLEAN),
				(new BackendParameter('use_path_style', $l->t('Enable Path Style')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->setDependencyCheck('\OC\Files\Storage\AmazonS3::checkDependencies')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Dropbox', $l->t('Dropbox'), [
				(new BackendParameter('configured', 'configured'))
					->setType(BackendParameter::VALUE_HIDDEN),
				(new BackendParameter('app_key', $l->t('App key'))),
				(new BackendParameter('app_secret', $l->t('App secret')))
					->setType(BackendParameter::VALUE_PASSWORD),
				(new BackendParameter('token', 'token'))
					->setType(BackendParameter::VALUE_HIDDEN),
				(new BackendParameter('token_secret', 'token_secret'))
					->setType(BackendParameter::VALUE_HIDDEN),
			]))
			->setDependencyCheck('\OC\Files\Storage\Dropbox::checkDependencies')
			->setCustomJs('dropbox')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\FTP', $l->t('FTP'), [
				(new BackendParameter('host', $l->t('Host'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure ftps://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->setDependencyCheck('\OC\Files\Storage\FTP::checkDependencies')
			->addAuthScheme(IMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Google', $l->t('Google Drive'), [
				(new BackendParameter('configured', 'configured'))
					->setType(BackendParameter::VALUE_HIDDEN),
				(new BackendParameter('client_id', $l->t('Client ID'))),
				(new BackendParameter('client_secret', $l->t('Client secret')))
					->setType(BackendParameter::VALUE_PASSWORD),
				(new BackendParameter('token', 'token'))
					->setType(BackendParameter::VALUE_HIDDEN),
			]))
			->setDependencyCheck('\OC\Files\Storage\Google::checkDependencies')
			->setCustomJs('google')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Swift', $l->t('OpenStack Object Storage'), [
				(new BackendParameter('user', $l->t('Username'))),
				(new BackendParameter('bucket', $l->t('Bucket'))),
				(new BackendParameter('region', $l->t('Region (optional for OpenStack Object Storage)')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('key', $l->t('API Key (required for Rackspace Cloud Files)')))
					->setType(BackendParameter::VALUE_PASSWORD)
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('tenant', $l->t('Tenantname (required for OpenStack Object Storage)')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('password', $l->t('Password (required for OpenStack Object Storage)')))
					->setType(BackendParameter::VALUE_PASSWORD)
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('service_name', $l->t('Service Name (required for OpenStack Object Storage)')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('url', $l->t('URL of identity endpoint (required for OpenStack Object Storage)')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('timeout', $l->t('Timeout of HTTP requests in seconds')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
			]))
			->setDependencyCheck('\OC\Files\Storage\Swift::checkDependencies')
		);

		if (!\OC_Util::runningOnWindows()) {
			$service->registerBackend(
				(new BackendConfig('\OC\Files\Storage\SMB', $l->t('SMB / CIFS'), [
					(new BackendParameter('host', $l->t('Host'))),
					(new BackendParameter('share', $l->t('Share'))),
					(new BackendParameter('root', $l->t('Remote subfolder')))
						->setFlag(BackendParameter::FLAG_OPTIONAL),
				]))
				->setDependencyCheck('\OC\Files\Storage\SMB::checkDependencies')
				->addAuthScheme(IMechanism::SCHEME_PASSWORD)
				->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
			);

			$service->registerBackend(
				(new BackendConfig('\OC\Files\Storage\SMB_OC', $l->t('SMB / CIFS using OC login'), [
					(new BackendParameter('host', $l->t('Host'))),
					(new BackendParameter('username_as_share', $l->t('Username as share')))
						->setType(BackendParameter::VALUE_BOOLEAN),
					(new BackendParameter('share', $l->t('Share')))
						->setFlag(BackendParameter::FLAG_OPTIONAL),
					(new BackendParameter('root', $l->t('Remote subfolder')))
						->setFlag(BackendParameter::FLAG_OPTIONAL),
				]))
				->setDependencyCheck('\OC\Files\Storage\SMB_OC::checkDependencies')
				->setPriority(BackendConfig::PRIORITY_DEFAULT - 10)
			);
		}

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\DAV', $l->t('WebDAV'), [
				(new BackendParameter('host', $l->t('URL'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure https://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->setDependencyCheck('\OC\Files\Storage\DAV::checkDependencies')
			->addAuthScheme(IMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\OwnCloud', $l->t('ownCloud'), [
				(new BackendParameter('host', $l->t('URL'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure https://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->addAuthScheme(IMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\SFTP', $l->t('SFTP'), [
				(new BackendParameter('host', $l->t('Host'))),
				(new BackendParameter('root', $l->t('Root')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
			]))
			->addAuthScheme(IMechanism::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$service->registerBackend(
			(new BackendConfig('\OC\Files\Storage\SFTP_Key', $l->t('SFTP with secret key login'), [
				(new BackendParameter('host', $l->t('Host'))),
				(new BackendParameter('user', $l->t('Username'))),
				(new BackendParameter('public_key', $l->t('Public key'))),
				(new BackendParameter('private_key', 'private_key'))
					->setType(BackendParameter::VALUE_HIDDEN),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
			]))
			->setCustomJs('sftp_key')
		);
	}

	/**
	 * Load authentication mechanisms provided by this app
	 */
	protected function loadAuthMechanisms() {
		$container = $this->getContainer();
		$l = $container->query('OCP\\IL10N');
		$service = $container->query('OCA\\Files_External\\Service\\BackendService');

		$service->registerAuthMechanism(
			(new AuthMechConfig(
				$container->query('OCA\Files_External\Lib\Auth\NullMechanism'),
				$l->t('None'), []
			))
		);

		// IMechanism::SCHEME_PASSWORD mechanisms
		$service->registerAuthMechanism(
			(new AuthMechConfig(
				$container->query('OCA\Files_External\Lib\Auth\Password\Basic'),
				$l->t('Username and password'), [
					(new BackendParameter('user', $l->t('Username'))),
					(new BackendParameter('password', $l->t('Password')))
						->setType(BackendParameter::VALUE_PASSWORD),
			]))
		);
	}

}
