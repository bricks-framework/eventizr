<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\CopyGenerator;

use Laminas\Code\Generator\MethodGenerator;
use BricksFramework\Eventizr\Generator\ClassGenerator;

class CopyGenerator implements CopyGeneratorInterface
{
    /** @var string */
    protected $class;

    /** @var ClassGenerator */
    protected $classGenerator;

    public function __construct(string $class, ClassGenerator $classGenerator)
    {
        $this->class = $class;
        $this->classGenerator = $classGenerator;
    }

    public function getClassGenerator() : ClassGenerator
    {
        return $this->classGenerator;
    }

    public function getClass() : string
    {
        return $this->class;
    }

    public function modify() : void
    {
        $this->modifyNamespace();
        $this->modifyMethods();
        $this->addProxy();
    }

    public function modifyNamespace() : void
    {
        $this->getClassGenerator()
            ->setNamespaceName(substr($this->class, 0, strrpos($this->class, '\\')));
    }

    public function modifyMethods() : void
    {
        $classGenerator = $this->getClassGenerator();
        $methods = [];
        foreach ($classGenerator->getMethods() as $method) {
            if ($method->getVisibility() != MethodGenerator::VISIBILITY_PUBLIC) {
                $classGenerator->removeMethod($method->getName());
            } else {
                $methods[] = $method;
            }
        }

        $classGenerator->modifyMethods($this->class, $methods);
    }

    public function addProxy() : void
    {
        $this->getClassGenerator()
            ->addProxy();
    }

    public function generate() : string
    {
        return $this->getClassGenerator()->generate();
    }
}
