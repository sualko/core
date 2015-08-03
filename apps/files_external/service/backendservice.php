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

use \OCA\Files_External\Lib\BackendConfig;
use \OCA\Files_External\Lib\AuthMechConfig;

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
	 */
	public function __construct(
		IConfig $config
	) {
		$this->config = $config;

		// Load config values
		if ($this->config->getAppValue('files_external', 'allow_user_mounting', 'yes') !== 'yes') {
			$this->userMountingAllowed = false;
		}
		$this->userMountingBackends = explode(',',
			$this->config->getAppValue('files_external', 'user_mounting_backends', '')
		);
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
}
