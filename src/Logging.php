<?php
namespace LexicalBits\BackupRotator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NullHandler;

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
        if (self::$path) {
            $logger->pushHandler(new StreamHandler(self::$path));
        } else {
            $logger->pushHandler(new NullHandler());
        }
        return $logger;
    }
}
