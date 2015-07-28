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

namespace OCA\Files_External\Lib;

use \OCA\Files_External\Lib\BackendParameter;
use \OCA\Files_External\Lib\StorageConfig;
use \OCA\Files_External\Lib\VisibilityTrait;
use \OCA\Files_External\Service\BackendService;

/**
 * External storage authentication mechanism configuration
 */
class AuthMechConfig implements \JsonSerializable {

	use VisibilityTrait;

	/** Standard mechanism schemes */
	const SCHEME_NULL = 'null';
	const SCHEME_PASSWORD = 'password';

	/** @var string implemented authentication scheme */
	private $scheme;

	/** @var string mechanism class */
	private $class;

	/** @var string human-readable mechanism name */
	private $text;

	/** @var BackendParameter[] parameters for mechanism */
	private $parameters = [];

	/** @var string|null custom JS */
	private $customJs = null;

	/**
	 * @param string $scheme Implemented authentication scheme
	 * @param string $class Mechanism class
	 * @param string $text Human-readable name
	 * @param BackendParameter[] $parameters
	 */
	public function __construct($scheme, $class, $text, $parameters) {
		$this->scheme = $scheme;
		$this->class = $class;
		$this->text = $text;
		$this->parameters = $parameters;
		$this->visibility = BackendService::VISIBILITY_DEFAULT;
		$this->allowedVisibility = BackendService::VISIBILITY_DEFAULT;
	}

	/**
	 * @return string
	 */
	public function getScheme() {
		return $this->scheme;
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
			'scheme' => $this->getScheme(),
			'name' => $this->getText(),
			'configuration' => $configuration,
		];
		if (isset($this->customJs)) {
			$data['custom'] = $this->customJs;
		}
		return $data;
	}

	/**
	 * Check if parameters are satisfied in a StorageConfig
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	public function validateStorage(StorageConfig $storage) {
		// does the backend actually support this scheme
		$supportedSchemes = $storage->getBackend()->getAuthSchemes();
		if (empty($supportedSchemes)) {
			$supportedSchemes = [self::SCHEME_NULL => true];
		}
		if (!isset($supportedSchemes[$this->scheme])) {
			return false;
		}
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
