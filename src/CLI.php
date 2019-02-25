<?php
namespace LexicalBits\BackupRotator;

/**
 * Helper class for CLI functionality
 * 
 */
class CLI
{
  /**
   * Create a CLI helper 
   * 
   * @param string $help Help text for this app
   * @param string $shortOptions See http://php.net/manual/en/function.getopt.php "options"
   * @param array $longOptions See http://php.net/manual/en/function.getopt.php "longopts"
   *
   */
  public function __construct(string $help, string $shortOptions, array $longOptions=[])
  {
      global $argv;
      $optionsEndIndex = null;
      $this->options = getopt($shortOptions, $longOptions, $optionIndex);
      $this->arguments = array_slice($argv, $optionsEndIndex);
      $this->help = $help;
  }
  /**
   * Get a specific argument with an optional default value
   * 
   * @param string $arg Arg from 
   * 
   * @return string|null
   */
  public function getOption(array $possibleKeys)
  {
    return array_reduce($this->options, function ($found, $key) {
        if (!$found) {
          return $this->options[$key] ?? null;
        }
        return $found;
    }, null);
  }
  /**
   * Get positional arguments
   * 
   * @return array
   */
  public function getArguments()
  {
    return $this->arguments;
  }
  /**
   * Create a path from parts 
   * 
   * @param array $parts Ex: ['var', 'www', 'src'], ['home', 'ubuntu', 'backups.log'] 
   * 
   * @return string OS-appropriate path ('c:\Users\Admin\config', '/var/www/src')
   */
  public function makePath(array $parts)
  {
    return implode(DIRECTORY_SEPARATOR, $parts);
  }
  /**
   * Get the first existing path in a list.
   * Good for cascading selection of config files.
   * 
   * @param array $partsList An array of parts useable in makePath
   * 
   * @return string The first valid path, or null if not found.
   */
  public function pickPath(array $partsList)
  {
    foreach($partsList as $parts) {
      $path = $this->makePath($parts);
      if (file_exists($path)) return $path;
    }
    return null;
  }
  /**
   * Write a single line to stdout.
   * 
   * @param string $msg What to say
   */
  public function writelnOut($msg)
  {
    print($msg.PHP_EOL);
  }
  /**
   * Show the app's help text with an optional extra message.
   * Messages are useful for communicating errors.
   * 
   * @param string $msg What to say before showing the help text.
   */
  public function showHelp($msg = '')
  {
    if ($msg) {
      $this->writelnOut($msg);
      $this->writelnOut(str_repeat('-', strlen($msg)));
    }
    $this->writelnOut($this->help);
  }
}
