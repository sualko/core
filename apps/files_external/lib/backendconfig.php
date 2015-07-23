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

namespace OCA\Files_External\Lib;

use \OCA\Files_External\Lib\BackendParameter;
use \OCA\Files_External\Lib\BackendDependency;
use \OCA\Files_External\Lib\StorageConfig;

/**
 * External storage backend configuration
 */
class BackendConfig implements \JsonSerializable {

	/** Visibility constants */
	const VISIBILITY_NONE = 0;
	const VISIBILITY_PERSONAL = 1;
	const VISIBILITY_ADMIN = 2;
	//const VISIBILITY_ALIENS = 4;

	const VISIBILITY_DEFAULT = 3; // PERSONAL | ADMIN

	/** Initial priority constants */
	const PRIORITY_DEFAULT = 100;

	/** @var string backend class */
	private $class;

	/** @var string human-readable backend name */
	private $text;

	/** @var BackendParameter[] parameters for backend */
	private $parameters = [];

	/** @var int initial priority */
	private $priority = self::PRIORITY_DEFAULT;

	/** @var bool has dependencies */
	private $hasDependencies = false;

	/** @var string|null custom JS */
	private $customJs = null;

	/** @var int visibility, see self::VISIBILITY_* constants */
	private $visibility = self::VISIBILITY_DEFAULT;

	/**
	 * @param string $class Backend class
	 * @param string $text Human-readable name
	 * @param BackendParameter[] $parameters
	 */
	public function __construct($class, $text, $parameters) {
		$this->class = $class;
		$this->text = $text;
		$this->parameters = $parameters;
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->class;
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * @return BackendParameter[]
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @return int
	 */
	public function getPriority() {
		return $this->priority;
	}

	/**
	 * @param int $priority
	 * @return self
	 */
	public function setPriority($priority) {
		$this->priority = $priority;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasDependencies() {
		return $this->hasDependencies;
	}

	/**
	 * @param bool $hasDependencies
	 * @return self
	 */
	public function setHasDependencies($hasDependencies) {
		$this->hasDependencies = $hasDependencies;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getCustomJs() {
		return $this->customJs;
	}

	/**
	 * @param string $custom
	 * @return self
	 */
	public function setCustomJs($custom) {
		$this->customJs = $custom;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getVisibility() {
		return $this->visibility;
	}

	/**
	 * Check if the backend is visible for a user type
	 *
	 * @param int $visibility
	 * @return bool
	 */
	public function isVisibleFor($visibility) {
		if ($this->visibility & $visibility) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $visibility
	 * @return self
	 */
	public function setVisibility($visibility) {
		$this->visibility = $visibility;
		return $this;
	}

	/**
	 * Serialize into JSON for client-side JS
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		$configuration = [];
		foreach ($this->getParameters() as $parameter) {
			$configuration[$parameter->getName()] = $parameter;
		}

		$data = [
			'backend' => $this->getText(),
			'priority' => $this->getPriority(),
			'configuration' => $configuration,
		];
		if (isset($this->customJs)) {
			$data['custom'] = $this->customJs;
		}
		return $data;
	}

	/**
	 * Check if backend is valid for use
	 *
	 * @return BackendDependency[] Unsatisfied dependencies
	 */
	public function checkDependencies() {
		$ret = [];

		if ($this->hasDependencies()) {
			$class = $this->getClass();
			$result = $class::checkDependencies();
			if ($result !== true) {
				if (!is_array($result)) {
					$result = [$result];
				}
				foreach ($result as $key => $value) {
					if (!($value instanceof BackendDependency)) {
						$module = null;
						$message = null;
						if (is_numeric($key)) {
							$module = $value;
						} else {
							$module = $key;
							$message = $value;
						}
						$value = new BackendDependency($module, $this);
						$value->setMessage($message);
					}
					$ret[] = $value;
				}
			}
		}

		return $ret;
	}

	/**
	 * Check if parameters are satisfied in a StorageConfig
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	public function validateStorage(StorageConfig $storage) {
		$options = $storage->getBackendOptions();
		foreach ($this->parameters as $parameter) {
			$value = isset($options[$parameter->getName()]) ?
				$options[$parameter->getName()] : null;
			if (!$parameter->validateValue($value)) {
				return false;
			}
		}
		return true;
	}
}
