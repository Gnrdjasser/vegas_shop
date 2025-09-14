<?php

namespace VegasShop\Utils;

/**
 * Validator Class
 * Comprehensive input validation with sanitization
 */
class Validator
{
    private $data;
    private $rules;
    private $errors = [];
    private $customMessages = [];

    public function __construct($data = [])
    {
        $this->data = $data;
        $this->rules = [];
    }

    /**
     * Set data to validate
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Add validation rules
     */
    public function rules($rules)
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Set custom error messages
     */
    public function messages($messages)
    {
        $this->customMessages = $messages;
        return $this;
    }

    /**
     * Validate data against rules
     */
    public function validate()
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError()
    {
        return !empty($this->errors) ? reset($this->errors)[0] : null;
    }

    /**
     * Check if field has errors
     */
    public function hasError($field)
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Validate single field
     */
    private function validateField($field, $rules)
    {
        $value = $this->getValue($field);
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Apply single validation rule
     */
    private function applyRule($field, $value, $rule)
    {
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $ruleValue = $ruleParts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, $this->getMessage($field, 'required', 'The :field field is required.'));
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $this->getMessage($field, 'email', 'The :field field must be a valid email address.'));
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $ruleValue) {
                    $this->addError($field, $this->getMessage($field, 'min', "The :field field must be at least {$ruleValue} characters."));
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $ruleValue) {
                    $this->addError($field, $this->getMessage($field, 'max', "The :field field must not exceed {$ruleValue} characters."));
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, $this->getMessage($field, 'numeric', 'The :field field must be numeric.'));
                }
                break;

            case 'integer':
                if (!empty($value) && !is_int($value) && !ctype_digit($value)) {
                    $this->addError($field, $this->getMessage($field, 'integer', 'The :field field must be an integer.'));
                }
                break;

            case 'decimal':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, $this->getMessage($field, 'decimal', 'The :field field must be a decimal number.'));
                }
                break;

            case 'min_value':
                if (!empty($value) && is_numeric($value) && $value < $ruleValue) {
                    $this->addError($field, $this->getMessage($field, 'min_value', "The :field field must be at least {$ruleValue}."));
                }
                break;

            case 'max_value':
                if (!empty($value) && is_numeric($value) && $value > $ruleValue) {
                    $this->addError($field, $this->getMessage($field, 'max_value', "The :field field must not exceed {$ruleValue}."));
                }
                break;

            case 'in':
                $allowedValues = explode(',', $ruleValue);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->addError($field, $this->getMessage($field, 'in', "The :field field must be one of: " . implode(', ', $allowedValues)));
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($ruleValue, $value)) {
                    $this->addError($field, $this->getMessage($field, 'regex', 'The :field field format is invalid.'));
                }
                break;

            case 'phone':
                if (!empty($value) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $value)) {
                    $this->addError($field, $this->getMessage($field, 'phone', 'The :field field must be a valid phone number.'));
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $this->getMessage($field, 'url', 'The :field field must be a valid URL.'));
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, $this->getMessage($field, 'date', 'The :field field must be a valid date.'));
                }
                break;

            case 'image':
                if (!empty($value) && is_array($value) && isset($value['tmp_name'])) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = mime_content_type($value['tmp_name']);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $this->addError($field, $this->getMessage($field, 'image', 'The :field field must be a valid image file.'));
                    }
                }
                break;

            case 'file_size':
                if (!empty($value) && is_array($value) && isset($value['size'])) {
                    $maxSize = $ruleValue * 1024 * 1024; // Convert MB to bytes
                    if ($value['size'] > $maxSize) {
                        $this->addError($field, $this->getMessage($field, 'file_size', "The :field field must not exceed {$ruleValue}MB."));
                    }
                }
                break;

            case 'unique':
                // This would require database validation - implement as needed
                break;

            case 'exists':
                // This would require database validation - implement as needed
                break;
        }
    }

    /**
     * Get value from data array
     */
    private function getValue($field)
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Add error to field
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get error message
     */
    private function getMessage($field, $rule, $default)
    {
        $key = "{$field}.{$rule}";
        
        if (isset($this->customMessages[$key])) {
            return $this->customMessages[$key];
        }

        return str_replace(':field', $field, $default);
    }

    /**
     * Sanitize data
     */
    public function sanitize()
    {
        $sanitized = [];
        
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize array recursively
     */
    private function sanitizeArray($array)
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize single value
     */
    private function sanitizeValue($value)
    {
        if (is_string($value)) {
            // Remove null bytes
            $value = str_replace(chr(0), '', $value);
            
            // Trim whitespace
            $value = trim($value);
            
            // Convert special characters to HTML entities
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }

    /**
     * Static method for quick validation
     */
    public static function make($data, $rules, $messages = [])
    {
        $validator = new self($data);
        $validator->rules($rules);
        $validator->messages($messages);
        
        return $validator;
    }
}
