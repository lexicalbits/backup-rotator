<?php
namespace LexicalBits\BackupRotator\Test\Unit\Storage;

use LexicalBits\BackupRotator\Test\TestCase;
use LexicalBits\BackupRotator\Storage\Storage;
use LexicalBits\BackupRotator\Storage\FileStorage;
use LexicalBits\BackupRotator\Storage\StorageException;

final class StorageTest extends TestCase
{
    public function testRegisterEngineRegistersLocal()
    {
        Storage::registerEngine('Foo');
        $this->assertArraySubset(
            ['Foo' => 'LexicalBits\\BackupRotator\\Storage\\Foo'],
            $this->getStaticInternalProperty(Storage::class, 'engines')
        );
    }
    public function testRegisterEngineRegistersExternal()
    {
        Storage::registerEngine('MyNamespace\\Foo');
        $this->assertArraySubset(
            ['MyNamespace\\Foo' => 'MyNamespace\\Foo'],
            $this->getStaticInternalProperty(Storage::class, 'engines')
        );
    }
    public function testFactoryFailsWithNoEngine()
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionCode(StorageException::GENERAL_INVALID_CONFIG);
        $this->expectExceptionMessage('No storage engine');
        Storage::factory([]);
    }
    public function testFactoryFailsWithInvalidEngine()
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionCode(StorageException::GENERAL_INVALID_CONFIG);
        $this->expectExceptionMessage('Unknown engine');
        Storage::factory(['engine'=>'foo']);
    }
    public function testFactoryGeneratesEngine()
    {
        $engine = Storage::factory(['engine'=>'FileStorage', 'path'=>'/dev/null']);
        $this->assertInstanceOf(FileStorage::class, $engine);
    }

}
