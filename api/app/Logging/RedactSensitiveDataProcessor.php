<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Patterns to match and redact sensitive data
     */
    private array $patterns = [
        // Email addresses
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[REDACTED_EMAIL]',
        
        // 10-digit phone numbers (Indian format)
        '/\b[6-9]\d{9}\b/' => '[REDACTED_PHONE]',
        
        // 12-digit phone numbers (with country code)
        '/\b91[6-9]\d{9}\b/' => '[REDACTED_PHONE]',
        
        // API tokens and keys
        '/\b[A-Za-z0-9]{32,}\b/' => '[REDACTED_TOKEN]',
        
        // Credit card numbers (basic pattern)
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/' => '[REDACTED_CARD]',
        
        // Aadhaar numbers (12 digits)
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/' => '[REDACTED_AADHAAR]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $record->message;
        $context = $record->context;

        // Redact sensitive data in message
        $message = $this->redactSensitiveData($message);

        // Redact sensitive data in context
        $context = $this->redactContextData($context);

        return $record->with(message: $message, context: $context);
    }

    private function redactSensitiveData(string $text): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    private function redactContextData(array $context): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'api_key', 'secret',
            'email', 'phone', 'mobile', 'contact', 'address', 'aadhaar',
            'credit_card', 'card_number', 'ssn', 'pan', 'gstin'
        ];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                $context[$key] = $this->redactSensitiveData($value);
            } elseif (is_array($value)) {
                $context[$key] = $this->redactContextData($value);
            }
        }

        return $context;
    }
}
