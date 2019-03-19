<?php
namespace LexicalBits\BackupRotator\Test\Unit\Rotator;

use LexicalBits\BackupRotator\Storage\Storage;

class MockStorage extends Storage
{
    public $config;
    public $chunks;
    public $existingModifiers;

    public function __construct()
    {
        $this->chunks = [];
    }

    static public function fromConfig(array $config)
    {
        $this->config = $config;
    }

    public function onChunk(string $chunk, int $idx)
    {
        $this->chunks[] = [$chunk, $idx];
    }

    public function onEnd()
    {
    }
    public function getExistingCopyModifiers()
    {
        return $this->existingModifiers;
    }
    public function copyWithModifier(string $modifier, bool $allowOverwrite=false)
    {
    }
    public function deleteWithModifier(string $modifier)
    {
    }
}

