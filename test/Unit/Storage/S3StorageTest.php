<?php
namespace LexicalBits\BackupRotator\Test\Unit\Storage;

use LexicalBits\BackupRotator\Test\TestCase;
use LexicalBits\BackupRotator\Storage\S3Storage;
use LexicalBits\BackupRotator\Storage\StorageException;
use Aws\S3\S3Client;

final class S3StorageTest extends TestCase {
    protected function setS3Client($storage)
    {
        $s3Client = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'createMultipartUpload',
                'uploadPart',
                'completeMultipartUpload',
                'putObject'
            ])
            ->getMock();
        $this->setInternalProperty(S3Storage::class, 's3', $storage, $s3Client);
        return $s3Client;
    }
    protected $s3Client;

    public function setUp()
    {
    }
    public function tearDown()
    {
    }
    public function testFromConfigThrowsWithoutBothCredentials()
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionCode(StorageException::GENERAL_INVALID_CONFIG);
        $this->expectExceptionMessage('custom credentials');
        S3Storage::fromConfig(['aws_key'=>'foo']);
    }
    public function testFromConfigThrowsWithoutNecessaryKeys()
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionCode(StorageException::GENERAL_INVALID_CONFIG);
        $this->expectExceptionMessage('required key');
        S3Storage::fromConfig([]);
    }
    public function testFromConfigCreatesInstance()
    {
        $this->assertInstanceOf(S3Storage::class, S3Storage::fromConfig([
            'region' => 'foo',
            'bucket' => 'bar',
            'filename' => 'baz'
        ]));
    }
    public function testOnChunkOnlyCachesWhenUnderLimit()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->exactly(0))
            ->method('createMultipartUpload');
        $s3Client->expects($this->exactly(0))
            ->method('uploadPart');
        $s3Client->expects($this->exactly(0))
            ->method('putObject');
        $s3Client->expects($this->exactly(0))
            ->method('completeMultipartUpload');
        $storage->onChunk('abc123', 1);
        $storage->onChunk('def456', 2);
    }
    public function testOnChunkInitializesMultipartOnFirstChunk()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->once())
            ->method('createMultipartUpload');
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 1024) + 1), 1);
        $this->assertEquals($storage->mode, S3Storage::MODE_MULTIPART);
    }
    public function testOnChunkUploadsCorrectSizeChunksAsParts()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->once())
            ->method('uploadPart');
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 512) + 1), 1);
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 512) + 1), 1);
    }
    public function testOnChunkUploadsMultipleParts()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->exactly(2))
            ->method('uploadPart');
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 1024) + 1), 1);
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 1024) + 1), 1);
    }
    public function testOnEndDoesNothingWithNoData()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->exactly(0))
            ->method('uploadPart');
        $s3Client->expects($this->exactly(0))
            ->method('putObject');
        $s3Client->expects($this->exactly(0))
            ->method('completeMultipartUpload');
        $storage->onEnd();
    }
    public function testOnEndUploadsSmallData()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->once())
            ->method('putObject');
        $storage->onChunk('abc123', 1);
        $storage->onEnd();
    }
    public function testOnEndFinalizesMultipartUploads()
    {
        $storage = new S3Storage('foo', 'bar', 'baz');
        $s3Client = $this->setS3Client($storage);
        $s3Client->expects($this->once())
            ->method('completeMultipartUpload');
        $storage->onChunk(str_repeat('0', (S3Storage::MINIMUM_MULTIPART_SIZE * 1024) + 1), 1);
        $storage->onEnd();
    }

}
