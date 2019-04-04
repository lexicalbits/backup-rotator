<?php
namespace LexicalBits\BackupRotator\Storage;

use LexicalBits\BackupRotator\Logging;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;

/**
 * Class: S3 - A "smart" S3 storage system that will use multipart uploads if files are sufficiently large.
 *
 * @see Storage
 */
class S3Storage extends Storage
{
    /**
     * Generate an S3Storage instance from a given config
     *
     * @param array $config Configuration data: region/bucket/filename required, aws_key/aws_secret optional
     */
    static public function fromConfig(array $config)
    {
        $credentials = null;
        if (isset($config['aws_key']) && isset($config['aws_secret'])) {
            $credentials = new Credentials($config['aws_key'], $config['aws_secret']);
        } else if (isset($config['aws_key']) || isset($config['aws_secret'])) {
            throw new StorageException(
                'Both aws_key and aws_secret need to be present to use custom credentials',
                StorageException::GENERAL_INVALID_CONFIG
            );
        }
        foreach (['region', 'bucket', 'filename'] as $key) {
            if (!isset($config[$key])) {
                throw new StorageException(
                    sprintf('Missing required key "%s"', $key),
                    StorageException::GENERAL_INVALID_CONFIG
                );
            }
        }
        return new S3Storage(
            $config['region'],
            $config['bucket'],
            $config['filename'],
            $credentials
        );
    }
    /**
     * We make our decision around 5mb chunks, the minimum needed to do a multipart upload.
     * Therefore we don't want to accept chunks smaller than this (though we can, and cache
     * as needed)
     */
    const CHUNK_SIZE_KB = 5120;
    /**
     * What character we use to isolate the base filename from the rotator's modifiers
     */
    const MODIFIER_BOUNDARY = '_';
    /**
     * We haven't yet figured out how we're going to upload the file and are waiting on additional
     * data or an onEnd call.
     */
    const MODE_UNKNOWN = 'unknown';
    /**
     * We've uploaded the file all at once.
     */
    const MODE_UPLOAD = 'upload';
    /**
     * We've started a multipart upload.
     */
    const MODE_MULTIPART = 'multipart';
    /**
     * Size that we use as a cutoff to start a multipart upload.  5120kb (5mb) is the smallest part
     * S3 will accept.
     */
    const MINIMUM_MULTIPART_SIZE = 5120; // 5mb minimum for multipart uploads
    /**
     * What upload mode we've decided on.  One of the S3Storage::MODE_* constants.
     *
     * @var string
     */
    public $mode;
    /**
     * Name of the bucket we're targetting
     *
     * @var string
     */
    protected $bucket;
    /**
     * Name of the file we're putting in the bucket
     *
     * @var string
     */
    protected $filename;
    /**
     * Configured S3 client
     *
     * @var S3Client
     */
    protected $s3;
    /**
     * Stored response data from multipart uploads
     *
     * @var array
     */
    protected $parts;
    /**
     * If we've started a multipart upload, this is the ID AWS gave us to use.
     *
     * @var string|null
     */
    protected $uploadId;
    /**
     * Temporary storage for incoming data while we either decide if we need multipart uploads
     * or until we have enought for a single multipart chunk.
     *
     * @var array
     */
    protected $cache;
    /**
     * An instance of the logger configured to this class.
     *
     * @var \Monolog\Logger
     */
    protected $logger;
    /**
     * Configure an S3 uploader.
     *
     * @param string $region Which AWS region our bucket is in
     * @param string $bucket Which bucket we're targetting
     * @param string $filename What the filename should be in the bucket
     * @param \Aws\Credentials\CredentialsInterface|null $credentials Optional credentials used to initialize S3
     */
    public function __construct(string $region, string $bucket, string $filename, $credentials=null)
    {
        $this->bucket = $bucket;
        $this->filename = $filename;
        $cfg = [
            'profile' => 'default',
            'version' => 'latest',
            'region'  => $region 
        ];
        if ($credentials) {
            $cfg['credentials'] = $credentials;
        }
        $this->s3 = new S3Client($cfg);
        $this->parts = [];
        $this->uploadId = null;
        $this->cache = [];
        $this->mode = S3Storage::MODE_UNKNOWN;
        $this->logger = Logging::make(__NAMESPACE__);
    }
    /**
     * Check how big the cache currently is
     *
     * @return int Size in bytes
     *
     */
    protected function cacheSize()
    {
        return array_sum(array_map('strlen', $this->cache));
    }
    public function onChunk(string $chunk, int $idx)
    {
        $this->cache[] = $chunk;
        $newSize = $this->cacheSize();
        $this->logger->debug(sprintf('Got chunk %d, cache is now %d bytes', $idx, $newSize));
        if ($newSize / 1024 >= S3Storage::MINIMUM_MULTIPART_SIZE) {
            $this->uploadCachedMultipart();
        }
    }
    /**
     * Initialize a multipart upload.
     *
     */
    protected function initializeMultipart()
    {
        $this->mode = S3Storage::MODE_MULTIPART;
        $this->logger->debug(sprintf("Initializing multipart upload %s in %s\n", $this->filename, $this->bucket));
        // TODO - check for existing open upload
        $this->uploadId = $this->s3->createMultipartUpload([
            'ACL' => 'private',
            'Bucket' => $this->bucket,
            'Key' => $this->filename
        ])['UploadId'];
        $this->logger->debug(sprintf("Using upload ID %s\n", $this->uploadId));
    }
    /**
     * Upload a part of a multipart upload from the cache.
     *
     * We do this instead of pushing parts on each onChunk in case the chunk size is less than S3's minimum.
     */
    protected function uploadCachedMultipart()
    {
        switch ($this->mode) {
            case S3Storage::MODE_UPLOAD:
                throw new StorageException(
                    'onChunk called in upload mode - file already uploaded',
                    StorageException::S3_EXTRA_CALL
                );
                break;
            case S3Storage::MODE_UNKNOWN:
                $this->initializeMultipart();
                break;
        }
        if (count($this->cache) > 0) {
            $partNumber = count($this->parts) + 1;
            $this->logger->debug(sprintf('Sending part %d', $partNumber));
            $res = $this->s3->uploadPart([
                'Bucket' => $this->bucket,
                'Key' => $this->filename,
                'Body' => implode('', $this->cache),
                'PartNumber' => $partNumber,
                'UploadId' => $this->uploadId
            ]);
            $this->parts[] = [
                'ETag' => $res['ETag'],
                'PartNumber' => $partNumber
            ];
            $this->cache = [];
        }
    }
    /**
     * Finalize a multipart upload.
     *
     */
    protected function completeMultipart()
    {
        $this->logger->info(sprintf("Finalizing multipart upload of %s to %s\n", $this->filename, $this->bucket));
        $this->s3->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->filename,
            'UploadId' => $this->uploadId,
            'MultipartUpload' => [
                'Parts' => $this->parts
            ]
        ]);
    }
    /**
     * Upload the entire cache a once.
     *
     */
    protected function uploadCacheSingle()
    {
        if ($this->mode === S3Storage::MODE_MULTIPART) {
            throw new StorageException('uploadSingle called in multipart mode', StorageException::S3_EXTRA_CALL);
        }
        $this->mode = S3Storage::MODE_UPLOAD;
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->filename,
            'Body' => implode('', $this->cache),
        ]);
    }
    public function onEnd() {
        switch ($this->mode) {
            case S3Storage::MODE_UNKNOWN:
                if (count($this->cache) === 0) {
                    $this->logger->debug(sprintf(
                        "No upload was initialized for %s in %s\n",
                        $this->filename, $this->bucket
                    ));
                } else {
                    $this->logger->info(sprintf(
                        "File size under multipart limit - uploading %s in one call to %s",
                        $this->filename, $this->bucket
                    ));
                    $this->uploadCacheSingle();
                }
                break;
            case S3Storage::MODE_UPLOAD:
                throw new StorageException(
                    'onEnd called after a single upload already occurred',
                    StorageException::S3_EXTRA_CALL
                );
                break;
            case S3Storage::MODE_MULTIPART:
                $this->uploadCachedMultipart(); // In case there's anything left in the cache
                $this->completeMultipart();
                break;
            default:
                throw new StorageException(
                    sprintf('Stuck in an unknown mode: %s', $this->mode),
                    StorageException::S3_UNKNOWN_MODE
                );
        }
    }

    public function getExistingCopyModifiers()
    {
        $pathInfo = pathinfo($this->filename);
        $filename = $pathInfo['filename'];
        // If we find ourselves needing more items in the future, there is a paginator we can use,
        // but the 1000 maximum results from a regular query seems fine.  In fact, we'll throw an error
        // if we hit that cap, 'cause that probably means something went wrong...
        $reply = $this->s3->listObjects([
            'Bucket' => $this->bucket
        ]);
        $results = $reply['Contents'];
        if (count($results) === 1000) {
            throw new StorageException(
                'Too many stored results (1000) - check your bucket for extra data',
                StorageException::S3_TOO_MANY_FILES
            );
        }
        // Figure out a regex we can use to extract a modifier from a file that matches our modified format
        // Makes something like /^myfile_.*\.tar.gz$/
        $pattern = sprintf(
            '/^%s%s(.*)\\.?%s$/',
            preg_quote($filename),
            preg_quote(self::MODIFIER_BOUNDARY),
            preg_quote(isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '')
        );
        // The extra call to values is because filter persists the original key (even though the resulting array is
        // not sparse)
        return array_values(array_filter(array_map(function ($record) use ($pattern) {
            $entry = $record['Key'];
            $modifier = preg_replace($pattern, '$1', $entry);
            if ($modifier && $modifier !== $entry) return $modifier;
            return null;
        }, $results)));
    }
    public function getModifiedPath(string $modifier)
    {
        $pathInfo = pathinfo($this->filename);
        return implode('', [
            $pathInfo['filename'],
            self::MODIFIER_BOUNDARY,
            $modifier,
            isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : ''
        ]);
    }

    protected function assertValidModifier(string $modifier)
    {
        if (trim($modifier) === '') {
            throw new StorageException('Modifier cannot be empty', StorageException::GENERAL_INVALID_MODIFIER);
        }
        // https://docs.aws.amazon.com/AmazonS3/latest/dev/UsingMetadata.html#object-keys
        if (strlen($modifier) + strlen($this->filename) > 1024) {
            throw new StorageException('Modifier too long', StorageException::GENERAL_INVALID_MODIFIER);
        }
    }

    public function copyWithModifier(string $modifier, bool $allowOverwrite=false)
    {
        $this->assertValidModifier($modifier);
        $targetFile = $this->getModifiedPath($modifier);
        $exists = $this->s3->doesObjectExist($this->bucket, $targetFile);
        if ($allowOverwrite || !$exists) {
            $this->s3->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $targetFile,
                'CopySource' => sprintf("%s/%s", $this->bucket, $this->filename)
            ]);
            if ($exists) {
                $this->logger->info(sprintf('Overwrote existing copy at %s', $targetFile));
            } else {
                $this->logger->info(sprintf('Created new copy at %s', $targetFile));
            }
        } else {
            $this->logger->info(sprintf('Skipped overwriting existing copy at %s', $targetFile));
        }
    }
    public function deleteWithModifier(string $modifier)
    {
        $this->assertValidModifier($modifier);
        $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->getModifiedPath($modifier)
        ]);
    }
}
