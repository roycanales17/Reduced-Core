<?php

	namespace Handler;

	class RouteHandler
	{
		private array $routes = [];
		private array $render = [];
		private array $perform = [];

		/**
		 * Registers a page route to serve a specific file based on the given URI.
		 *
		 * @param string $uri The URI that maps to the page.
		 * @param string $path The relative file path to the page within the `pages` directory.
		 *
		 * @return void
		 */
		public
		function page(string $uri, string $path): void
		{
			if (file_exists(root . 'pages/' . $path)) {
				$this->routes[trim($uri, '/')] = '/' . ltrim($path, '/');
			}
		}

		/**
		 * Associates a URI with a component class for rendering dynamic content.
		 *
		 * @param string $uri The URI that maps to the component.
		 * @param string $className The fully qualified name of the class responsible for rendering the component.
		 *
		 * @return void
		 */
		public
		function render(string $uri, string $className): void
		{
			if (class_exists($className)) {
				$this->render[trim($uri, '/')] = $className;
			}
		}

		/**
		 * Associates a URI with a class method to perform a specific action.
		 *
		 * @param string $uri The URI that maps to the action.
		 * @param array $action An array containing the class name and method name, e.g., ['ClassName', 'methodName'].
		 *
		 * @return void
		 */
		public
		function perform(string $uri, array $action): void
		{
			$class = $action[0] ?? '';
			$method = $action[1] ?? '';

			if ($class && $method && class_exists($class) && method_exists($class, $method)) {
				$this->perform[trim($uri, '/')] = [$class, $method];
			}
		}
	}