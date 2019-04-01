<?php
namespace LexicalBits\BackupRotator\Rotator;

use LexicalBits\BackupRotator\Storage\Storage;

/**
 * Class: Rotator - An abstract rotation strategy,
 *
 * @abstract
 */
abstract class Rotator
{
    static protected $engines = [];
    /**
     * Factory method for generating this rotator from a config array
     *
     * @param Storage $storage Storage system to be rotated
     * @param array $config Configuration for this rotator
     */
    abstract static public function fromConfig(Storage $storage, array $config);
    /**
     * Create an instance of a registered rotator subclass from a given config.
     *
     * @param Storage $storage Storage system to be rotated
     * @param array $cfg Configuration to use - must contain 'engine' and other keys required by the subclass
     */
    static public function factory(Storage $storage, array $cfg)
    {
        if (!isset($cfg['rotator'])) {
            throw new \Exception('No rotator engine configured');
        }
        $requestedClassName = $cfg['rotator'];

        if (!isset(self::$engines[$requestedClassName])) {
            throw new \Exception(sprintf('Unknown engine "{%s}"', $requestedClassName));
        }
        $className = self::$engines[$requestedClassName];
        return ($className)::fromConfig($storage, $cfg);
    }
    /**
     * Register a rotator engine class name.
     * This should be simply the class name for internal engines in this namespace,
     * or fully-qualified namespaces for external implementations being brought into the system.
     *
     * @param string $engine Name of the engine class to register
     */
    static public function registerEngine($engine)
    {
        // Assume unnamespaced names are part of this namespace (we do this so that future namespace changes
        // don't rely on string searches outside each file's header)
        if (strpos($engine, '\\') === false) {
            self::$engines[$engine] = implode('\\', [__NAMESPACE__, $engine]);
        } else {
            self::$engines[$engine] = $engine;
        }
    }
    /**
     * Execute the rotation strategy.
     *
     */
    abstract public function rotate();
}

Rotator::registerEngine('DMYRotator');
