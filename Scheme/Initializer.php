<?php

    namespace Scheme;

    Interface Initializer {

        public function onLoad(): void;

        public function onExit(): void;
    }