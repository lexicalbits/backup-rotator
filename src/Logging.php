<?php
namespace LexicalBits\BackupRotator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Logging
{
    static $path;
    static function setPath(string $path)
    {
        self::$path = $path;
    }
    static function make(string $name)
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler(self::$path));
        return $logger;
    }
}
