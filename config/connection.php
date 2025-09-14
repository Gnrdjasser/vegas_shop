<?php

/**
 * Vegas Shop Database Connection
 * Simplified database connection without external config dependencies
 */

// Prevent direct access
if (!defined('VEGAS_SHOP_ACCESS')) {
    define('VEGAS_SHOP_ACCESS', true);
}

// Load environment variables from .env file (simple implementation)
function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

// Database configuration from environment variables
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'vegas_shop');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '159753');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);

// Environment
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development');

// PDO options
define('PDO_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
]);

// Security constants
define('HASH_ALGO', PASSWORD_DEFAULT);
define('HASH_OPTIONS', ['cost' => 12]);
define('CSRF_TOKEN_LENGTH', 32);

class DatabaseConnection
{
    private static $instance = null;
    private $connection;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance of database connection
     * 
     * @return DatabaseConnection
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     * 
     * @throws PDOException
     */
    private function connect()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, PDO_OPTIONS);

            // Additional security settings
            $this->connection->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    /**
     * Get PDO connection instance
     * 
     * @return PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Execute a prepared statement with parameters
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     * @throws PDOException
     */
    public function executeQuery($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);

            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }

            return $stmt;
        } catch (PDOException $e) {
            $this->handleQueryError($e, $query);
            throw $e;
        }
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback()
    {
        $this->connection->rollBack();
    }

    /**
     * Check if currently in a transaction
     * 
     * @return bool
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    /**
     * Handle connection errors
     * 
     * @param PDOException $e
     * @throws PDOException
     */
    private function handleConnectionError(PDOException $e)
    {
        $errorMessage = "Database connection failed";

        if (ENVIRONMENT === 'development') {
            $errorMessage .= ": " . $e->getMessage();
        }

        error_log("Database Connection Error: " . $e->getMessage());
        throw new PDOException($errorMessage);
    }

    /**
     * Handle query errors
     * 
     * @param PDOException $e
     * @param string $query
     */
    private function handleQueryError(PDOException $e, $query)
    {
        $errorMessage = "Database query error";

        if (ENVIRONMENT === 'development') {
            $errorMessage .= ": " . $e->getMessage() . " | Query: " . $query;
        }

        error_log("Database Query Error: " . $e->getMessage() . " | Query: " . $query);
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Close database connection
     */
    public function __destruct()
    {
        $this->connection = null;
    }
}

/**
 * Security Helper Functions
 */
class SecurityHelper
{
    /**
     * Generate secure password hash
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword($password)
    {
        return password_hash($password, HASH_ALGO, HASH_OPTIONS);
    }

    /**
     * Verify password against hash
     * 
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Sanitize input data
     * 
     * @param mixed $data
     * @return mixed
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email address
     * 
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Initialize error handling
require_once __DIR__ . '/../src/utils/ErrorHandler.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Register error handler
\VegasShop\Utils\ErrorHandler::getInstance();

// Create a global PDO instance for simple access
try {
    $pdo = DatabaseConnection::getInstance()->getConnection();
} catch (Exception $e) {
    \VegasShop\Utils\Logger::critical('Database connection failed', [
        'error' => $e->getMessage(),
        'environment' => ENVIRONMENT
    ]);
    
    if (ENVIRONMENT === 'development') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}
