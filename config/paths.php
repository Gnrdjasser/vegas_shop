<?php
/**
 * Vegas Shop - Path Configuration
 * Centralized path management for images and uploads
 */

// Prevent direct access
if (!defined('VEGAS_SHOP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Image and Upload Path Configuration
 */
class PathConfig
{
    // Base directories
    const BASE_DIR = __DIR__ . '/..';
    const PUBLIC_DIR = self::BASE_DIR . '/public';
    
    // Upload directories (absolute paths)
    const UPLOAD_BASE_DIR = self::PUBLIC_DIR . '/uploads';
    const PRODUCT_UPLOAD_DIR = self::UPLOAD_BASE_DIR . '/products';
    const USER_UPLOAD_DIR = self::UPLOAD_BASE_DIR . '/users';
    const TEMP_UPLOAD_DIR = self::UPLOAD_BASE_DIR . '/temp';
    
    // Web accessible paths (relative to public directory)
    const WEB_UPLOAD_BASE = '/uploads';
    const WEB_PRODUCT_IMAGES = self::WEB_UPLOAD_BASE . '/products';
    const WEB_USER_IMAGES = self::WEB_UPLOAD_BASE . '/users';
    
    // Asset directories
    const ASSETS_DIR = self::PUBLIC_DIR . '/assets';
    const IMAGES_DIR = self::ASSETS_DIR . '/images';
    const CSS_DIR = self::ASSETS_DIR . '/css';
    const JS_DIR = self::ASSETS_DIR . '/js';
    
    // Web accessible asset paths
    const WEB_ASSETS = '/assets';
    const WEB_IMAGES = self::WEB_ASSETS . '/images';
    const WEB_CSS = self::WEB_ASSETS . '/css';
    const WEB_JS = self::WEB_ASSETS . '/js';
    
    // Default/placeholder images
    const DEFAULT_PRODUCT_IMAGE = self::WEB_IMAGES . '/no-image.svg';
    const DEFAULT_USER_AVATAR = self::WEB_IMAGES . '/default-avatar.svg';
    const LOGO_IMAGE = self::WEB_IMAGES . '/logo.png';
    
    // Image size configurations
    const PRODUCT_IMAGE_SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 400, 'height' => 400],
        'large' => ['width' => 800, 'height' => 600]
    ];
    
    const USER_AVATAR_SIZES = [
        'small' => ['width' => 50, 'height' => 50],
        'medium' => ['width' => 100, 'height' => 100],
        'large' => ['width' => 200, 'height' => 200]
    ];
    
    /**
     * Get absolute path for product uploads
     * 
     * @return string
     */
    public static function getProductUploadPath()
    {
        return self::PRODUCT_UPLOAD_DIR;
    }
    
    /**
     * Get web accessible path for product images
     * 
     * @param string|null $filename Optional filename to append
     * @return string
     */
    public static function getProductImageUrl($filename = null)
    {
        $basePath = self::WEB_PRODUCT_IMAGES;
        return $filename ? $basePath . '/' . $filename : $basePath;
    }
    
    /**
     * Get absolute path for user uploads
     * 
     * @return string
     */
    public static function getUserUploadPath()
    {
        return self::USER_UPLOAD_DIR;
    }
    
    /**
     * Get web accessible path for user images
     * 
     * @param string|null $filename Optional filename to append
     * @return string
     */
    public static function getUserImageUrl($filename = null)
    {
        $basePath = self::WEB_USER_IMAGES;
        return $filename ? $basePath . '/' . $filename : $basePath;
    }
    
    /**
     * Get default product image URL
     * 
     * @return string
     */
    public static function getDefaultProductImage()
    {
        return self::DEFAULT_PRODUCT_IMAGE;
    }
    
    /**
     * Get default user avatar URL
     * 
     * @return string
     */
    public static function getDefaultUserAvatar()
    {
        return self::DEFAULT_USER_AVATAR;
    }
    
    /**
     * Check if a product image file exists
     * 
     * @param string $filename
     * @return bool
     */
    public static function productImageExists($filename)
    {
        if (empty($filename)) {
            return false;
        }
        
        $fullPath = self::PRODUCT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
        return file_exists($fullPath) && is_file($fullPath);
    }
    
    /**
     * Check if a user image file exists
     * 
     * @param string $filename
     * @return bool
     */
    public static function userImageExists($filename)
    {
        if (empty($filename)) {
            return false;
        }
        
        $fullPath = self::USER_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
        return file_exists($fullPath) && is_file($fullPath);
    }
    
    /**
     * Get product image URL with fallback to default
     * 
     * @param string $image Image filename
     * @return string
     */
    public static function getProductImageWithFallback($image)
    {
        // Check if image exists
        if (!empty($image) && self::productImageExists($image)) {
            return self::getProductImageUrl($image);
        }
        
        // Return default image
        return self::getDefaultProductImage();
    }
    
    /**
     * Get user avatar URL with fallback to default
     * 
     * @param string $avatar Avatar filename
     * @return string
     */
    public static function getUserAvatarWithFallback($avatar)
    {
        if (!empty($avatar) && self::userImageExists($avatar)) {
            return self::getUserImageUrl($avatar);
        }
        
        return self::getDefaultUserAvatar();
    }
    
    /**
     * Create upload directories if they don't exist
     * 
     * @return bool
     */
    public static function createUploadDirectories()
    {
        $directories = [
            self::UPLOAD_BASE_DIR,
            self::PRODUCT_UPLOAD_DIR,
            self::USER_UPLOAD_DIR,
            self::TEMP_UPLOAD_DIR,
            self::ASSETS_DIR,
            self::IMAGES_DIR
        ];
        
        $success = true;
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $success = false;
                    error_log("Failed to create directory: $dir");
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Get image size configuration
     * 
     * @param string $type 'product' or 'user'
     * @param string $size 'thumbnail', 'medium', 'large', etc.
     * @return array|null
     */
    public static function getImageSize($type, $size)
    {
        switch ($type) {
            case 'product':
                return self::PRODUCT_IMAGE_SIZES[$size] ?? null;
            case 'user':
                return self::USER_AVATAR_SIZES[$size] ?? null;
            default:
                return null;
        }
    }
    
    /**
     * Get all available image sizes for a type
     * 
     * @param string $type 'product' or 'user'
     * @return array
     */
    public static function getAvailableImageSizes($type)
    {
        switch ($type) {
            case 'product':
                return array_keys(self::PRODUCT_IMAGE_SIZES);
            case 'user':
                return array_keys(self::USER_AVATAR_SIZES);
            default:
                return [];
        }
    }
    
    /**
     * Clean up old temporary files
     * 
     * @param int $maxAge Maximum age in seconds (default: 1 hour)
     * @return int Number of files deleted
     */
    public static function cleanupTempFiles($maxAge = 3600)
    {
        $tempDir = self::TEMP_UPLOAD_DIR;
        $deleted = 0;
        
        if (!is_dir($tempDir)) {
            return $deleted;
        }
        
        $files = glob($tempDir . '/*');
        $cutoff = time() - $maxAge;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Get file extension from filename
     * 
     * @param string $filename
     * @return string
     */
    public static function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Check if file extension is allowed for images
     * 
     * @param string $filename
     * @return bool
     */
    public static function isAllowedImageExtension($filename)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $extension = self::getFileExtension($filename);
        
        return in_array($extension, $allowedExtensions);
    }
    
    /**
     * Generate unique filename to prevent conflicts
     * 
     * @param string $originalName
     * @param string $prefix Optional prefix
     * @return string
     */
    public static function generateUniqueFilename($originalName, $prefix = '')
    {
        $extension = self::getFileExtension($originalName);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Add prefix if provided
        if (!empty($prefix)) {
            $basename = $prefix . '_' . $basename;
        }
        
        // Add timestamp and random number for uniqueness
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
}

/**
 * Helper functions for easy access
 */

/**
 * Get product image URL with fallback
 * 
 * @param string|array $images
 * @return string
 */
function getProductImage($images)
{
    return PathConfig::getProductImageWithFallback($images);
}

/**
 * Get user avatar URL with fallback
 * 
 * @param string $avatar
 * @return string
 */
function getUserAvatar($avatar)
{
    return PathConfig::getUserAvatarWithFallback($avatar);
}

/**
 * Get asset URL
 * 
 * @param string $path Path relative to assets directory
 * @return string
 */
function getAssetUrl($path)
{
    return PathConfig::WEB_ASSETS . '/' . ltrim($path, '/');
}

/**
 * Get image URL
 * 
 * @param string $path Path relative to images directory
 * @return string
 */
function getImageUrl($path)
{
    return PathConfig::WEB_IMAGES . '/' . ltrim($path, '/');
}

// Initialize upload directories on first load
PathConfig::createUploadDirectories();