<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\ProxyGenerator;

use BricksFramework\Eventizr\Generator\ClassGenerator;

interface ProxyGeneratorInterface
{
    public function getClassGenerator() : ClassGenerator;
    public function getClass() : string;
    public function modify() : void;
    public function modifyMethods() : void;
    public function addProxy() : void;
    public function generate() : string;
}
