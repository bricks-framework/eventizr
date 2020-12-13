<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr;

interface EventizrInterface
{
    public function getCompileDir() : string;
    public function eventize(string $class) : void;
    public function loadClassMap() : void;
}
