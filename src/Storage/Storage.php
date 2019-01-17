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
