<?php

declare(strict_types=1);

namespace ContactForm\Contact;

final class ContactValidator
{
    private const array SINGLE_LINE_LIMITS = ['name' => 100, 'email' => 254, 'phone' => 32, 'address' => 200];
    private const array MULTI_LINE_LIMITS = ['profession' => 500, 'field_one' => 1000, 'field_two' => 1000, 'field_three' => 1000, 'message' => 5000];
    private const array REQUIRED = ['name', 'email', 'phone'];
    private const string SINGLE_LINE_FORBIDDEN = '/[\p{Cc}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
    private const string MULTI_LINE_FORBIDDEN = '/[^\P{Cc}\t\r\n]|[\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
    private const string PHONE_PATTERN = '/\A\+?[0-9][0-9 ().-]{5,30}\z/';

    public function validate(array $input): ValidationResult
    {
        $values = [];
        $errors = [];
        foreach (self::SINGLE_LINE_LIMITS as $field => $limit) {
            [$values[$field], $error] = $this->clean($input[$field] ?? '', $limit, self::SINGLE_LINE_FORBIDDEN);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }
        foreach (self::MULTI_LINE_LIMITS as $field => $limit) {
            [$values[$field], $error] = $this->clean($input[$field] ?? '', $limit, self::MULTI_LINE_FORBIDDEN);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }
        foreach (self::REQUIRED as $field) {
            if ($values[$field] === '' && !isset($errors[$field])) {
                $errors[$field] = 'This field is required.';
            }
        }
        if (!isset($errors['email']) && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!isset($errors['phone']) && preg_match(self::PHONE_PATTERN, $values['phone']) !== 1) {
            $errors['phone'] = 'Enter a valid phone number.';
        }

        return new ValidationResult(
            $errors === [] ? ContactSubmission::fromArray($values) : null,
            $errors,
            $values,
        );
    }

    private function clean(mixed $value, int $limit, string $forbidden): array
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return ['', 'This value is not valid.'];
        }
        $trimmed = trim($value);
        if (preg_match($forbidden, $trimmed) === 1) {
            return ['', 'This value contains characters that are not allowed.'];
        }
        if (mb_strlen($trimmed, 'UTF-8') > $limit) {
            return [mb_substr($trimmed, 0, $limit, 'UTF-8'), sprintf('Use at most %d characters.', $limit)];
        }

        return [$trimmed, null];
    }
}
