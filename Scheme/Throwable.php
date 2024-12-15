<?php

    namespace Scheme;

    use Helper\Mapping;
    use Helper\Skeleton;
    use Error;

    abstract class Throwable extends Error
    {
        use Mapping;
        use Skeleton;

        protected
        string $preferredIDE = 'phpstorm';
    }