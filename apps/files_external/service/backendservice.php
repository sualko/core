<?php
/**
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
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

namespace OCA\Files_External\Service;

use \OCP\IConfig;
use \OCP\IL10N;

use \OCA\Files_External\Lib\BackendConfig;
use \OCA\Files_External\Lib\AuthMechConfig;
use \OCA\Files_External\Lib\BackendParameter as Param;

/**
 * Service class to manage backend definitions
 */
class BackendService {

	/** @var IConfig */
	protected $config;

	/** @var IL10N */
	protected $l10n;

	/** @var bool */
	private $userMountingAllowed = true;

	/** @var string[] */
	private $userMountingBackends = [];

	/** @var BackendConfig[] */
	private $backends = [];

	/** @var AuthMechConfig[] */
	private $authMechanisms = [];

	/**
	 * @param IConfig $config
	 * @param IL10N $l10n
	 */
	public function __construct(
		IConfig $config,
		IL10N $l10n
	) {
		$this->config = $config;
		$this->l10n = $l10n;

		// Load config values
		if ($this->config->getAppValue('files_external', 'allow_user_mounting', 'yes') !== 'yes') {
			$this->userMountingAllowed = false;
		}
		$this->userMountingBackends = explode(',',
			$this->config->getAppValue('files_external', 'user_mounting_backends', '')
		);

		$this->loadBackends();
		$this->loadAuthMechanisms();
	}

	/**
	 * Register a backend
	 *
	 * @param BackendConfig $backend
	 */
	public function registerBackend(BackendConfig $backend) {
		if (! $this->isAllowedUserBackend($backend)) {
			$backend->setVisibility(BackendConfig::VISIBILITY_ADMIN);
		}
		$this->backends[$backend->getClass()] = $backend;
	}

	/**
	 * Register an authentication mechanism
	 *
	 * @param AuthMechConfig $authMech
	 */
	public function registerAuthMechanism(AuthMechConfig $authMech) {
		$this->authMechanisms[$authMech->getClass()] = $authMech;
	}

	/**
	 * Get all backends
	 *
	 * @return BackendConfig[]
	 */
	public function getBackends() {
		return $this->backends;
	}

	/**
	 * Get all available backends
	 *
	 * @return BackendConfig[]
	 */
	public function getAvailableBackends() {
		return array_filter($this->getBackends(), function($backend) {
			return empty($backend->checkDependencies());
		});
	}

	/**
	 * Get user-allowed backends only
	 *
	 * @return BackendConfig[]
	 */
	public function getUserBackends() {
		return array_filter($this->getAvailableBackends(), function($backend) {
			return $backend->isVisibleFor(BackendConfig::VISIBILITY_PERSONAL);
		});
	}

	/**
	 * @param string $class Backend class name
	 * @return BackendConfig|null
	 */
	public function getBackend($class) {
		if (isset($this->backends[$class])) {
			return $this->backends[$class];
		}
		return null;
	}

	/**
	 * Get all authentication mechanisms
	 *
	 * @return AuthMechConfig[]
	 */
	public function getAuthMechanisms() {
		return $this->authMechanisms;
	}

	/**
	 * Get all authentication mechanisms for schemes
	 *
	 * @param string[] $schemes
	 * @return AuthMechConfig[]
	 */
	public function getAuthMechanismsByScheme(array $schemes) {
		return array_filter($this->getAuthMechanisms(), function($authMech) use ($schemes) {
			return in_array($authMech->getScheme(), $schemes, true);
		});
	}

	/**
	 * @param string $class
	 * @return AuthMechConfig|null
	 */
	public function getAuthMechanism($class) {
		if (isset($this->authMechanisms[$class])) {
			return $this->authMechanisms[$class];
		}
		return null;
	}

	/**
	 * @return bool
	 */
	public function isUserMountingAllowed() {
		return $this->userMountingAllowed;
	}

	/**
	 * Check a backend if a user is allowed to mount it
	 *
	 * @param BackendConfig $backend
	 * @return bool
	 */
	protected function isAllowedUserBackend(BackendConfig $backend) {
		if ($this->userMountingAllowed &&
			in_array($backend->getClass(), $this->userMountingBackends)
		) {
			return true;
		}
		return false;
	}

