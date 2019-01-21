<?php
namespace LexicalBits\BackupRotator\Storage;

use LexicalBits\BackupRotator\Logging;
use Aws\S3\S3Client;

/**
 * Class: S3 - A "smart" S3 storage system that will use multipart uploads if files are sufficiently large.
 *
 * @see Storage
 */
class FileStorage extends Storage
{
    /**
     * I'm not sure if there is an optimal size for this.  2mb seems rightish.
     */
    const CHUNK_SIZE_KB = 2048;
    /**
     * Generate an S3Storage instance from a given config
     *
     * @param array $config Configuration data: region/bucket/filename required, aws_key/aws_secret optional
     */
    static public function fromConfig(array $config)
    {
        if (!isset($config['path'])) {
            throw new StorageException(
                sprintf('Missing required key "path"'),
                StorageException::GENERAL_INVALID_CONFIG
            );
        }
        return new FileStorage($config['path']);
    }
    /**
     * Where the file should be written (including filename)
     *
     * @var string
     */
    protected $path;
    /**
     * Open resource pointer used to write streaming data
     *
     * @var resource
     */
    protected $file;
    /**
     * Initialize a file storage engine
     *
     * @param string $path Where to put the new file
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        $this->file = null;
        $this->logger = Logging::make(__NAMESPACE__);
    }
    /**
     * Handle a chunk of data.
     *
     * @param string $chunk Data chunk
     * @param int $idx Which chunk this is.  Note this is just needed to match the base class spec.
     */
    public function onChunk(string $chunk, int $idx)
    {
        if (!$this->file) {
            $this->logger->debug(sprintf('Opening %s for writing', $this->path));
            $this->file = fopen($this->path, 'w');
        }
        fwrite($this->file, $chunk);
    }
    /**
     * Handle an onEnd event
     *
     */
    public function onEnd()
    {
        $this->logger->debug(sprintf('Closing %s', $this->path));
        fclose($this->file);
    }
}
