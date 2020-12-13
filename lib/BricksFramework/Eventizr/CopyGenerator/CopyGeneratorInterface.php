<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\CopyGenerator;

use BricksFramework\Eventizr\Generator\ClassGenerator;

interface CopyGeneratorInterface
{
    public function getClassGenerator() : ClassGenerator;
    public function getClass() : string;
    public function modify() : void;
    public function modifyMethods() : void;
    public function addProxy() : void;
    public function generate() : string;
}
