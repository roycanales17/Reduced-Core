<?php

    namespace Scheme;

    use Core\Request;
	
	abstract class Middleware
    {
        public abstract function handle(Request $request);
    }