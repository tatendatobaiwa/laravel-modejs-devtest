<?php

namespace App\Services;

use Illuminate\Support\Str;
use HTMLPurifier;
use HTMLPurifier_Config;

class InputSanitizationService
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $this->initializeHtmlPurifier();
    }

    /**
     * Initialize HTML Purifier for advanced sanitization.
     */
    private function initializeHtmlPurifier(): void
    {
        $config = HTMLPurifier_Config::createDefault();
        
        // Strict configuration - no HTML allowed
        $config->set('HTML.Allowed', '');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveSpansWithoutAttributes', true);
        $config->set('AutoFormat.Linkify', false);
        $config->set('HTML.Nofollow', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('URI.DisableResources', true);
        
        // Cache directory for better performance
        $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));
        
        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Sanitize text input by removing harmful content.
     */
    public function sanitizeText(?string $input, int $maxLength = null): ?string
    {
        if (empty($input)) {
            return null;
        }

        // Remove null bytes and control characters
        $input = $this->removeControlCharacters($input);
        
        // Use HTML Purifier for advanced sanitization
        $sanitized = $this->purifier->purify($input);
        
        // Additional cleaning
        $sanitized = $this->cleanWhitespace($sanitized);
        $sanitized = $this->removeInvisibleCharacters($sanitized);
        
        // Truncate if max length specified
        if ($maxLength && strlen($sanitized) > $maxLength) {
            $sanitized = Str::limit($sanitized, $maxLength, '');
        }

        return trim($sanitized) ?: null;
    }

    /**
     * Sanitize email input.
     */
    public function sanitizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        // Remove control characters and normalize
        $email = $this->removeControlCharacters($email);
        $email = strtolower(trim($email));
        
        // Remove any characters that shouldn't be in an email
        $email = preg_replace('/[^\w\.\-@+]/', '', $email);
        
        // Validate basic email structure
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * Sanitize numeric input.
     */
    public function sanitizeNumeric(?string $input, int $decimals = 2): ?float
    {
        if (empty($input)) {
            return null;
        }

        // Remove all non-numeric characters except decimal point and minus sign
        $cleaned = preg_replace('/[^\d\.\-]/', '', $input);
        
        // Handle negative numbers
        $isNegative = str_starts_with($cleaned, '-');
        $cleaned = ltrim($cleaned, '-');
        
        // Ensure only one decimal point
        $parts = explode('.', $cleaned);
        if (count($parts) > 2) {
            $cleaned = $parts[0] . '.' . $parts[1];
        }
        
        // Limit decimal places
        if (isset($parts[1]) && strlen($parts[1]) > $decimals) {
            $cleaned = $parts[0] . '.' . substr($parts[1], 0, $decimals);
        }

        $result = is_numeric($cleaned) ? (float) $cleaned : null;
        
        return $result !== null && $isNegative ? -$result : $result;
    }

    /**
     * Sanitize currency code input.
     */
    public function sanitizeCurrencyCode(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }

        // Remove non-alphabetic characters and convert to uppercase
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $code));
        
        // Ensure exactly 3 characters
        if (strlen($code) !== 3) {
            return null;
        }

        return $code;
    }

    /**
     * Sanitize name input (allows letters, spaces, hyphens, apostrophes).
     */
    public function sanitizeName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        // Remove control characters
        $name = $this->removeControlCharacters($name);
        
        // Allow only letters, spaces, hyphens, apostrophes, and dots
        $name = preg_replace('/[^\p{L}\s\-\'\.]/u', '', $name);
        
        // Clean up whitespace
        $name = $this->cleanWhitespace($name);
        
        // Capitalize properly
        $name = $this->capitalizeNames($name);

        return trim($name) ?: null;
    }

    /**
     * Sanitize phone number input.
     */
    public function sanitizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters except + for international prefix
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        
        // Ensure + is only at the beginning
        if (str_contains($phone, '+')) {
            $phone = '+' . str_replace('+', '', $phone);
        }

        return $phone ?: null;
    }

    /**
     * Sanitize URL input.
     */
    public function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Remove control characters
        $url = $this->removeControlCharacters($url);
        $url = trim($url);
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    /**
     * Sanitize file path to prevent directory traversal.
     */
    public function sanitizeFilePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Remove dangerous characters and sequences
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        $path = preg_replace('/[<>:"|?*]/', '', $path);
        $path = $this->removeControlCharacters($path);
        
        // Remove leading slashes and dots
        $path = ltrim($path, './\\');

        return $path ?: null;
    }

    /**
     * Sanitize array of inputs recursively.
     */
    public function sanitizeArray(array $data, array $rules = []): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeText($key, 100);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value, $rules[$key] ?? []);
            } else {
                $rule = $rules[$key] ?? 'text';
                $sanitized[$sanitizedKey] = $this->sanitizeByType($value, $rule);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize input based on specified type.
     */
    public function sanitizeByType($input, string $type)
    {
        return match ($type) {
            'email' => $this->sanitizeEmail($input),
            'numeric', 'number' => $this->sanitizeNumeric($input),
            'currency' => $this->sanitizeCurrencyCode($input),
            'name' => $this->sanitizeName($input),
            'phone' => $this->sanitizePhoneNumber($input),
            'url' => $this->sanitizeUrl($input),
            'filepath' => $this->sanitizeFilePath($input),
            'text', 'string' => $this->sanitizeText($input),
            default => $this->sanitizeText($input),
        };
    }

    /**
     * Remove control characters and null bytes.
     */
    private function removeControlCharacters(string $input): string
    {
        // Remove null bytes and other control characters
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    }

    /**
     * Clean up whitespace characters.
     */
    private function cleanWhitespace(string $input): string
    {
        // Replace multiple whitespace with single space
        $input = preg_replace('/\s+/', ' ', $input);
        
        // Remove zero-width characters
        $input = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $input);
        
        return trim($input);
    }

    /**
     * Remove invisible and zero-width characters.
     */
    private function removeInvisibleCharacters(string $input): string
    {
        // Remove various invisible Unicode characters
        $invisibleChars = [
            '\x{00A0}', // Non-breaking space
            '\x{1680}', // Ogham space mark
            '\x{180E}', // Mongolian vowel separator
            '\x{2000}', // En quad
            '\x{2001}', // Em quad
            '\x{2002}', // En space
            '\x{2003}', // Em space
            '\x{2004}', // Three-per-em space
            '\x{2005}', // Four-per-em space
            '\x{2006}', // Six-per-em space
            '\x{2007}', // Figure space
            '\x{2008}', // Punctuation space
            '\x{2009}', // Thin space
            '\x{200A}', // Hair space
            '\x{200B}', // Zero width space
            '\x{200C}', // Zero width non-joiner
            '\x{200D}', // Zero width joiner
            '\x{202F}', // Narrow no-break space
            '\x{205F}', // Medium mathematical space
            '\x{3000}', // Ideographic space
            '\x{FEFF}', // Zero width no-break space
        ];

        $pattern = '/[' . implode('', $invisibleChars) . ']/u';
        return preg_replace($pattern, '', $input);
    }

    /**
     * Properly capitalize names.
     */
    private function capitalizeNames(string $name): string
    {
        // Split by spaces and capitalize each part
        $parts = explode(' ', $name);
        $capitalized = [];

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // Handle hyphenated names
            if (str_contains($part, '-')) {
                $hyphenated = explode('-', $part);
                $part = implode('-', array_map('ucfirst', array_map('strtolower', $hyphenated)));
            } else {
                $part = ucfirst(strtolower($part));
            }

            $capitalized[] = $part;
        }

        return implode(' ', $capitalized);
    }

    /**
     * Validate that input doesn't contain suspicious patterns.
     */
    public function containsSuspiciousPatterns(string $input): bool
    {
        $suspiciousPatterns = [
            // SQL injection patterns
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            // XSS patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            // Command injection patterns
            '/(\||;|&|`|\$\(|\${)/i',
            // Path traversal
            '/\.\.[\/\\]/i',
            // PHP code injection
            '/<\?php/i',
            '/eval\s*\(/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log suspicious input for security monitoring.
     */
    public function logSuspiciousInput(string $input, string $context = ''): void
    {
        logger()->warning('Suspicious input detected', [
            'input' => Str::limit($input, 200),
            'context' => $context,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ]);
    }
}