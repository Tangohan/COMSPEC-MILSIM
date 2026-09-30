<?php

if (!function_exists('adv_h')) {
    function adv_h(mixed $v): string
    {
        return htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
    }

    function adv_person(array $row): string
    {
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['callsign'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($row['email'] ?? 'Personnel'));
        }

        return $name;
    }

    function adv_option(array $row): string
    {
        $name = adv_person($row);
        $cs = trim((string) ($row['callsign'] ?? ''));
        if ($cs !== '' && strcasecmp($cs, $name) !== 0) {
            $name .= ' · ' . $cs;
        }

        return $name;
    }

    function adv_info(string $title, string $body): string
    {
        return '<span class="adv-info" tabindex="0">'
            . '<span class="adv-info__i" aria-hidden="true">i</span>'
            . '<span class="adv-info__bubble" role="tooltip"><strong>' . adv_h($title) . '</strong>' . adv_h($body) . '</span>'
            . '</span>';
    }
}
