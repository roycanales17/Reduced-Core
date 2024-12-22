<?php

	namespace Core;

	use Helper\BaseFileUpload;

	class Storage
	{
		public static function disk(string $disk = 'storage'): BaseFileUpload
		{
			return new BaseFileUpload($disk);
		}
	}