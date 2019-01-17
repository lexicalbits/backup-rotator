<?php
namespace LexicalBits\BackupRotator\Storage;

class StorageException extends \Exception
{
    /**
     * S3->OnChunk was called when it was expected to be done
     */
    const S3_EXTRA_CALL = 300;
    /**
     * S3->OnChunk was called when in an invalid mode
     */
    const S3_UNKNOWN_MODE = 301;
    /**
     * Track an error in a storage system
     *
     * @param string $message What happened
     * @param int $code What type of issue this was (use a code CONST here)
     * @param Exception|null $previous A previously caught error if available
     */
    public function __constructor(string $message, int $code, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

