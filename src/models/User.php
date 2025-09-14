<?php
/**
 * User Model
 * Handles user authentication and management
 */

require_once __DIR__ . '/../../config/connection.php';

class User
{
    private $db;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }
    
    /**
     * Create a new user
     * 
     * @param array $data User data
     * @return string Last insert ID
     */
    public function create($data)
    {
        $sql = "INSERT INTO users (username, email, password, role, is_active) 
                VALUES (:username, :email, :password, :role, :is_active)";
        
        $params = [
            'username' => SecurityHelper::sanitizeInput($data['username']),
            'email' => SecurityHelper::sanitizeInput($data['email']),
            'password' => SecurityHelper::hashPassword($data['password']),
            'role' => $data['role'] ?? 'admin',
            'is_active' => $data['is_active'] ?? true
        ];
        
        $this->db->executeQuery($sql, $params);
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Authenticate user by username/email and password
     * 
     * @param string $username Username or email
     * @param string $password Plain text password
     * @return array|false User data if successful, false otherwise
     */
    public function authenticate($username, $password)
    {
        $sql = "SELECT * FROM users 
                WHERE (username = :username OR email = :email) 
                AND is_active = 1";
        
        $stmt = $this->db->executeQuery($sql, [
            'username' => $username,
            'email' => $username
        ]);
        
        $user = $stmt->fetch();
        
        if ($user && SecurityHelper::verifyPassword($password, $user['password'])) {
            // Update last login
            $this->updateLastLogin($user['id']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return array|false
     */
    public function readById($id)
    {
        $sql = "SELECT id, username, email, role, is_active, last_login, created_at 
                FROM users WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username
     * @return array|false
     */
    public function getByUsername($username)
    {
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $this->db->executeQuery($sql, ['username' => $username]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by email
     * 
     * @param string $email Email
     * @return array|false
     */
    public function getByEmail($email)
    {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->db->executeQuery($sql, ['email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Update user
     * 
     * @param int $id User ID
     * @param array $data Updated data
     * @return int Number of affected rows
     */
    public function update($id, $data)
    {
        $updateFields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['username', 'email', 'role', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                
                if (in_array($field, ['username', 'email'])) {
                    $params[$field] = SecurityHelper::sanitizeInput($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }
        
        // Handle password update separately
        if (isset($data['password']) && !empty($data['password'])) {
            $updateFields[] = "password = :password";
            $params['password'] = SecurityHelper::hashPassword($data['password']);
        }
        
        if (empty($updateFields)) {
            throw new InvalidArgumentException("No valid fields to update");
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Update last login timestamp
     * 
     * @param int $id User ID
     * @return int Number of affected rows
     */
    public function updateLastLogin($id)
    {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount();
    }
    
    /**
     * Delete user
     * 
     * @param int $id User ID
     * @return int Number of affected rows
     */
    public function delete($id)
    {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount();
    }
    
    /**
     * Get all users
     * 
     * @param int|null $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array
     */
    public function readAll($limit = null, $offset = 0)
    {
        $sql = "SELECT id, username, email, role, is_active, last_login, created_at 
                FROM users ORDER BY created_at DESC";
        $params = [];
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params = ['limit' => $limit, 'offset' => $offset];
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if username exists
     * 
     * @param string $username Username
     * @param int|null $excludeId User ID to exclude from check
     * @return bool
     */
    public function usernameExists($username, $excludeId = null)
    {
        $sql = "SELECT 1 FROM users WHERE username = :username";
        $params = ['username' => $username];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email Email
     * @param int|null $excludeId User ID to exclude from check
     * @return bool
     */
    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT 1 FROM users WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Validate user data
     * 
     * @param array $data User data
     * @param int|null $excludeId User ID to exclude from validation
     * @return array Validation errors
     */
    public function validate($data, $excludeId = null)
    {
        $errors = [];
        
        // Required fields
        if (empty($data['username'])) {
            $errors[] = "Username is required";
        } elseif (strlen($data['username']) < 3) {
            $errors[] = "Username must be at least 3 characters long";
        } elseif (strlen($data['username']) > 50) {
            $errors[] = "Username must be 50 characters or less";
        } elseif ($this->usernameExists($data['username'], $excludeId)) {
            $errors[] = "Username already exists";
        }
        
        if (empty($data['email'])) {
            $errors[] = "Email is required";
        } elseif (!SecurityHelper::validateEmail($data['email'])) {
            $errors[] = "Invalid email format";
        } elseif ($this->emailExists($data['email'], $excludeId)) {
            $errors[] = "Email already exists";
        }
        
        if (empty($data['password']) && !$excludeId) {
            $errors[] = "Password is required";
        } elseif (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        // Role validation
        if (isset($data['role']) && !in_array($data['role'], ['admin', 'user'])) {
            $errors[] = "Invalid role specified";
        }
        
        return $errors;
    }
}
