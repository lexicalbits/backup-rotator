<?php
namespace LexicalBits\BackupRotator\Storage;

class StorageException extends \Exception
{
    const GENERAL_INVALID_CONFIG = 0;
    /**
     * A storage engine was configured that does not exist.
     */
    const GENERAL_INVALID_ENGINE = 10;
    /**
     * A modifier used to copy/delete was invalid (empty or unusable)
     */
    const GENERAL_INVALID_MODIFIER = 20;
    /**
     * S3->onChunk was called when it was expected to be done
     */
    const S3_EXTRA_CALL = 300;
    /**
     * S3->onChunk was called when in an invalid mode
     */
    const S3_UNKNOWN_MODE = 310;
    /**
     * S3->getExistingCopyModifiers found an unsafe amount of data
     */
    const S3_TOO_MANY_FILES = 320;
    /**
     * Track an error in a storage system
     *
     * @param string $message What happened
     * @param int $code What type of issue this was (use a code CONST here)
     * @param \Exception|null $previous A previously caught error if available
     */
    public function __constructor(string $message, int $code, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

