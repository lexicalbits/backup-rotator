<?php
namespace LexicalBits\BackupRotator\Rotator;

/**
 * Class: Rotator - An abstract rotation strategy,
 *
 * @abstract
 */
abstract class Rotator
{
    /**
     * Factory method for generating this rotator from a config array
     *
     * @param array $config Configuration for this rotator
     */
    abstract static public function fromConfig(array $config);
    /**
     * Execute the rotation strategy.
     *
     */
    abstract public function rotate();
}
