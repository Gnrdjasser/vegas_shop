<?php

namespace VegasShop\Utils;

/**
 * Session Management Class
 * Provides secure session handling
 */
class Session
{
    private const FLASH_KEY = 'flash_messages';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $this->startSession();
        }
    }

    /**
     * Start secure session
     */
    private function startSession()
    {
        $lifetime = $_ENV['SESSION_LIFETIME'] ?? 7200;
        $secure = $_ENV['SESSION_SECURE'] ?? false;
        $httponly = $_ENV['SESSION_HTTPONLY'] ?? true;

        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_lifetime', $lifetime);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Strict'
        ]);

        session_start();

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    /**
     * Get session value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     *
     * @param string $key
     */
    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Get all session data
     *
     * @return array
     */
    public function all()
    {
        return $_SESSION;
    }

    /**
     * Clear all session data
     */
    public function clear()
    {
        session_unset();
    }

    /**
     * Destroy session
     */
    public function destroy()
    {
        session_destroy();
    }

    /**
     * Set flash message
     *
     * @param string $type
     * @param string $message
     */
    public function setFlash($type, $message)
    {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }
        $_SESSION[self::FLASH_KEY][$type][] = $message;
    }

    /**
     * Get flash messages
     *
     * @return array
     */
    public function getFlashMessages()
    {
        $messages = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);
        return $messages;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->has('user_id');
    }

    /**
     * Get current user ID
     *
     * @return int|null
     */
    public function getUserId()
    {
        return $this->get('user_id');
    }

    /**
     * Set user as authenticated
     *
     * @param int $userId
     * @param array $userData
     */
    public function setUser($userId, $userData = [])
    {
        $this->set('user_id', $userId);
        $this->set('user_data', $userData);
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $this->remove('user_id');
        $this->remove('user_data');
        session_regenerate_id(true);
    }
}
