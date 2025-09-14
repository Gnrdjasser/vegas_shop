<?php

namespace VegasShop\Utils;

/**
 * Logger Class
 * Handles application logging with different levels and file rotation
 */
class Logger
{
    private static $instance = null;
    private $logFile;
    private $logLevel;
    private $maxFileSize;
    private $maxFiles;

    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];

    private function __construct()
    {
        $this->logFile = $_ENV['LOG_FILE'] ?? 'logs/app.log';
        $this->logLevel = self::LEVELS[$_ENV['LOG_LEVEL'] ?? 'debug'];
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->maxFiles = 5;

        // Create logs directory if it doesn't exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log debug message
     */
    public static function debug($message, $context = [])
    {
        self::getInstance()->log('debug', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = [])
    {
        self::getInstance()->log('info', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = [])
    {
        self::getInstance()->log('warning', $message, $context);
    }

    /**
     * Log error message
     */
    public static function error($message, $context = [])
    {
        self::getInstance()->log('error', $message, $context);
    }

    /**
     * Log critical message
     */
    public static function critical($message, $context = [])
    {
        self::getInstance()->log('critical', $message, $context);
    }

    /**
     * Log message with specified level
     */
    private function log($level, $message, $context = [])
    {
        if (self::LEVELS[$level] < $this->logLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        // Check if file rotation is needed
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogFile();
        }

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file
     */
    private function rotateLogFile()
    {
        // Move existing log files
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Move current log file
        if (file_exists($this->logFile)) {
            rename($this->logFile, $this->logFile . '.1');
        }
    }

    /**
     * Clear all log files
     */
    public static function clear()
    {
        $instance = self::getInstance();
        $logDir = dirname($instance->logFile);
        $pattern = $logDir . '/' . basename($instance->logFile) . '*';
        
        foreach (glob($pattern) as $file) {
            unlink($file);
        }
    }
}
