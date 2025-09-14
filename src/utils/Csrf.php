<?php

namespace VegasShop\Utils;

/**
 * CSRF Protection Class
 */
class Csrf
{
    private const TOKEN_KEY = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->getToken()) {
            $this->generateToken();
        }
    }

    /**
     * Generate a new CSRF token
     *
     * @return string
     */
    public function generateToken()
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_KEY] = $token;
        return $token;
    }

    /**
     * Get current CSRF token
     *
     * @return string|null
     */
    public function getToken()
    {
        return $_SESSION[self::TOKEN_KEY] ?? null;
    }

    /**
     * Validate a given token
     *
     * @param string|null $token
     * @return bool
     */
    public function validateToken($token)
    {
        if (!$token) {
            return false;
        }

        $sessionToken = $this->getToken();
        return hash_equals($sessionToken, $token);
    }

    /**
     * Remove the CSRF token from session
     */
    public function clearToken()
    {
        unset($_SESSION[self::TOKEN_KEY]);
    }
}
