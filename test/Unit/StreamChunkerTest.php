<?php
namespace LexicalBits\BackupRotator\Test\Unit;

use LexicalBits\BackupRotator\Test\TestCase;
use LexicalBits\BackupRotator\StreamChunker;
use LexicalBits\BackupRotator\StreamChunkerException;

final class StreamChunkerTest extends TestCase {
    protected $temppath;
    public function setUp()
    {
        $this->temppath = tempnam(sys_get_temp_dir(), uniqid());
        $tmpfile = fopen($this->temppath, 'w');
        fwrite($tmpfile, str_repeat('0', 4098));
        fclose($tmpfile);

    }
    public function tearDown()
    {
        unlink($this->temppath);
    }
    public function testRunDeliversChunks()
    {
        $output = [];
        $chunker = new StreamChunker($this->temppath, function (string $chunk) use (&$output) {
            $output[] = $chunk;
        }, 1, 10);
        $chunker->run();
        $this->assertEquals($output, [
            str_repeat('0', 1024),
            str_repeat('0', 1024),
            str_repeat('0', 1024),
            str_repeat('0', 1024),
            str_repeat('0', 2)
        ]);
    }
    public function testRunFailsIfTooMuchData()
    {
        $this->expectException(StreamChunkerException::class);
        $this->expectExceptionCode(StreamChunkerException::TOO_MUCH_DATA);
        $this->expectExceptionMessage('aborting');

        $chunker = new StreamChunker($this->temppath, 'sprintf', 1, 3);
        $chunker->run();
    }
}
