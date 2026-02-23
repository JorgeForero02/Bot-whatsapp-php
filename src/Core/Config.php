<?php

namespace App\Core;

class Config
{
    private static $config = null;

    public static function load($configFile)
    {
        if (self::$config === null) {
            self::loadEnv(dirname(dirname(__DIR__)) . '/.env');
            
            if (!file_exists($configFile)) {
                throw new \RuntimeException('Configuration file not found: ' . $configFile);
            }
            self::$config = require $configFile;
        }
        return self::$config;
    }
    
    private static function loadEnv($envFile)
    {
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    public static function get($key, $default = null)
    {
        if (self::$config === null) {
            throw new \RuntimeException('Configuration not loaded');
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function all()
    {
        return self::$config;
    }
}
