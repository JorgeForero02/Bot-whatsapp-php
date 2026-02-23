<?php

namespace App\Core;

class Logger
{
    private $logPath;

    public function __construct($logPath = null)
    {
        $this->logPath = $logPath ?: __DIR__ . '/../../logs';
        
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function log($message, $level = 'INFO', $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        $logMessage = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $message,
            $contextStr
        );

        $filename = $this->logPath . '/' . date('Y-m-d') . '.log';
        file_put_contents($filename, $logMessage, FILE_APPEND);
    }

    public function info($message, $context = [])
    {
        $this->log($message, 'INFO', $context);
    }

    public function error($message, $context = [])
    {
        $this->log($message, 'ERROR', $context);
    }

    public function debug($message, $context = [])
    {
        $this->log($message, 'DEBUG', $context);
    }

    public function warning($message, $context = [])
    {
        $this->log($message, 'WARNING', $context);
    }
}
