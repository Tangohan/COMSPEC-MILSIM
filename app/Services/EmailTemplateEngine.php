<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Templates PHP sous views/emails/{name}.php qui retournent ['html' => string, 'text' => string].
 */
final class EmailTemplateEngine
{
    /**
     * @param array<string, mixed> $vars
     * @return array{html: string, text: string}
     */
    public function render(string $template, array $vars): array
    {
        $path = \base_path('views/emails/' . $template . '.php');
        if (!is_file($path)) {
            return ['html' => '', 'text' => ''];
        }
        extract($vars, EXTR_SKIP);
        /** @var array{html: string, text: string}|mixed $out */
        $out = include $path;
        if (!is_array($out) || !isset($out['html'], $out['text'])) {
            return ['html' => '', 'text' => ''];
        }

        return ['html' => (string) $out['html'], 'text' => (string) $out['text']];
    }
}
