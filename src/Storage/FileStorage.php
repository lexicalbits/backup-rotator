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
    const MODIFIER_BOUNDARY = '_';
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

    public function onChunk(string $chunk, int $idx)
    {
        if (!$this->file) {
            $this->logger->debug(sprintf('Opening %s for writing', $this->path));
            $this->file = fopen($this->path, 'w');
        }
        fwrite($this->file, $chunk);
    }

    public function onEnd()
    {
        $this->logger->debug(sprintf('Closing %s', $this->path));
        fclose($this->file);
    }

    public function getStorageKey()
    {
        return $this->path;
    }
    public function getModifiedPath(string $modifier)
    {
        $pathInfo = pathinfo($this->path);
        return implode('', [
            $pathInfo['directory'],
            DIRECTORY_SEPARATOR,
            $pathInfo['filename'],
            self::MODIFIER_BOUNDARY,
            $modifier,
            '.',
            $pathInfo['extension']
        ]);
    }
    public function getExistingCopyModifiers()
    {
        $pathInfo = pathinfo($this->path);
        // Makes something like /^myfile_.*\.tar.gz$/
        $pattern = sprintf(
            '/^%s%s.*\\.%s$/',
            $pathInfo['filename'],
            self::MODIFIER_BOUNDARY,
            $pathInfo['extension']
        );
        return array_map(
            glob(implode(DIRECTORY_SEPARATOR, [$pathInfo['directory'], $pathInfo['filename']])),
            function ($filename) {
                return preg_replace($pattern, $filename);
            }
        );
    }
    public function copyWithModifier(string $modifier, bool $allowOverwrite=false)
    {
        $targetFile = $this->getModifiedPath($modifier);
        $exists = file_exists($targetFile);
        if ($allowOverwrite || !$exists) {
            copy($this->path, $targetFile);
            if ($exists) {
                $this->logger->info(sprintf('Overwrite existing copy at %s', $targetFile));
            } else {
                $this->logger->info(sprintf('Created new copy at %s', $targetFile));
            }
        } else {
            $this->logger->info(sprintf('Skipped overwriting existing copy at %s', $targetFile));
        }
    }
    public function deleteWithModifier(string $modifier)
    {
        $targetFile = $this->getModifiedPath($modifier);
        if (file_exists($targetFile)) {
            $this->logger->info(sprintf('Removing existing file at %s', $targetfile));
            unlink($targetFile);
        } else {
            $this->logger->info(sprintf('Cannot remove non-existent file at %s', $targetfile));
        }
    }
}
