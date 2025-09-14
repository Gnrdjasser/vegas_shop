<?php

namespace VegasShop\Utils;

/**
 * Error Handler Class
 * Centralized error handling and exception management
 */
class ErrorHandler
{
    private static $instance = null;
    private $logger;

    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->registerHandlers();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register error and exception handlers
     */
    private function registerHandlers()
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        $errorType = $errorTypes[$severity] ?? 'Unknown Error';
        
        $this->logger->error("PHP {$errorType}: {$message}", [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ]);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception)
    {
        $this->logger->critical('Uncaught Exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->displayError($exception);
    }

    /**
     * Handle fatal errors
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->critical('Fatal Error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);

            $this->displayError(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * Display error to user
     */
    private function displayError($exception)
    {
        if (php_sapi_name() === 'cli') {
            echo "Error: " . $exception->getMessage() . "\n";
            echo "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n";
            return;
        }

        // Set proper HTTP status code
        http_response_code(500);

        // Check if we should show detailed errors
        $showDetails = ($_ENV['ENVIRONMENT'] ?? 'production') === 'development';

        if ($showDetails) {
            $this->displayDetailedError($exception);
        } else {
            $this->displayUserFriendlyError();
        }
    }

    /**
     * Display detailed error for development
     */
    private function displayDetailedError($exception)
    {
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Error - Vegas Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 20px; }
        .error-message { background: #f8f9fa; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0; }
        .error-file { color: #666; font-size: 14px; margin-top: 10px; }
        .error-trace { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">Application Error</h1>
        <div class="error-message">
            <strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '
        </div>
        <div class="error-file">
            <strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '
        </div>
        <div class="error-trace">' . htmlspecialchars($exception->getTraceAsString()) . '</div>
    </div>
</body>
</html>';
    }

    /**
     * Display user-friendly error
     */
    private function displayUserFriendlyError()
    {
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Error - Vegas Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 40px; background: #f5f5f5; text-align: center; }
        .error-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .error-icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .error-title { color: #333; font-size: 24px; margin-bottom: 15px; }
        .error-message { color: #666; margin-bottom: 30px; }
        .error-actions a { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px; }
        .error-actions a:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">Something went wrong</h1>
        <p class="error-message">We apologize for the inconvenience. Our team has been notified and is working to fix this issue.</p>
        <div class="error-actions">
            <a href="/">Go Home</a>
            <a href="javascript:history.back()">Go Back</a>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Handle API errors
     */
    public static function handleApiError($message, $code = 500, $details = [])
    {
        $logger = Logger::getInstance();
        $logger->error("API Error: {$message}", $details);

        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'details' => $details
        ]);
        exit;
    }

    /**
     * Handle validation errors
     */
    public static function handleValidationError($errors, $code = 400)
    {
        $logger = Logger::getInstance();
        $logger->warning('Validation Error', ['errors' => $errors]);

        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'code' => $code,
            'errors' => $errors
        ]);
        exit;
    }
}
