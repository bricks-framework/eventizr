<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\ProxyGenerator;

use BricksFramework\Eventizr\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;

class ProxyGenerator implements ProxyGeneratorInterface
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
        $this->modifyFinal();
        $this->modifyExtendClass();
        $this->modifyTraits();
        $this->modifyContstants();
        $this->modifyProperties();
        $this->modifyMethods();
        $this->addProxy();
    }

    public function modifyNamespace() : void
    {
        $this->getClassGenerator()
            ->setNamespaceName('BricksCompile\\Eventizr\\' . substr($this->class, 0, strrpos($this->class, '\\')));
    }

    public function modifyFinal() : void
    {
        $this->getClassGenerator()
            ->setFinal(false);
    }

    public function modifyExtendClass() : void
    {
        $this->getClassGenerator()
            ->setExtendedClass($this->getClass());
    }

    public function modifyTraits() : void
    {
        $classGenerator = $this->getClassGenerator();
        foreach ($classGenerator->getTraits() as $trait) {
            $classGenerator->removeTrait($trait);
        }
        foreach ($classGenerator->getTraitAliases() as $alias) {
            $classGenerator->removeUseAlias($alias);
        }
    }

    public function modifyContstants() : void
    {
        $classGenerator = $this->getClassGenerator();
        foreach ($classGenerator->getConstants() as $constant) {
            $classGenerator->removeConstant($constant->getName());
        }
    }

    public function modifyProperties() : void
    {
        $classGenerator = $this->getClassGenerator();
        foreach ($classGenerator->getProperties() as $property) {
            $classGenerator->removeProperty($property->getName());
        }
    }

    public function modifyMethods() : void
    {
        $classGenerator = $this->getClassGenerator();
        $methods = [];
        foreach ($classGenerator->getMethods() as $method) {
            if (in_array($method->getVisibility(),[
                MethodGenerator::VISIBILITY_PUBLIC,
                MethodGenerator::VISIBILITY_PROTECTED
            ])) {
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
