<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $rules = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleList) {
            $rules = is_array($ruleList) ? $ruleList : explode('|', $ruleList);
            $value = $this->data[$field] ?? null;
            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                $method = 'validate' . str_replace(' ', '', ucwords(str_replace('_', ' ', $rule)));
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $value, $params)) {
                        break;
                    }
                }
            }
        }
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validateRequired(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            $this->errors[$field][] = 'Le champ est requis.';
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'Adresse email invalide.';
            return false;
        }
        return true;
    }

    private function validateMin(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $min = (int) ($params[0] ?? 0);
        if (strlen((string) $value) < $min) {
            $this->errors[$field][] = "Minimum {$min} caractères.";
            return false;
        }
        return true;
    }

    private function validateMax(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $max = (int) ($params[0] ?? 255);
        if (strlen((string) $value) > $max) {
            $this->errors[$field][] = "Maximum {$max} caractères.";
            return false;
        }
        return true;
    }
}
