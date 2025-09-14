<?php
/**
 * API Middleware
 * Handles common API functionality like CORS, authentication, and error handling
 */

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON content type for API responses
header('Content-Type: application/json');

// Initialize error handling
require_once __DIR__ . '/../config/connection.php';

// API version
define('API_VERSION', '1.0');

// Common API functions
class ApiMiddleware
{
    /**
     * Send JSON response
     */
    public static function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send error response
     */
    public static function sendError($message, $statusCode = 400, $details = [])
    {
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $statusCode
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        self::sendResponse($response, $statusCode);
    }

    /**
     * Send success response
     */
    public static function sendSuccess($data = null, $message = 'Success', $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::sendResponse($response, $statusCode);
    }

    /**
     * Validate API key (if using API keys)
     */
    public static function validateApiKey($apiKey)
    {
        // Implement API key validation if needed
        return true; // For now, allow all requests
    }

    /**
     * Get JSON input
     */
    public static function getJsonInput()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::sendError('Invalid JSON input', 400);
        }

        return $data;
    }

    /**
     * Validate required fields
     */
    public static function validateRequired($data, $requiredFields)
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            self::sendError('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Log API request
     */
    public static function logRequest($endpoint, $method, $data = null)
    {
        \VegasShop\Utils\Logger::info('API Request', [
            'endpoint' => $endpoint,
            'method' => $method,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ]);
    }

    /**
     * Handle API errors
     */
    public static function handleError($exception)
    {
        \VegasShop\Utils\Logger::error('API Error', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        self::sendError('Internal server error', 500);
    }
}

// Set up error handling for API
set_exception_handler(['ApiMiddleware', 'handleError']);
