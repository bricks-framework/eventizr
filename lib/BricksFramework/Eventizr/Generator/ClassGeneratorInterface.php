<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\Generator;

use Laminas\Code\Generator\MethodGenerator;

interface ClassGeneratorInterface
{
    public function modifyMethods(string $class, array $methods, bool $isCopy = false) : void;
    public function addProxy() : void;
    public function modifyProxyConstructor(string $class, MethodGenerator $method) : void;
    public function addProxyMethods() : void;
    public function buildMethodBody(string $class, MethodGenerator $method, bool $isCopy = false) : void;
}
