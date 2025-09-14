<?php
/**
 * File Upload Utility
 * Handles file uploads with validation and security
 */

// Include path configuration
define('VEGAS_SHOP_ACCESS', true);
require_once __DIR__ . '/../../config/paths.php';

class FileUpload
{
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    private $errors = [];
    
    public function __construct($uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?: PathConfig::getProductUploadPath();
        $this->allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload multiple files
     * 
     * @param array $files $_FILES array
     * @return array Array of uploaded filenames
     */
    public function uploadMultiple($files)
    {
        $uploadedFiles = [];
        
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $uploadedFiles;
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileInfo = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $filename = $this->uploadSingle($fileInfo);
                if ($filename) {
                    $uploadedFiles[] = $filename;
                }
            }
        }
        
        return $uploadedFiles;
    }
    
    /**
     * Upload a single file
     * 
     * @param array $file File info array
     * @return string|false Filename on success, false on failure
     */
    public function uploadSingle($file)
    {
        // Validate file
        if (!$this->validateFile($file)) {
            return false;
        }
        
        // Generate unique filename
        $filename = $this->generateFilename($file['name']);
        $filepath = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Set proper permissions
            chmod($filepath, 0644);
            return $filename;
        }
        
        $this->errors[] = "Failed to move uploaded file";
        return false;
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file File info array
     * @return bool
     */
    private function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $this->errors[] = "File size exceeds maximum allowed size of " . ($this->maxFileSize / 1024 / 1024) . "MB";
            return false;
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            $this->errors[] = "File type not allowed. Allowed types: " . implode(', ', $this->allowedTypes);
            return false;
        }
        
        // Additional security checks
        if (!$this->isValidImage($file['tmp_name'])) {
            $this->errors[] = "Invalid image file";
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if file is a valid image
     * 
     * @param string $filepath
     * @return bool
     */
    private function isValidImage($filepath)
    {
        $imageInfo = getimagesize($filepath);
        return $imageInfo !== false;
    }
    
    /**
     * Generate unique filename
     * 
     * @param string $originalName
     * @return string
     */
    private function generateFilename($originalName)
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Generate unique filename
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * Get upload error message
     * 
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "Unknown upload error";
        }
    }
    
    /**
     * Delete a file
     * 
     * @param string $filename
     * @return bool
     */
    public function deleteFile($filename)
    {
        $filepath = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Delete multiple files
     * 
     * @param array $filenames
     * @return int Number of files deleted
     */
    public function deleteMultiple($filenames)
    {
        $deleted = 0;
        
        foreach ($filenames as $filename) {
            if ($this->deleteFile($filename)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Get upload errors
     * 
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Check if file exists
     * 
     * @param string $filename
     * @return bool
     */
    public function fileExists($filename)
    {
        return file_exists($this->uploadDir . DIRECTORY_SEPARATOR . $filename);
    }
    
    /**
     * Get file URL
     * 
     * @param string $filename
     * @return string
     */
    public function getFileUrl($filename)
    {
        return '/uploads/products/' . $filename;
    }
    
    /**
     * Resize image
     * 
     * @param string $filename
     * @param int $maxWidth
     * @param int $maxHeight
     * @return bool
     */
    public function resizeImage($filename, $maxWidth = 800, $maxHeight = 600)
    {
        $filepath = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $imageInfo = getimagesize($filepath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        
        if ($ratio >= 1) {
            return true; // No need to resize
        }
        
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        // Create image resource
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filepath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save resized image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($destination, $filepath, 90);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($destination, $filepath, 9);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($destination, $filepath);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($destination);
        
        return $result;
    }
}