<?php

    namespace Core;

    use Handler\ExceptionHandler;
	use Handler\MiddlewaresHandler;
	use Handler\PageInitializer;
	use Handler\RouteHandler;
	use Helper\Initializer;
	use Helper\Mapping;
	use Helper\Reflections;
	use Helper\Router;
	use Helper\Skeleton;
	use Closure;
	use Error;
	use Exception;
	use ParseError;
	use ReflectionClass;
	use Throwable;
	
	class Application
    {
        use Mapping;
        use Reflections;
        use Skeleton;

        private string $dir;
        private string $config;
        private string $main = '';
        private string $notFound = '';
        private null|object $middleware = null;
        private null|object $exceptions = null;
        private null|RouteHandler $routes = null;
        private null|PageInitializer $init = null;

        public
        static function configure(string $init = ''): self
        {
            # Create new instance
            return new self($init);
        }

        public
        function __construct(string $config)
        {
            $backtrace = debug_backtrace();
            $this->dir = dirname($backtrace[1][ 'file' ]);
            $this->config = trim($config, '/');

            if (isset($_GET['__module__'])) {
				die(response()->html("<center><h1><b>".$this->searchComponent($_GET['__module__'])."</b></h1></center>"));
            }

            return $this;
        }

        public
        function withExceptions(Closure $callback): self
        {
            $callback($this->exceptions = new ExceptionHandler());
            return $this;
        }

        public
        function withMiddlewares(Closure $callback): self
        {
            $callback($this->middleware = new MiddlewaresHandler());
            return $this;
        }

        public
        function withRoutes(Closure $callback): self
        {
            $callback($this->routes = new RouteHandler());
            return $this;
        }

        public
        function withPageInit(Closure $callback): self
        {
            $callback($this->init = new PageInitializer());
            return $this;
        }

        public
        function throw(string $page = ''): self
        {
            $this->notFound = ltrim($page, '/') . '.php';
            return $this;
        }

        public
        function main(string $page): self
        {
            $this->main = ltrim($page, '/') . '.php';
            return $this;
        }
		
		private
		function searchComponent($token)
		{
			$components = Cache::get('APP_COMPONENTS') ?: [];
			$token = htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8');
			
			foreach ($components as $component => $registeredToken) {
				if (decryptString($token, strlen($component)) === $registeredToken) {
					return preg_replace('/^AC_/', '', $component);
				}
			}
			
			if (config('development')) {
				die(response(404)->json([
					'message' => !$token ? "Token is required." : "`$token` is undefined",
					'components' => $components
				]));
			}
			
			die(response(400)->json(['message' => "Bad Request"]));
		}

        private
        function validate(string $filepath)
        {
            if ($this->middleware) {
                $reflection = new ReflectionClass($this->middleware);
                $method = $reflection->getMethod('fetchMiddlewares');
                $method->setAccessible(true);

                foreach ($method->invoke($this->middleware) as $action) {
                    $path = $action['path'];
                    $action = $action['action'];

                    $class = null;
                    $method = null;
                    $args = null;

                    if (is_string($action)) {
                        $class = $action;
                        $method = 'handle';
                    } else {
                        $class = $action[0];
                        $method = $action[1];
                        $args = $action[2] ?? null;
                    }

                    if ($path == '*') {
                        $res = $this->performAction([$class, $method, $args]);
                    } else {
                        if ($filepath == $this->createFullPath($path)) {
                            $res = $this->performAction([$class, $method, $args]);
                        }
                    }

                    if (($res ?? true) === false) {
                        return (new Request)->response(401)->json(['message' => "Unauthorized"]);
                    }
                    if (http_response_code() !== 200) {
                        return $res;
                    }
                }
            }
            return true;
        }

        private
        function commence(string $path): string
        {
            $onStart = false;
            $onExit = false;
            if (!is_null($this->init)) {
                $initReflection = new ReflectionClass($this->init);
                $configs = $initReflection->getProperty('configs');
                $configs->setAccessible(true);

                foreach ($configs->getValue($this->init) as $config) {
                    $is_dir = is_dir($config['path']);
                    $passed = false;

                    if ($is_dir) {
                        $dir = str_replace(root .'/pages', '', $config['path']);
                        $semiPath = str_replace(root .'/pages', '', $path);

                        if ($this->isInDirectory($semiPath, $dir)) {
                            $passed = true;
                        }
                    } else {
                        if ($config['path'] == $path) {
                            $passed = true;
                        }
                    }

                    if ($passed) {
                        call_user_func($config['callback'], $setup = new Initializer());
                        $setupReflection = new ReflectionClass($setup);
                        $method = $setupReflection->getMethod('getSetup');
                        $method->setAccessible(true);
                        $conf = $method->invoke($setup);

                        if ($classHeader = $conf['header']) {
                            $onStart = new $classHeader();
                        }

                        if ($classHeader = $conf['footer']) {
                            $onExit = new $classHeader();
                        }
                    }
                }
            }

            ob_start();
            if ($onStart) {
                echo(call_user_func([new $onStart, 'onLoad']));
            }

            if ( ($res = $this->validate($path)) === true ) {
                $req = new Request();

                if ($token = $_SERVER['HTTP_X_APP_COMPONENT'] ?? '') {
					$component = $this->searchComponent($token);
					if ($req->has('partial_load')) {
						die(render($component, $req->except(['partial_load'])));
					} else {
						die((new $component)->ajax($req));
					}
                }

                ob_start();
                if (file_exists($path)) {
                    $returnValue = require_once $path;
                } else {
                    $returnValue = response(404)->json(['message' => "Not Found"]);
                }
                $output = ob_get_clean();
                echo $output ?: ($returnValue !== 1 ? $returnValue : '');

            } else {
                echo($res);
            }

            if ($onExit) {
                echo(call_user_func([new $onExit, 'onExit']));
            }

            return ob_get_clean();
        }

        public
        function render(): mixed
        {
            try {

                // Search in routes
                if (!is_null($this->routes)) {
                    $route = new Router($this->routes);

					// Load component directly
                    if ($className = $route->search('render')) {
                        return(render($className, request()->inputs()));
                    }

					// Load page directly
                    if ($routePath = $route->search('routes')) {
                        return($this->commence($this->createFullPath($routePath)));
                    }
                }
				
				// Set default home page
                if (($url = $this->getURI()) == '/') {
                    $url = $this->main;
                }

                // Search in files
                if (file_exists($path = $this->createFullPath($url))) {
                    return($this->commence($path));
                } else {
                    $throw = $this->createFullPath($this->notFound);
                    if (file_exists($throw)) {
                        return(require $throw);
                    } else {
                        return(response(404)->json(['message' => "Page not found!"]));
                    }
                }

            } catch (Error|Exception|Throwable|ParseError $e) {
                $request = response(500);
                if (is_null($this->exceptions)) {
                    die($request->html($this->displayError($e)));
                }

                die($request->html($this->exceptions->handleException($e, (new Request))));
            }
        }
    }