<?php

namespace TinyAuth\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use TestApp\Controller\OffersController;
use TinyAuth\Controller\Component\AuthComponent;

/**
 * TinyAuth AuthComponent to handle all authentication in a central ini file.
 */
class AuthComponentTest extends TestCase {

	/**
	 * @var \TinyAuth\Controller\Component\AuthComponent
	 */
	protected $AuthComponent;

	/**
	 * @var array
	 */
	protected $componentConfig = [];

	/**
	 * @return void
	 */
	public function setUp() {
		$this->componentConfig = [
			'allowFilePath' => Plugin::path('TinyAuth') . 'tests' . DS . 'test_files' . DS,
			'autoClearCache' => true,
		];
	}

	/**
	 * @return void
	 */
	public function testValid() {
		$request = new ServerRequest(['params' => [
			'controller' => 'Users',
			'action' => 'view',
			'plugin' => null,
			'_ext' => null,
			'pass' => [1]
		]]);
		$controller = $this->getControllerMock($request);

		$registry = new ComponentRegistry($controller);
		$this->AuthComponent = new AuthComponent($registry, $this->componentConfig);

		$config = [];
		$this->AuthComponent->initialize($config);

		$event = new Event('Controller.startup', $controller);
		$response = $this->AuthComponent->startup($event);
		$this->assertNull($response);
	}

	/**
	 * @return void
	 */
	public function testValidAnyAction() {
		$request = new ServerRequest(['params' => [
			'plugin' => 'Extras',
			'controller' => 'Offers',
			'action' => 'index',
			'_ext' => null,
			'pass' => [1]
		]]);
		$controller = new OffersController($request);

		$registry = new ComponentRegistry($controller);
		$this->AuthComponent = new AuthComponent($registry, $this->componentConfig);

		$config = [];
		$this->AuthComponent->initialize($config);

		$event = new Event('Controller.startup', $controller);
		$response = $this->AuthComponent->startup($event);
		$this->assertNull($response);
	}

	/**
	 * @return void
	 */
	public function testDeniedActionInController() {
		$request = new ServerRequest(['params' => [
			'plugin' => 'Extras',
			'controller' => 'Offers',
			'action' => 'denied',
			'_ext' => null,
			'pass' => [1],
		]]);
		$controller = new OffersController($request);
		$controller->loadComponent('TinyAuth.Auth', $this->componentConfig);

		$config = [];
		$controller->Auth->initialize($config);

		$event = new Event('Controller.beforeFilter', $controller);
		$controller->beforeFilter($event);

		$event = new Event('Controller.startup', $controller);
		$response = $controller->Auth->startup($event);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame(302, $response->getStatusCode());
	}

	/**
	 * @return void
	 */
	public function testDeniedAction() {
		$request = new ServerRequest(['params' => [
			'plugin' => 'Extras',
			'controller' => 'Offers',
			'action' => 'superPrivate',
			'_ext' => null,
			'pass' => [1],
		]]);
		$controller = new OffersController($request);
		$controller->loadComponent('TinyAuth.Auth', $this->componentConfig);

		$config = [];
		$controller->Auth->initialize($config);

		$event = new Event('Controller.beforeFilter', $controller);
		$controller->beforeFilter($event);

		$event = new Event('Controller.startup', $controller);
		$response = $controller->Auth->startup($event);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame(302, $response->getStatusCode());
	}

	/**
	 * @return void
	 */
	public function testInvalid() {
		$request = new ServerRequest(['params' => [
			'controller' => 'FooBar',
			'action' => 'index',
			'plugin' => null,
			'_ext' => null,
			'pass' => [],
		]]);
		$controller = $this->getControllerMock($request);

		$registry = new ComponentRegistry($controller);
		$this->AuthComponent = new AuthComponent($registry, $this->componentConfig);

		$config = [];
		$this->AuthComponent->initialize($config);

		$event = new Event('Controller.startup', $controller);
		$response = $this->AuthComponent->startup($event);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame(302, $response->getStatusCode());
	}

	/**
	 * @param \Cake\Http\ServerRequest $request
	 * @return \Cake\Controller\Controller|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getControllerMock(ServerRequest $request) {
		$controller = $this->getMockBuilder(Controller::class)
			->setConstructorArgs([$request])
			->setMethods(['isAction'])
			->getMock();

		$controller->expects($this->once())->method('isAction')->willReturn(true);

		return $controller;
	}

}
