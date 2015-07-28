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
use \OCA\Files_External\Lib\BackendParameter;

/**
 * Service class to manage backend definitions
 */
class BackendService {

	/** Visibility constants for backends and auth mechanisms */
	const VISIBILITY_NONE = 0;
	const VISIBILITY_PERSONAL = 1;
	const VISIBILITY_ADMIN = 2;
	//const VISIBILITY_ALIENS = 4;

	const VISIBILITY_DEFAULT = 3; // PERSONAL | ADMIN

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
		if (!$this->isAllowedUserBackend($backend)) {
			$backend->removeVisibility(BackendService::VISIBILITY_PERSONAL);
		}
		$this->backends[$backend->getClass()] = $backend;
	}

	/**
	 * Register an authentication mechanism
	 *
	 * @param AuthMechConfig $authMech
	 */
	public function registerAuthMechanism(AuthMechConfig $authMech) {
		if (!$this->isAllowedAuthMechanism($authMech)) {
			$authMech->removeVisibility(BackendService::VISIBILITY_PERSONAL);
		}
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
	 * Get backends visible for $visibleFor
	 *
	 * @param int $visibleFor
	 * @return BackendConfig[]
	 */
	public function getBackendsVisibleFor($visibleFor) {
		return array_filter($this->getAvailableBackends(), function($backend) use ($visibleFor) {
			return $backend->isVisibleFor($visibleFor);
		});
	}

	/**
	 * Get backends allowed to be visible for $visibleFor
	 *
	 * @param int $visibleFor
	 * @return BackendConfig[]
	 */
	public function getBackendsAllowedVisibleFor($visibleFor) {
		return array_filter($this->getAvailableBackends(), function($backend) use ($visibleFor) {
			return $backend->isAllowedVisibleFor($visibleFor);
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
	 * Get authentication mechanisms visible for $visibleFor
	 *
	 * @param int $visibleFor
	 * @return AuthMechConfig[]
	 */
	public function getAuthMechanismsVisibleFor($visibleFor) {
		return array_filter($this->getAuthMechanisms(), function($authMechanism) use ($visibleFor) {
			return $authMechanism->isVisibleFor($visibleFor);
		});
	}

	/**
	 * Get authentication mechanisms allowed to be visible for $visibleFor
	 *
	 * @param int $visibleFor
	 * @return AuthMechConfig[]
	 */
	public function getAuthMechanismsAllowedVisibleFor($visibleFor) {
		return array_filter($this->getAuthMechanisms(), function($authMechanism) use ($visibleFor) {
			return $authMechanism->isAllowedVisibleFor($visibleFor);
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
	 * Check an authentication mechanism if a user is allowed to use it
	 *
	 * @param AuthMechConfig $authMechanism
	 * @return bool
	 */
	protected function isAllowedAuthMechanism(AuthMechConfig $authMechanism) {
		return true; // not implemented
	}

	/**
	 * Load backends
	 */
	protected function loadBackends() {
		$l = $this->l10n;

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\Local', $l->t('Local'), [
				(new BackendParameter('datadir', $l->t('Location'))),
			]))
			->setAllowedVisibility(BackendService::VISIBILITY_ADMIN)
			->setPriority(BackendConfig::PRIORITY_DEFAULT + 50)
		);

		$this->registerBackend(
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

		$this->registerBackend(
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

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\FTP', $l->t('FTP'), [
				(new BackendParameter('host', $l->t('Host'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure ftps://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->setDependencyCheck('\OC\Files\Storage\FTP::checkDependencies')
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
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

		$this->registerBackend(
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
			$this->registerBackend(
				(new BackendConfig('\OC\Files\Storage\SMB', $l->t('SMB / CIFS'), [
					(new BackendParameter('host', $l->t('Host'))),
					(new BackendParameter('share', $l->t('Share'))),
					(new BackendParameter('root', $l->t('Remote subfolder')))
						->setFlag(BackendParameter::FLAG_OPTIONAL),
				]))
				->setDependencyCheck('\OC\Files\Storage\SMB::checkDependencies')
				->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
				->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
			);

			$this->registerBackend(
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

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\DAV', $l->t('WebDAV'), [
				(new BackendParameter('host', $l->t('URL'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure https://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->setDependencyCheck('\OC\Files\Storage\DAV::checkDependencies')
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\OwnCloud', $l->t('ownCloud'), [
				(new BackendParameter('host', $l->t('URL'))),
				(new BackendParameter('root', $l->t('Remote subfolder')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
				(new BackendParameter('secure', $l->t('Secure https://')))
					->setType(BackendParameter::VALUE_BOOLEAN),
			]))
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
			(new BackendConfig('\OC\Files\Storage\SFTP', $l->t('SFTP'), [
				(new BackendParameter('host', $l->t('Host'))),
				(new BackendParameter('root', $l->t('Root')))
					->setFlag(BackendParameter::FLAG_OPTIONAL),
			]))
			->addAuthScheme(AuthMechConfig::SCHEME_PASSWORD)
			->setLegacyAuthMechanismClass('\OCA\Files_External\Lib\Auth\Password\Basic')
		);

		$this->registerBackend(
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
					(new BackendParameter('user', $l->t('Username'))),
					(new BackendParameter('password', $l->t('Password')))
						->setType(BackendParameter::VALUE_PASSWORD),
			]))
		);
	}
}
