<?php

namespace VegasShop\Utils;

/**
 * Cache Class
 * Simple file-based caching system
 */
class Cache
{
    private static $instance = null;
    private $driver;
    private $defaultTtl;

    private function __construct()
    {
        $this->defaultTtl = (int)($_ENV['CACHE_TTL'] ?? 3600);
        $driver = $_ENV['CACHE_DRIVER'] ?? 'file';
        
        switch ($driver) {
            case 'file':
                $this->driver = new FileCacheDriver();
                break;
            case 'memory':
                $this->driver = new MemoryCacheDriver();
                break;
            default:
                $this->driver = new FileCacheDriver();
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
     * Get value from cache
     */
    public static function get($key, $default = null)
    {
        return self::getInstance()->driver->get($key, $default);
    }

    /**
     * Set value in cache
     */
    public static function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? self::getInstance()->defaultTtl;
        return self::getInstance()->driver->set($key, $value, $ttl);
    }

    /**
     * Check if key exists in cache
     */
    public static function has($key)
    {
        return self::getInstance()->driver->has($key);
    }

    /**
     * Delete key from cache
     */
    public static function delete($key)
    {
        return self::getInstance()->driver->delete($key);
    }

    /**
     * Clear all cache
     */
    public static function clear()
    {
        return self::getInstance()->driver->clear();
    }

    /**
     * Get or set value with callback
     */
    public static function remember($key, $callback, $ttl = null)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * Increment numeric value
     */
    public static function increment($key, $value = 1)
    {
        $current = self::get($key, 0);
        $newValue = $current + $value;
        self::set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrement numeric value
     */
    public static function decrement($key, $value = 1)
    {
        $current = self::get($key, 0);
        $newValue = $current - $value;
        self::set($key, $newValue);
        return $newValue;
    }

    /**
     * Get multiple keys
     */
    public static function many($keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key);
        }
        return $result;
    }

    /**
     * Set multiple keys
     */
    public static function putMany($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            self::set($key, $value, $ttl);
        }
        return true;
    }
}

/**
 * File Cache Driver
 */
class FileCacheDriver
{
    private $directory;
    private $prefix = 'cache';

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/vegasshop_cache';
        
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get($key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data === false || !isset($data['expires']) || $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $data['value'];
    }

    public function set($key, $value, $ttl)
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function has($key)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data === false || !isset($data['expires']) || $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    public function delete($key)
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function clear()
    {
        $pattern = $this->directory . '/' . $this->prefix . '_*';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }

    private function getFilePath($key)
    {
        return $this->directory . '/' . $this->prefix . '_' . md5($key) . '.dat';
    }
}

/**
 * Memory Cache Driver
 */
class MemoryCacheDriver
{
    private $cache = [];

    public function get($key, $default = null)
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }
        
        $data = $this->cache[$key];
        
        if ($data['expires'] < time()) {
            unset($this->cache[$key]);
            return $default;
        }
        
        return $data['value'];
    }

    public function set($key, $value, $ttl)
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        return true;
    }

    public function has($key)
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        $data = $this->cache[$key];
        
        if ($data['expires'] < time()) {
            unset($this->cache[$key]);
            return false;
        }
        
        return true;
    }

    public function delete($key)
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear()
    {
        $this->cache = [];
        return true;
    }
}