	/**
	 * Load backends
	 */
	protected function loadBackends() {
		$l = $this->l10n;

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Local', $l->t('Local'), [
				(new Param('datadir', $l->t('Location'))),
			]))
			->setVisibility(BackendConfig::VISIBILITY_ADMIN)
			->setPriority(BackendConfig::PRIORITY_DEFAULT + 50)
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\AmazonS3', $l->t('Amazon S3'), [
				(new Param('key', $l->t('Access Key'))),
				(new Param('secret', $l->t('Secret Key')))
					->setType(Param::VALUE_PASSWORD),
				(new Param('bucket', $l->t('Bucket'))),
				(new Param('hostname', $l->t('Hostname')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('port', $l->t('Port')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('region', $l->t('Region')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('use_ssl', $l->t('Enable SSL')))
					->setType(Param::VALUE_BOOLEAN),
				(new Param('use_path_style', $l->t('Enable Path Style')))
					->setType(Param::VALUE_BOOLEAN),
			]))
			->setHasDependencies(true)
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Dropbox', $l->t('Dropbox'), [
				(new Param('configured', 'configured'))
					->setType(Param::VALUE_HIDDEN),
				(new Param('app_key', $l->t('App key'))),
				(new Param('app_secret', $l->t('App secret')))
					->setType(Param::VALUE_PASSWORD),
				(new Param('token', 'token'))
					->setType(Param::VALUE_HIDDEN),
				(new Param('token_secret', 'token_secret'))
					->setType(Param::VALUE_HIDDEN),
			]))
			->setHasDependencies(true)
			->setCustomJs('dropbox')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\FTP', $l->t('FTP'), [
				(new Param('host', $l->t('Host'))),
				(new Param('root', $l->t('Remote subfolder')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('secure', $l->t('Secure ftps://')))
					->setType(Param::VALUE_BOOLEAN),
			]))
			->setHasDependencies(true)
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Google', $l->t('Google Drive'), [
				(new Param('configured', 'configured'))
					->setType(Param::VALUE_HIDDEN),
				(new Param('client_id', $l->t('Client ID'))),
				(new Param('client_secret', $l->t('Client secret')))
					->setType(Param::VALUE_PASSWORD),
				(new Param('token', 'token'))
					->setType(Param::VALUE_HIDDEN),
			]))
			->setHasDependencies(true)
			->setCustomJs('google')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Swift', $l->t('OpenStack Object Storage'), [
				(new Param('user', $l->t('Username'))),
				(new Param('bucket', $l->t('Bucket'))),
				(new Param('region', $l->t('Region (optional for OpenStack Object Storage)')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('key', $l->t('API Key (required for Rackspace Cloud Files)')))
					->setType(Param::VALUE_PASSWORD)
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('tenant', $l->t('Tenantname (required for OpenStack Object Storage)')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('password', $l->t('Password (required for OpenStack Object Storage)')))
					->setType(Param::VALUE_PASSWORD)
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('service_name', $l->t('Service Name (required for OpenStack Object Storage)')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('url', $l->t('URL of identity endpoint (required for OpenStack Object Storage)')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('timeout', $l->t('Timeout of HTTP requests in seconds')))
					->setFlag(Param::FLAG_OPTIONAL),
			]))
			->setHasDependencies(true)
		);

		if (!\OC_Util::runningOnWindows()) {
			$this->registerBackend(
				(new BackendConfig('\OC\Files\Storage\SMB', $l->t('SMB / CIFS'), [
					(new Param('host', $l->t('Host'))),
					(new Param('share', $l->t('Share'))),
					(new Param('root', $l->t('Remote subfolder')))
						->setFlag(Param::FLAG_OPTIONAL),
				]))
				->setHasDependencies(true)
				->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
				->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
			);

			$this->registerBackend(
				(new BackendConfig('\OC\Files\Storage\SMB_OC', $l->t('SMB / CIFS using OC login'), [
					(new Param('host', $l->t('Host'))),
					(new Param('username_as_share', $l->t('Username as share')))
						->setType(Param::VALUE_BOOLEAN),
					(new Param('share', $l->t('Share')))
						->setFlag(Param::FLAG_OPTIONAL),
					(new Param('root', $l->t('Remote subfolder')))
						->setFlag(Param::FLAG_OPTIONAL),
				]))
				->setHasDependencies(true)
				->setPriority(BackendConfig::PRIORITY_DEFAULT - 10)
			);
		}

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\DAV', $l->t('WebDAV'), [
				(new Param('host', $l->t('URL'))),
				(new Param('root', $l->t('Remote subfolder')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('secure', $l->t('Secure https://')))
					->setType(Param::VALUE_BOOLEAN),
			]))
			->setHasDependencies(true)
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\OwnCloud', $l->t('ownCloud'), [
				(new Param('host', $l->t('URL'))),
				(new Param('root', $l->t('Remote subfolder')))
					->setFlag(Param::FLAG_OPTIONAL),
				(new Param('secure', $l->t('Secure https://')))
					->setType(Param::VALUE_BOOLEAN),
			]))
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\SFTP', $l->t('SFTP'), [
				(new Param('host', $l->t('Host'))),
				(new Param('root', $l->t('Root')))
					->setFlag(Param::FLAG_OPTIONAL),
			]))
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\SFTP_Key', $l->t('SFTP with secret key login'), [
				(new Param('host', $l->t('Host'))),
				(new Param('user', $l->t('Username'))),
				(new Param('public_key', $l->t('Public key'))),
				(new Param('private_key', 'private_key'))
					->setType(Param::VALUE_HIDDEN),
				(new Param('root', $l->t('Remote subfolder')))
					->setFlag(Param::FLAG_OPTIONAL),
			]))
			->setCustomJs('sftp_key')
		);
	}

	/**
	 * Load authentication mechanisms
	 */
	protected function loadAuthMechanisms() {
		$l = $this->l10n;

		$this->registerAuthMechanism(
			(new AuthMechConfig(AuthMechConfig::SCHEME_NULL,
				'\OCA\Files_External\Lib\Auth\NullMechanism', $l->t('None'), []
			))
		);

		// AuthMechConfig::SCHEME_PASSWORD mechanisms
		$this->registerAuthMechanism(
			(new AuthMechConfig(AuthMechConfig::SCHEME_PASSWORD,
				'\OCA\Files_External\Lib\Auth\Password\Basic', $l->t('Username and password'), [
					(new Param('user', $l->t('Username'))),
					(new Param('password', $l->t('Password')))
						->setType(Param::VALUE_PASSWORD),
			]))
		);
	}
}
