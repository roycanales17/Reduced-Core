<?php
	
	namespace Core;
	
	use Exception;
	use Memcached;
	
	class Cache
	{
		private static ?object $instance = null;
		
		/**
		 * Get the singleton instance of Memcached.
		 *
		 * @return object|null
		 */
		public static function instance(): ?object
		{
			if (!self::$instance) {
				
				$active = config('MEMCACHE');
				$server = config('MEMCACHE_SERVER_NAME');
				$port = config('MEMCACHE_PORT');
				
				if (!$active || !$server || !$port) {
					return null;
				}
				
				$obj = new Memcached();
				if (!$obj->addServer($server, $port)) {
					throw new Exception('Failed to connect to Memcache server.');
				}
				
				self::$instance = $obj;
			}
			
			return self::$instance;
		}
		
		/**
		 * Retrieve a value from the cache or store it if it does not exist.
		 *
		 * This method checks if a key exists in Memcache. If the key exists, it retrieves
		 * the cached value. If not, it executes the provided callback to generate the value,
		 * stores it in the cache with the specified timeout, and then returns the value.
		 *
		 * @param string $key
		 * @param callable $callback
		 * @param int $expire
		 * @return mixed
		 */
		public static function remember(string $key, callable $callback, int $expiration = 60): mixed
		{
			$obj = self::instance();
			
			if ($obj) {
				
				$key = "remember:$key";
				$cachedValue = $obj->get($key);
				
				if ($cachedValue !== false) {
					return $cachedValue;
				}
				
				$format = [
					'data' => $value = $callback(),
					'expires_at' => time() + $expiration,
				];
				$obj->set($key, $format, $expiration);
				return $value;
			}
			
			// If Memcache is not available, just return the callback result
			return $callback();
		}
		
		/**
		 * Check if a key exists in Memcache.
		 *
		 * @param string $key
		 * @return bool
		 */
		public static function has(string $key): bool
		{
			$obj = self::instance();
			if ($obj) {
				return $obj->get($key) !== false;
			}
			
			return false;
		}
		
		/**
		 * Set a value in Memcache.
		 *
		 * @param string $key
		 * @param mixed $value
		 * @param int $expiration
		 * @return bool
		 */
		public static function set(string $key, mixed $value, int $expiration = 0): bool
		{
			$obj = self::instance();
			if ($obj) {
				$format = [
					'data' => $value,
					'expires_at' => time() + $expiration,
				];
				return $obj->set($key, $format, $expiration);
			}
			
			return false;
		}
		
		/**
		 * Get a value from Memcache.
		 *
		 * @param string $key
		 * @param mixed $default
		 * @return mixed
		 */
		public static function get(string $key, mixed $default = false): mixed
		{
			$obj = self::instance();
			if ($obj) {
				$data = $obj->get($key);
				if ($data !== false) {
					return $data['data'];
				}
			}
			return $default;
		}
		
		/**
		 * Delete a key from Memcache.
		 *
		 * @param string $key
		 * @return bool
		 */
		public static function delete(string $key): bool
		{
			$obj = self::instance();
			if ($obj) {
				return $obj->delete($key);
			}
			
			return false;
		}
		
		/**
		 * Clear all values from Memcache.
		 *
		 * @return void
		 */
		public static function clear(): void
		{
			self::instance()?->flush();
		}
		
		/**
		 * Fetch all keys and values from Memcache.
		 *
		 * @return array|bool
		 */
		public static function fetchAll(): array|bool
		{
			$obj = self::instance();
			if ($obj) {
				return $obj->getAllKeys();
			}
			
			return false;
		}
		
		/**
		 * Get the expiration time (TTL) for a specific key from Memcache.
		 *
		 * @param string $key
		 * @return int|null Returns the remaining TTL in seconds, or null if the key does not exist.
		 */
		public static function getExpiration(string $key): ?int
		{
			$obj = self::instance();
			if ($obj) {
				$data = $obj->get($key);
				if ($data && isset($data['expires_at'])) {
					$ttl = $data['expires_at'] - time();
					return max($ttl, 0);
				}
			}
			
			return false;
		}
	}
