<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr\Generator;

use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ClassGenerator as LaminasClassGenerator;
use Laminas\Code\Generator\ParameterGenerator;

class ClassGenerator extends LaminasClassGenerator implements ClassGeneratorInterface
{
    public function modifyMethods(string $class, array $methods, $isCopy = false) : void
    {
        /** @var MethodGenerator $method */
        foreach ($methods as $method) {
            if ($method->getName() != '__construct') {
                $this->buildMethodBody($class, $method, $isCopy);
            } else {
                $this->modifyProxyConstructor($class, $method);
            }
        }
    }

    public function addProxy($isCopy = false) : void
    {
        $this->addProxyInterface($isCopy
            ? 'BricksFramework\\Eventizr\\EventizrCopyInterface'
            : 'BricksFramework\\Eventizr\\EventizrProxyInterface'
        );
        $this->addProxyMethods();
    }

    public function addProxyInterface(string $interface) : void
    {
        $interfaces = $this->getImplementedInterfaces();
        $copyInterface = 'BricksFramework\\Eventizr\\EventizrCopyInterface';
        $proxyInterface = 'BricksFramework\\Eventizr\\EventizrProxyInterface';
        if (!in_array($copyInterface, $interfaces) && !in_array($proxyInterface, $interfaces)) {
            $interfaces[] = $interface;
            $this->setImplementedInterfaces($interfaces);
        }
    }

    public function modifyProxyConstructor(string $class, MethodGenerator $method) : void
    {
        $newParameters[] = new ParameterGenerator('____eventManager', '\BricksFramework\Event\EventManager\EventManagerInterface');
        foreach($method->getParameters() as $parameter) {
            $newParameters[] = $parameter;
        }
        $method->setParameters($newParameters);
        $this->buildMethodBody($class, $method);
        $method->setBody('$this->____eventManager = $____eventManager;        
' . $method->getBody());
    }

    public function addProxyMethods() : void
    {
        $this->addMethod(
            '____dispatch',
            [new ParameterGenerator('event', '\BricksFramework\Event\EventInterface')],
            MethodGenerator::FLAG_PRIVATE, '
            
if ($this->____eventManager) {
    $this->____eventManager->dispatch($event);    
}
            ',
            null
        );
    }

    public function buildMethodBody(string $class, MethodGenerator $method, bool $isCopy = false) : void
    {
        $hasReturnType = 'void' !== (string) $method->getReturnType() &&
            ('' === (string) $method->getReturnType() || !empty((string) $method->getReturnType()));

        $newBody = '$____return = null;' . self::LINE_FEED;
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === '____eventManager') {
                continue;
            }
            $newBody .= '$____parameters["' . $parameter->getName() . '"] = $' . $parameter->getName() .';' . self::LINE_FEED;
        }

        $newBody .= '
$____event = new \BricksFramework\Event\Event($this, "' . $class . '::' . $method->getName() . '.pre", $____parameters, $____return);         
$this->____dispatch($____event);
if ($____event->isPropagationStopped()) {
    ' . ($hasReturnType ? 'return $____event->getReturn();' : 'return;') . '
}
';
        if ($isCopy) {
            $newBody .= preg_replace('#\Wreturn\W#', '$____return = ', $method->getBody());
        } else {
            $newBody .= ((string) $method->getReturnType() !== 'void' || '' !== (string) $method->getReturnType() ? '$____return = ' : '') . 'parent::' .
                $method->getName() . '(';
            foreach ($method->getParameters() as $parameter) {
                $newBody .= '$' . $parameter->getName() . ', ';
            }
            $newBody = rtrim($newBody, ', ') . ');';
        }

        $newBody .= '
$____event = new \BricksFramework\Event\Event($this, "' . $class . '::' . $method->getName() . '.post", $____parameters, $____return);
$this->____dispatch($____event);
return ' . ($hasReturnType ? '$____return' : '') . ';
';

        $method->setBody($newBody);
    }
}
