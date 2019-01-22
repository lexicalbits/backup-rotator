<?php
namespace LexicalBits\BackupRotator;

class StreamChunkerException extends \Exception
{
    const TOO_MUCH_DATA = 100;
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


