<?php
namespace LexicalBits\BackupRotator\Test;

class TestCase extends \PHPUnit\Framework\TestCase {
    protected function getReflectionClass($className)
    {
        return new \ReflectionClass($className);
    }
    /**
     * Generate a useable reflection method for private/protected methods
     * c/o https://www.webtipblog.com/unit-testing-private-methods-and-properties-with-phpunit/
     *
     * @author	Joe Sexton <joe@webtipblog.com>
     * @param 	string $className
     * @param 	string $methodName
     * @return	ReflectionMethod
     */
    protected function getInternalMethod(string $className, string $methodName)
    {
        $method = $this->getReflectionClass($className)->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Generage a useable reflection property for private/protected properties
     * c/o https://www.webtipblog.com/unit-testing-private-methods-and-properties-with-phpunit/
     *
     * @author	Joe Sexton <joe@webtipblog.com>
     * @param 	string $className
     * @param 	string $propertyName
     * @return	ReflectionProperty
     */
    protected function getInternalProperty(string $className, string $propertyName)
    {
        $property = $this->getReflectionClass($className)->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }

    protected function setInternalProperty(string $className, string $propertyName, object $instance, $value)
    {

        $property = $this->getInternalProperty($className, $propertyName);
        $property->setValue($instance, $value);
        return $property;
    }

    /**
     * Generage a useable reflection property for private/protected properties
     * c/o https://www.webtipblog.com/unit-testing-private-methods-and-properties-with-phpunit/
     *
     * @author	Joe Sexton <joe@webtipblog.com>
     * @param 	string $className
     * @param 	string $propertyName
     * @return	mixed Whatever the actual value of the property is - NOT a reflection property
     */
    protected function getStaticInternalProperty($className, $propertyName)
    {
        $statics = $this->getReflectionClass($className)->getStaticProperties();
        return $statics[$propertyName];
    }
}
