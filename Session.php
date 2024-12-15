<?php

    namespace app;
    use app\Handler\SessionHandler;

    class Session
    {
        public static function setup(): void
        {
            # Configure
            session_set_cookie_params([
                'lifetime' => config('SESSION_LIFETIME'),
                'path' => config('SESSION_PATH'),
                'domain' => config('SESSION_DOMAIN', ''),
                'secure' => (bool) config('SESSION_SECURE', false),
                'httponly' => (bool) config('SESSION_HTTPONLY', true),
            ]);

            ini_set('session.gc_maxlifetime', config('SESSION_LIFETIME'));
            ini_set('session.gc_probability', 1);
            ini_set('session.gc_divisor', 100);

            # Session Handler
            session_set_save_handler(new \App\Handler\SessionHandler(), true);

            # Register session close function
            register_shutdown_function([new \App\Handler\SessionHandler(), 'session_close']);

            # Start
            self::start();
        }

        public static function started(): bool
        {
            return !(session_status() === PHP_SESSION_NONE);
        }

        /**
         * Start the session if it hasn't already been started.
         */
        public static function start(): void
        {
            if (!self::started()) {
				@session_start();
            }
        }

        /**
         * Set a session value.
         *
         * @param string $key The key to associate with the value.
         * @param mixed $value The value to store.
         * @return void
         */
        public static function set(string $key, mixed $value): void
        {
            self::start();
            $_SESSION[$key] = $value;
        }

        /**
         * Get a session value by its key.
         *
         * @param string $key The key of the value to retrieve.
         * @param mixed $default The default value to return if the key doesn't exist.
         * @return mixed
         */
        public static function get(string $key, mixed $default = null): mixed
        {
            self::start();
            return $_SESSION[$key] ?? $default;
        }

        /**
         * Check if a session key exists.
         *
         * @param string $key The key to check.
         * @return bool
         */
        public static function has(string $key): bool
        {
            self::start();
            return isset($_SESSION[$key]);
        }

        /**
         * Remove a session value by its key.
         *
         * @param string $key The key of the value to remove.
         * @return void
         */
        public static function remove(string $key): void
        {
            self::start();
            unset($_SESSION[$key]);
        }

        /**
         * Flash a value for one-time use.
         * The value will be available until it is retrieved for the first time.
         *
         * @param string $key The key to associate with the flash value.
         * @param mixed|null $value The value to flash. If null, the method will retrieve and remove the value.
         * @return mixed|null
         */
        public static function flash(string $key, mixed $value = null): mixed
        {
            self::start();

            if ($value === null) {
                $flashValue = $_SESSION[$key] ?? null;
                self::remove($key);
                return $flashValue;
            }

            $_SESSION[$key] = $value;
            return null;
        }

        /**
         * Destroy the entire session.
         *
         * @return void
         */
        public static function destroy(): void
        {
            if (session_status() !== PHP_SESSION_NONE) {
                session_destroy();
                $_SESSION = [];
            }
        }

        /**
         * Regenerate the session ID for security purposes.
         *
         * @param bool $deleteOldSession Whether to delete the old session data.
         * @return void
         */
        public static function regenerate(bool $deleteOldSession = true): void
        {
            self::start();
            session_regenerate_id($deleteOldSession);
        }
    }
