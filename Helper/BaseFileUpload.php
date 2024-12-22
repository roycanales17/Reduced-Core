<?php

	namespace Helper;

	use Exception;
	use Core\Logger;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;

	class BaseFileUpload
	{
		protected mixed $disk;
		protected array $registeredDisk = ['local', 'storage'];

		/**
		 * Initialize the FileUpload instance with a specified disk.
		 * If the disk doesn't exist, it creates it.
		 *
		 * @param string $disk Disk name ('local' or 'storage').
		 * @throws Exception If the disk is unsupported.
		 */
		function __construct(string $disk = 'local')
		{
			if (in_array($disk, $this->registeredDisk)) {
				$disk = 'storage';

				if (!is_dir($root = root . '/' . $disk)) {
					mkdir($root, 0755, true);
				}

				$this->disk = $root;
			} else {
				throw new Exception("Disk [$disk] is not supported for the meantime.");
			}

			return $this;
		}

		/**
		 * Create a file with the given filename and content.
		 *
		 * @param string $filename The name of the file to create.
		 * @param array|string $fileOrContent File content or file data.
		 * @return bool True if the file is created successfully, false otherwise.
		 * @throws Exception If multiple files are provided for this method.
		 */
		public function create(string $filename, array|string $fileOrContent): bool
		{
			if (!is_object($this->disk)) {

				self::makeDirectory(dirname($filename));

				if ($fileOrContent && is_string($fileOrContent)) {
					if (file_put_contents($filename, $fileOrContent) !== false) {
						return true;
					}
				}

				if ($fileOrContent && is_array($fileOrContent)) {
					if (array_keys($fileOrContent) !== range(0, count($fileOrContent) - 1)) {
						$fileOrContent['name'] = $filename;
						return self::upload([$fileOrContent]);
					} else {
						throw new Exception("We only upload single file for this function.");
					}
				}
			}

			Logger::path('warning.log')->warning("Unable to upload file [$filename].");
			return false;
		}

		/**
		 * Upload multiple files.
		 *
		 * @param array $files An array of files to upload.
		 * @return bool True if at least one file is uploaded successfully, false otherwise.
		 */
		public function upload(array $files): bool
		{
			if (!is_object($this->disk)) {
				$noFileUploaded = false;

				foreach ($files as $file) {
					if (!isset($file['tmp_name'], $file['name'], $file['error']) || $file['error'] !== 0) {
						Logger::path('warning.log')->warning("Invalid file [$file[name]] uploaded.");
						continue;
					}

					$tmpName = $file['tmp_name'];
					$fileName = $file['name'];
					$destination = $this->disk . '/' . $fileName;

					if (!move_uploaded_file($tmpName, $destination)) {
						return false;
					}

					$noFileUploaded = true;
				}

				return $noFileUploaded;
			}

			Logger::path('warning.log')->warning("Unable to upload file/s [" . json_encode($files) . "].");
			return false;
		}

		/**
		 * Write a file or upload files into a directory.
		 *
		 * @param string $directory Path to the directory.
		 * @param string|array $contentOrFiles File content or file data.
		 * @return bool True on success, false otherwise.
		 */
		public function put(string $directory, string|array $contentOrFiles): bool
		{
			if (!is_object($this->disk)) {
				self::makeDirectory($directory = '/' . trim($directory, '/') . '/');

				if (is_array($contentOrFiles)) {
					$fileName = $contentOrFiles['name'] ?? '';

					if ($fileName) {
						$contentOrFiles['name'] = $directory . $fileName;
						return self::upload([$contentOrFiles]);
					} else {
						foreach ($contentOrFiles as $index => $file) {
							$fileName = $file['name'] ?? '';

							if ($fileName) {
								$contentOrFiles[$index]['name'] = $directory . $fileName;
							}
						}

						return self::upload($contentOrFiles);
					}
				}

				if ($contentOrFiles && file_put_contents($directory, $contentOrFiles) !== false) {
					return true;
				}
			}

			Logger::path('warning.log')->warning("Unable to write file [$directory].");
			return false;
		}

		/**
		 * Deletes a file at the specified path.
		 *
		 * @param string $path The relative path to the file to be deleted.
		 * @return bool True if the file was deleted successfully, false otherwise.
		 */
		public function delete(string $path): bool
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					return unlink($this->disk . '/' . ltrim($path, '/'));
				}
			}

			Logger::path('warning.log')->warning("Unable to delete file [$path].");
			return false;
		}

		/**
		 * Retrieves the content of a file.
		 *
		 * @param string $path The path to the file.
		 * @return mixed The file content if it exists, otherwise `false`.
		 */
		public function getContent(string $path): mixed
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					$fullPath = $this->disk . '/' . ltrim($path, '/');
					return file_get_contents($fullPath);
				} else {
					Logger::path('warning.log')->warning("File does not exist [$path].");
				}
			}

			return false;
		}

		/**
		 * Loads the content of a file and sets the appropriate headers.
		 *
		 * @param string $path The path to the file.
		 * @return mixed The file content if it exists and loads successfully, otherwise `false`.
		 */
		public function load(string $path): mixed
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					$fullPath = $this->disk . '/' . ltrim($path, '/');
					$mimeType = self::mimeType($path);
					header('Content-Type: ' . $mimeType);
					return file_get_contents($fullPath);
				} else {
					Logger::path('warning.log')->warning("File does not exist [$path].");
				}
			}

			return false;
		}

		/**
		 * Checks if a file or directory exists at the specified path.
		 *
		 * @param string $path The relative path to check for existence.
		 * @return bool True if the file or directory exists, false otherwise.
		 */
		public function exists(string $path): bool
		{
			if (!is_object($this->disk)) {
				return file_exists($this->disk . '/' . ltrim($path, '/'));
			}

			return false;
		}

		/**
		 * Downloads a file at the specified path.
		 *
		 * @param string $path The relative path to the file to be downloaded.
		 * @return bool True if the file was downloaded successfully, false otherwise.
		 */
		public function download(string $path): bool
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					$path = $this->disk . '/' . ltrim($path, '/');
					header('Content-Type: ' . mime_content_type($path));
					header('Content-Disposition: attachment; filename="' . basename($path) . '"');
					header('Content-Length: ' . filesize($path));
					readfile($path);
					return true;
				}
			}

			Logger::path('warning.log')->warning("Unable to download file [$path].");
			return false;
		}

		/**
		 * Gets the size of a file or directory.
		 *
		 * @param string $filePathOrDirectory The relative path to the file or directory.
		 * @return int The size in bytes, or 0 if the file or directory does not exist.
		 */
		public function size(string $filePathOrDirectory): int
		{
			if (!is_object($this->disk)) {
				if (self::exists($filePathOrDirectory)) {
					$filePathOrDirectory = $this->disk . '/' . ltrim($filePathOrDirectory, '/');

					if (is_file($filePathOrDirectory)) {
						return filesize($filePathOrDirectory);
					} elseif (is_dir($filePathOrDirectory)) {
						$size = 0;
						foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePathOrDirectory)) as $file) {
							$size += $file->getSize();
						}
						return $size;
					}
				}
			}

			Logger::path('warning.log')->warning("Unable to get file size [$filePathOrDirectory].");
			return 0;
		}

		/**
		 * Gets the last modified timestamp of a file.
		 *
		 * @param string $path The relative path to the file.
		 * @return string|null The last modified timestamp in "Y-m-d H:i:s" format, or null on failure.
		 */
		public function lastModified(string $path):? string
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					return date("Y-m-d H:i:s", filemtime($this->disk . '/' . ltrim($path, '/')));
				}
			}

			Logger::path('warning.log')->warning("Unable to get the last modified [$path].");
			return null;
		}

		/**
		 * Gets the MIME type of file.
		 *
		 * @param string $path The relative path to the file.
		 * @return string|null The MIME type of the file, or null on failure.
		 */
		public function mimeType(string $path):? string
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					return mime_content_type($this->disk . '/' . ltrim($path, '/'));
				}
			}

			Logger::path('warning.log')->warning("Unable to get the mime type [$path].");
			return null;
		}

		/**
		 * Retrieves all files in a directory and its subdirectories.
		 *
		 * @param string $directory The relative path to the directory.
		 * @return array An array of file paths.
		 */
		public function allFiles(string $directory): array
		{
			$files = [];
			if (!is_object($this->disk)) {
				$directory = $this->disk . '/' . ltrim($directory, '/');

				if (is_dir($directory)) {
					$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
					foreach ($iterator as $file) {
						if ($file->isFile()) {
							$files[] = $file->getPathname();
						}
					}
				}
			}

			return $files;
		}

		/**
		 * Retrieves all directories in a root directory and its subdirectories.
		 *
		 * @param string $root The relative path to the root directory.
		 * @return array An array of directory paths.
		 */
		public function allDirectories(string $root): array
		{
			$directories = [];
			if (!is_object($this->disk)) {
				$root = $this->disk . '/' . ltrim($root, '/');
				if (is_dir($root)) {
					$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
					foreach ($iterator as $fileInfo) {
						if ($fileInfo->isDir() && !$fileInfo->isDot()) {
							$directories[] = $fileInfo->getPathname();
						}
					}
				}
			}

			return $directories;
		}

		/**
		 * Creates a directory at the specified path.
		 *
		 * @param string $path The relative path to the directory.
		 * @return bool True if the directory was created successfully, false otherwise.
		 */
		public function makeDirectory(string $path): bool
		{
			if (!is_object($this->disk)) {
				$path = $this->disk . '/' . ltrim($path, '/');
				if (!is_dir($path)) {
					mkdir($path, 0755, true);
					return true;
				}
			}

			Logger::path('warning.log')->warning("Unable to make directory [$path].");
			return false;
		}

		/**
		 * Deletes a directory at the specified path.
		 *
		 * @param string $path The relative path to the directory.
		 * @return bool True if the directory was deleted successfully, false otherwise.
		 */
		public function deleteDirectory(string $path): bool
		{
			if (!is_object($this->disk)) {
				if (self::exists($path)) {
					$path = $this->disk . '/' . ltrim($path, '/');
					if (is_dir($path)) {
						unlink($path);
						return true;
					}
				}
			}

			Logger::path('warning.log')->warning("Unable to delete directory [$path].");
			return false;
		}
	}
