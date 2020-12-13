<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/

namespace BricksFramework\Eventizr;

use BricksFramework\Eventizr\CopyGenerator\CopyGenerator;
use BricksFramework\Eventizr\Generator\ClassGenerator;
use BricksFramework\Eventizr\ProxyGenerator\ProxyGenerator;
use Composer\Autoload\ClassLoader;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Config\Config;
use Laminas\Config\Writer\PhpArray;
use Laminas\Filter\Word\SeparatorToSeparator;

class Eventizr implements EventizrInterface
{
    protected const TEMP_FILE_NAMESPACE_PREFIX = 'BricksTempCompile\\';

    /** @var ClassLoader */
    protected $autoloader;

    /** @var string */
    protected $compileDir;

    /** @var string */
    protected $tempDir;

    protected $classes = [];

    protected $md5sums = [];

    public function __construct(ClassLoader $autoloader, string $compileDir, string $tempDir)
    {
        $this->autoloader = $autoloader;
        $this->compileDir = $compileDir . DIRECTORY_SEPARATOR . 'BricksCompile' . DIRECTORY_SEPARATOR . 'Eventizr';
        $this->tempDir = $tempDir . DIRECTORY_SEPARATOR . 'eventizr-temp-compile';
    }

    public function getCompileDir() : string
    {
        return $this->compileDir;
    }

    public function eventize(string $class) : void
    {
        if ($this->isEventized($class) && !$this->needUpdate($class)) {
            return;
        }

        $this->copyTempFile($class);

        $tempFilePath = $this->getTempFilePath($class);
        $this->modifyTempFileNamespace($class);
        $className = substr($class, strrpos($class, '\\')+1);
        $this->setAutoloaderFile($this->getTempFileNamespace($class) . '\\' . $className, $tempFilePath);

        $this->createFromTempFile($tempFilePath, $class);
        $originalFile = $this->autoloader->findFile($class);
        $this->addMd5Sum($originalFile, md5_file($originalFile));
    }

    public function loadClassMap() : void
    {
        $classmapFile = $this->compileDir . DIRECTORY_SEPARATOR . 'classmap.php';
        if (!file_exists($classmapFile)) {
            return;
        }

        $classmap = require $classmapFile;
        foreach ($classmap as $key => $filepath) {
            if (!file_exists($filepath)) {
                unset($classmap[$key]);
            }
        }

        $this->autoloader->addClassMap($classmap);
    }

    protected function isEventized(string $class) : bool
    {
        $filter = new SeparatorToSeparator('\\', DIRECTORY_SEPARATOR);
        $filepath = $this->compileDir . DIRECTORY_SEPARATOR . $filter->filter($class) . '.php';
        return file_exists($filepath);
    }

    protected function needUpdate(string $class) : bool
    {
        $filepath = $this->autoloader->findFile($class);
        $filemd5 = md5_file($filepath);
        $this->getMd5Sums();
        return $this->md5sums[$filepath] ?? '' != $filemd5;
    }

    protected function getMd5Sums() : array
    {
        if (!$this->md5sums) {
            $md5SumFile = $this->getMd5SumFile();
            if (file_exists($md5SumFile)) {
                $this->md5sums = require $md5SumFile;
            }
        }
        return $this->md5sums;
    }

    protected function addMd5Sum(string $filepath, $md5sum) : void
    {
        $this->getMd5Sums();
        $this->md5sums[$filepath] = $md5sum;
        $md5file = $this->getMd5SumFile();
        $config = new Config($this->md5sums);
        $writer = new PhpArray();
        $writer->toFile($md5file, $config);
    }

    protected function getMd5SumFile() : string
    {
        return $this->compileDir . DIRECTORY_SEPARATOR . 'md5sums.php';
    }

    protected function getTempFileNamespace(string $class) : string
    {
        $namespacePart = substr($class, 0, strrpos($class, '\\'));
        return self::TEMP_FILE_NAMESPACE_PREFIX . $namespacePart;
    }

    protected function copyTempFile(string $class) : void
    {
        $sourceFilepath = $this->autoloader->findFile($class);
        $targetFilepath = $this->getTempFilePath($class);
        if (!is_dir(dirname($targetFilepath))) {
            mkdir(dirname($targetFilepath), 0777, true);
        }
        copy($sourceFilepath, $targetFilepath);
    }

    protected function getNamespace(string $class) : string
    {
        return substr($class, 0, strrpos($class, '\\'));
    }

    protected function getTempFilePath(string $class) : string
    {
        $filter = new SeparatorToSeparator('\\', DIRECTORY_SEPARATOR);
        return $this->tempDir . DIRECTORY_SEPARATOR . $filter->filter($class) . '.php';
    }

    protected function getTempFileClass(string $class) : string
    {
        return $this->getTempFileNamespace($class) . '\\' . substr($class, strrpos($class, '\\')+1);
    }

