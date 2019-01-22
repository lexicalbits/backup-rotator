<?php
namespace LexicalBits\BackupRotator;

class StreamChunker
{
    /**
     * Name of the stream to read.
     * This can be a filename, a 'php://' stream, or anything else supported by fopen.
     *
     * @var string
     */
    protected $streamName;
    /**
     * Method to call when a chunk has been fetched.
     *
     * @var callable
     */
    protected $onChunk;
    /**
     * How big a chunk we're taking at a time (in kilobytes)
     *
     * @var int
     */
    protected $chunkSizeKb;
    /**
     * The biggest we expect this file to be (for safety's sake)
     *
     * @var int
     */
    protected $maxSizeKb;
    /**
     * What state the instance is in.
     * Can either be StreamChunker::STATUS_NEW or StreamChunker::STATUS_USED if the stream has been read
     *
     * @var int
     */
    protected $status;
    /**
     * Configure a streaming data handler.
     *
     * @param string $streamName Name of the stream we're processing (a filename, probably)
     * @param callable $onChunk What to call with each chunk as it's consumed
     * @param int $chunkSizeKb How big we want each chunk to be (see stream_get_contents)
     * @param int $maxSize The absolute biggest this file should be.  Acts as a sanity check for broken streams.
     */
    public function __construct(string $streamName, callable $onChunk, int $chunkSizeKb=1024, int $maxSizeKb=10240)
    {
        $this->streamName = $streamName;
        $this->onChunk = $onChunk;
        $this->chunkSizeKb = $chunkSizeKb;
        $this->maxSizeKb = $maxSizeKb;
        $this->totalSize = 0;
        $this->logger = Logging::make(__NAMESPACE__);
    }
    /**
     * Start streaming to the handlers.
     *
     */
    public function run()
    {
        $stream = fopen($this->streamName, 'r');
        $chunkSize = $this->chunkSizeKb * 1024;
        $maxSize = $this->maxSizeKb * 1024;
        $ctr = 0;
        while (!feof($stream) && $this->totalSize < $maxSize) {
            $ctr++;
            $chunk = stream_get_contents($stream, $chunkSize);
            $this->totalSize += strlen($chunk);
            call_user_func($this->onChunk, $chunk, $ctr, $this->streamName);
        }
        if ($this->totalSize >= $maxSize) {
            // TODO cleanup script
            throw new StreamChunkerException(
                sprintf('Exceeded max size of %d: %d reached, aborting.', $maxSize, $this->totalSize),
                StreamChunkerException::TOO_MUCH_DATA
            );
        }
        fclose($stream);
    }
}
