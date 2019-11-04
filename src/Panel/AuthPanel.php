<?php

namespace TinyAuth\Panel;

use Cake\Core\InstanceConfigTrait;
use Cake\Event\Event;
use DebugKit\DebugPanel;
use TinyAuth\Auth\AclTrait;
use TinyAuth\Auth\AllowTrait;
use TinyAuth\Utility\Config;
use TinyAuth\Utility\TinyAuth;

/**
 * A panel to show authentication and authorization data for current request.
 */
class AuthPanel extends DebugPanel {

	use AclTrait;
	use AllowTrait;
	use InstanceConfigTrait;

	const ICON_RESTRICTED = "\u{1f512}";
	const ICON_PUBLIC = "\u{1f513}";

	/**
	 * Defines which plugin this panel is from so the element can be located.
	 *
	 * @var string
	 */
	public $plugin = 'TinyAuth';

	/**
	 * @var array
	 */
	protected $_data = [];

	/**
	 * @var bool|null
	 */
	protected $isPublic;

	/**
	 * @var array
	 */
	protected $_defaultConfig = [
	];

	public function __construct() {
		$this->setConfig(Config::all());
	}

	/**
	 * Data collection callback.
	 *
	 * @param \Cake\Event\Event $event The shutdown event.
	 *
	 * @return void
	 */
	public function shutdown(Event $event) {
		/** @var  \Cake\Controller\Controller $controller */
		$controller = $event->getSubject();
		$request = $controller->request;

		$params = $this->_getParams($request->getAttribute('params'));
		$availableRoles = (new TinyAuth())->getAvailableRoles();
		$data = [
			'params' => $params,
			'path' => $this->_getPath($params),
			'availableRoles' => $availableRoles,
		];

		$rule = $this->_getAllowRule($params);
		$this->isPublic = !empty($rule);

		$controller->loadComponent('TinyAuth.AuthUser');
		$user = $controller->AuthUser->user();
		$data['user'] = $user;

		$roles = $controller->AuthUser->roles();
		$data['roles'] = $roles;

		$access = [];
		if ($user) {
			foreach ($availableRoles as $role => $id) {
				$user = $this->_injectRole($user, $role, $id);
				$access[$role] = $this->_checkUser($user, $params);
			}
		}
		$data['access'] = $access;

		$this->_data = $data;
	}

	/**
	 * Get the data for this panel
	 *
	 * @return array
	 */
	public function data() {
		$data = [
			'isPublic' => $this->isPublic,
		];
		return $this->_data + $data;
	}

	/**
	 * Get the summary data for a panel.
	 *
	 * This data is displayed in the toolbar even when the panel is collapsed.
	 *
	 * @return string
	 */
	public function summary() {
		if ($this->isPublic === null) {
			return '';
		}

		return $this->isPublic ? static::ICON_PUBLIC : static::ICON_RESTRICTED; // For now no HTML possible.
	}

	/**
	 * @param array $user
	 * @param string $role
	 * @param int $id
	 *
	 * @return array
	 */
	protected function _injectRole(array $user, $role, $id) {
		if (!$this->getConfig('multiRole')) {
			$user[$this->getConfig('roleColumn')] = $id;

			return $user;
		}

		if (isset($user[$this->getConfig('rolesTable')])) {
			$user[$this->getConfig('rolesTable')] = [$role => $id];

			return $user;
		}

		$pivotTableName = $this->_pivotTableName();
		if (isset($user[$pivotTableName])) {
			$user[$pivotTableName] = [$role => $id];
			return $user;
		}

		//TODO: other edge cases?

		return $user;
	}

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	protected function _getParams(array $params) {
		$params += [
			'prefix' => null,
			'plugin' => null,
		];
		unset($params['isAjax']);

		return $params;
	}

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	protected function _getPath(array $params) {
		$path = $params['controller'];
		if ($params['prefix']) {
			$path = $params['prefix'] . '/' . $path;
		}
		if ($params['plugin']) {
			$path = $params['plugin'] . '.' . $path;
		}

		return $path;
	}

}
