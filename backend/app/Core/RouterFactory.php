<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;
use Nette\Routing\Route;


final class RouterFactory
{
	public function create(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('api/health', 'Api:Health:default');
		$router->addRoute('api/day', 'Api:Day:default');
		$router->addRoute('api/events/<id>', [
			'presenter' => 'Api:Event',
			'action' => 'default',
			'id' => [Route::Pattern => '\d{1,9}'],
		]);
		return $router;
	}
}
