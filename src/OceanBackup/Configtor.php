<?php

namespace OceanBackup;

/**
 * Configtor load configurations from the following locations, in the following sequence.
 *   - ~/ocean_backup.ini
 *   - /etc/ocean_backup.ini
 * @package OceanBackup
 */
class Configtor
{

    /** @var Configtor Singleton instance. */
    protected static $instance = null;

    protected $config = [];

    /**
     * Configtor constructor.
     */
    public function __construct()
    {
        $filePaths = [
            '~/ocean_backup.ini',
            '/etc/ocean_backup.ini',
        ];

        foreach ($filePaths as $filePath)
        {
            if (!is_readable($filePath))
            {
                continue;
            }

            $arr = parse_ini_file($filePath);
            if ($arr == false)
            {
                continue;
            }

            $this->config = $arr;
            return;
        }
        
        error_log("Config file not found.");
    }

    /**
     * Return config value. If `$key` is not present, return whole config as array.
     * @param str $key
     * @param mixed $default Default value if key is not found.
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config;
        }

        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $default;
    }

    /**
     * Singleton method.
     * @return Configtor
     */
    public static function instance()
    {
        if (self::$instance == null) {
            self::$instance = new Configtor();
        }

        return self::$instance;
    }

}

