<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	public function create(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('api/health', 'Api:Health:default');
		return $router;
	}
}
