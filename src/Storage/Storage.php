<?php
namespace LexicalBits\BackupRotator\Storage;

/**
 * Class: Storage - An abstract storage class that defines a common streaming interface.
 *
 * @abstract
 */
abstract class Storage
{
    /**
     * The recommended chunk size to request for this storage system.
     *
     * Should be overriden if necessary by each child class.
     */
    const CHUNK_SIZE_KB = 1024;
    static protected $engines = [];
    /**
     * Create an instance of this class from a configuration array
     *
     * @param array $config Configuration to use
     */
    abstract static public function fromConfig(array $config);
    /**
     * Register a storage engine class name.
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
     * Create an instance of a registered storage subclass from a given config.
     *
     * @param array $cfg Configuration to use - must contain 'engine' and other keys required by the subclass
     */
    static public function factory(array $cfg)
    {
        if (!isset($cfg['engine'])) {
            throw new StorageException('No storage engine configured', StorageException::GENERAL_INVALID_CONFIG);
        }
        $requestedClassName = $cfg['engine'];

        if (!isset(self::$engines[$requestedClassName])) {
            throw new StorageException(
                sprintf('Unknown engine "{%s}"', $requestedClassName),
                StorageException::GENERAL_INVALID_CONFIG
            );
        }
        $className = self::$engines[$requestedClassName];
        return ($className)::fromConfig($cfg);
    }

    /**
     * What to do when a chunk comes in.
     *
     * @param string $chunk Chunk of data
     * @param int $idx Which chunk this is
     */
    abstract public function onChunk(string $chunk, int $idx);
    /**
     * What to do when we're out of chunks
     *
     */
    abstract public function onEnd();
}

Storage::registerEngine('S3Storage');
Storage::registerEngine('FileStorage');
