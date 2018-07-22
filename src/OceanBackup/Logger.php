<?php

namespace OceanBackup;

/**
 * Logger currently supports 3 log levels: INFO, WARN, ERROR.
 * INFO message will go to STDIN. WARN and ERROR go to STDERR.
 * @package OceanBackup
 */
class Logger
{

    const INFO = 1 << 1;
    const WARN = 1 << 2;
    const ERROR = 1 << 3;

    /**
     * @param $level Log level.
     * @param array|string $messages
     */
    public static function log($level, $messages = [])
    {
        $message = is_array($messages)
            ? implode("\n", $messages) . "\n"
            : (string) $messages . "\n";

        switch ($level) {
            case self::WARN:
                $prefix = '[' . date('c') . '] WARN: ';
                fwrite(STDERR, $prefix . $message);
                fflush(STDERR);
                break;
            case self::ERROR:
                $prefix = '[' . date('c') . '] ERROR: ';
                fwrite(STDERR, $prefix . $message);
                fflush(STDERR);
                break;
            default:
                $prefix = '[' . date('c') . '] INFO: ';
                fwrite(STDIN, $prefix . $message);
                fflush(STDIN);

        }
    }

    /**
     * Log to STDIN.
     * @param array|string $messages
     */
    public static function info($messages = [])
    {
        return self::log(self::INFO, $messages);
    }

    /**
     * Log to STDERR.
     * @param array|string $messages
     */
    public static function warn($messages = [])
    {
        return self::log(self::WARN, $messages);
    }

    /**
     * Log to STDERR.
     * @param array|string $messages
     */
    public static function error($messages = [])
    {
        return self::log(self::ERROR, $messages);
    }
}