    protected function modifyTempFileNamespace(string $class) : void
    {
        $tempFilePath = $this->getTempFilePath($class);
        $namespace = $this->getTempFileNamespace($class);
        $content = file_get_contents($tempFilePath);
        $useStatements = $this->fetchUseStatements($content);

        $useStatement = '';
        foreach ($useStatements as $className) {
            $useStatement = 'use ' . $this->getNamespace($class) . '\\' . $className . ';' . "\n";
        }

        $content = preg_replace('#namespace .*?;#', 'namespace ' . $namespace . ';
' . $useStatement, $content);
        file_put_contents($tempFilePath, $content);
    }

    protected function fetchUseStatements(string $content) : array
    {
        $usedStatements = [];
        if (preg_match_all('#use .*? as (.*?);#ims', $content, $matches)) {
            foreach ($matches[1] as $className) {
                $usedStatements[] = trim($className);
            }
        }
        if (preg_match_all('#use ([\w|\\\\]+);#ims', $content, $matches)) {
            foreach ($matches[1] as $className) {
                $usedStatements[] = trim(substr($className, strrpos($className, '\\')+1));
            }
        }

        $useStatements = [];
        if (preg_match_all('#extends (\w+)+#ims', $content, $matches)) {
            foreach ($matches[1] as $className) {
                if (false === strpos($className, '\\') && !in_array($className, $usedStatements)) {
                    $useStatements[] = $className;
                }
            }
        }
        if (preg_match_all('#implements (\w+)+#ims', $content, $matches)) {
            foreach ($matches[1] as $className) {
                if (false === strpos($className, '\\') && !in_array($className, $usedStatements)) {
                    $useStatements[] = $className;
                }
            }
        }
        if (preg_match_all('#\) ?: ?(\w+)+#ims', $content, $matches)) {
            foreach ($matches[1] as $className) {
                if (in_array($className, [
                    'object', 'void', 'int', 'integer', 'double', 'float', 'string', 'bool', 'boolean'
                ])) {
                    continue;
                }
                if (false === strpos($className, '\\') && !in_array($className, $usedStatements)) {
                    $useStatements[] = $className;
                }
            }
        }
        return $useStatements;
    }

    protected function setAutoloaderFile(string $class, string $path) : void
    {
        $this->autoloader->addClassMap([$class => $path]);
    }

    protected function updateClassMap(string $class, string $filepath) : void
    {
        $classmap = [];
        $classmapFile = $this->compileDir . DIRECTORY_SEPARATOR . 'classmap.php';
        if (file_exists($classmapFile)) {
            $classmap = require $this->compileDir . DIRECTORY_SEPARATOR . 'classmap.php';
        }
        $classmap = array_merge($classmap, [
            $class => $filepath
        ]);

        $config = new Config($classmap);
        $writer = new PhpArray();
        $writer->toFile($this->compileDir . DIRECTORY_SEPARATOR . 'classmap.php', $config);
    }

    protected function hasClassFile(string $class) : bool
    {
        $proxyFile = $this->getFilePath($class);
        return file_exists($proxyFile);
    }

    protected function getFilePath(string $class) : string
    {
        $filter = new SeparatorToSeparator('\\', DIRECTORY_SEPARATOR);
        return $this->compileDir . DIRECTORY_SEPARATOR . $filter->filter($class) . '.php';
    }

    /**
     * @throws \ReflectionException
     */
    protected function createFromTempFile(string $tempFilePath, string $class) : void
    {
        $tempFileClass = $this->getTempFileClass($class);
        $implements = class_implements($tempFileClass, true);

        if (
            in_array('BricksFramework\\Eventizr\\EventizrCopyInterface', $implements) ||
            in_array('BricksFramework\\Eventizr\\EventizrProxyInterface', $implements)
        ) {
            return;
        }

        $generator = $this->getGenerator($class);
        $generator->modify();
        $classContent = $generator->generate();

        $filepath = $this->getFilePath($class);
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0775, true);
        }
        file_put_contents($filepath, '<?php

/** @copyright Sven Ullmann <kontakt@sumedia-webdesign.de> **/' . "\n" . $classContent);

        if ($generator instanceof CopyGenerator) {
            $this->updateClassMap($class, $filepath);
            $this->setAutoloaderFile($generator->getClass(), $filepath);
        }

        if ($generator instanceof ProxyGenerator) {
            $this->updateClassMap('BricksCompile\\Eventizr\\' . $class, $this->getFilePath($class));
            $this->setAutoloaderFile('BricksCompile\\Eventizr\\' . $generator->getClass(), $filepath);
        }
    }

    /**
     * @return CopyGenerator|ProxyGenerator
     * @throws \ReflectionException
     */
    protected function getGenerator(string $class)
    {
        $tempFileClass = $this->getTempFileClass($class);
        $classGenerator = ClassGenerator::fromReflection(new \Laminas\Code\Reflection\ClassReflection($tempFileClass));

        if ($classGenerator->isFinal()) {
            return new CopyGenerator($class, $classGenerator);
        }

        $properties = $classGenerator->getProperties();
        foreach ($properties as $property) {
            if ($property->isFinal() || $property->getVisibility() == PropertyGenerator::VISIBILITY_PRIVATE) {
                return new CopyGenerator($class, $classGenerator);
            }
        }

        $methods = $classGenerator->getMethods();
        foreach ($methods as $method) {
            if ($method->isFinal() || $method->getVisibility() == MethodGenerator::VISIBILITY_PRIVATE) {
                return new CopyGenerator($class, $classGenerator);
            }
        }

        return new ProxyGenerator($class, $classGenerator);
    }
}
