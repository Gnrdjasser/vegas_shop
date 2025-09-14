<?php

namespace VegasShop\Utils;

/**
 * Security Headers Class
 * Manages security headers for enhanced protection
 */
class SecurityHeaders
{
    /**
     * Set all security headers
     */
    public static function setAll()
    {
        self::setFrameOptions();
        self::setContentTypeOptions();
        self::setXssProtection();
        self::setReferrerPolicy();
        self::setContentSecurityPolicy();
        self::setStrictTransportSecurity();
        self::removeServerInfo();
    }

    /**
     * Prevent clickjacking
     */
    public static function setFrameOptions($value = 'SAMEORIGIN')
    {
        header("X-Frame-Options: $value");
    }

    /**
     * Prevent MIME type sniffing
     */
    public static function setContentTypeOptions()
    {
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Enable XSS protection
     */
    public static function setXssProtection($value = '1; mode=block')
    {
        header("X-XSS-Protection: $value");
    }

    /**
     * Set referrer policy
     */
    public static function setReferrerPolicy($value = 'strict-origin-when-cross-origin')
    {
        header("Referrer-Policy: $value");
    }

    /**
     * Set Content Security Policy
     */
    public static function setContentSecurityPolicy($environment = 'production')
    {
        if ($environment === 'production') {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
                   "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://cdnjs.cloudflare.com; " .
                   "connect-src 'self'; " .
                   "frame-ancestors 'self'; " .
                   "base-uri 'self'; " .
                   "form-action 'self';";
        } else {
            $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                   "img-src 'self' data: https:; " .
                   "connect-src 'self';";
        }

        header("Content-Security-Policy: $csp");
    }

    /**
     * Set HSTS header
     */
    public static function setStrictTransportSecurity($maxAge = 31536000, $includeSubDomains = true, $preload = true)
    {
        $hsts = "max-age=$maxAge";
        
        if ($includeSubDomains) {
            $hsts .= '; includeSubDomains';
        }
        
        if ($preload) {
            $hsts .= '; preload';
        }

        header("Strict-Transport-Security: $hsts");
    }

    /**
     * Remove server information
     */
    public static function removeServerInfo()
    {
        header_remove('X-Powered-By');
        header_remove('Server');
    }

    /**
     * Set custom security headers
     */
    public static function setCustom($headers)
    {
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Check if HTTPS is enabled
     */
    public static function isHttps()
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }

    /**
     * Force HTTPS redirect
     */
    public static function forceHttps()
    {
        if (!self::isHttps()) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit;
        }
    }
}
