<?php
namespace LexicalBits\BackupRotator\Test\Unit\Storage;

use LexicalBits\BackupRotator\Test\TestCase;
use LexicalBits\BackupRotator\Storage\FileStorage;
use LexicalBits\BackupRotator\Storage\StorageException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

final class FileStorageTest extends TestCase {
    protected $vfs;
    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('test'));
        // $this->vfs = vfsStream::setup('test');
    }
    protected function getResourceType(FileStorage $storage)
    {
        return get_resource_type($this->getInternalProperty(FileStorage::class, 'file')->getValue($storage));
    }
    public function testFromConfigThrowsWithoutNecessaryKeys()
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionCode(StorageException::GENERAL_INVALID_CONFIG);
        $this->expectExceptionMessage('required key');
        FileStorage::fromConfig([]);
    }
    public function testFromConfigCreatesInstance()
    {
        $this->assertInstanceOf(FileStorage::class, FileStorage::fromConfig([
            'path' => vfsStream::url('test/test.zip')
        ]));
    }
    public function testOnChunkWritesChunkToFile()
    {
        $path = vfsStream::url('test/test.zip');
        $storage = new FileStorage($path);
        $storage->onChunk('abc123', 1);
        $this->assertEquals(file_get_contents($path), 'abc123');
    }
    public function testOnEndClosesFile()
    {
        $path = vfsStream::url('test/test.zip');
        $storage = new FileStorage($path);
        $storage->onChunk('abc123', 1);
        $this->assertEquals('stream', $this->getResourceType($storage));
        $storage->onEnd();
        $this->assertEquals('Unknown', $this->getResourceType($storage));
    }
}
