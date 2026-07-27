<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\TenantRepository;
use ZipArchive;

/**
 * Publie sur Discord (webhook communauté) une annonce de mise à jour du pack Overwatch,
 * avec extrait de journal des changements si disponible.
 */
final class ModUpdateDiscordNotifier
{
    private const EMBED_COLOR = 0x059669;

    public function __construct(
        private ?DiscordWebhookService $discord = null,
        private ?TenantRepository $tenants = null,
    ) {
        $this->discord ??= new DiscordWebhookService();
        $this->tenants ??= new TenantRepository();
    }

    public function hasWebhookConfigured(int $tenantId): bool
    {
        return $this->resolveWebhookUrl($tenantId) !== null;
    }

    /**
     * Best-effort : n'échoue jamais l'upload du pack.
     *
     * @return array{sent:bool, skipped:bool, error?:string}
     */
    public function notifyModUpdate(
        int $tenantId,
        ?string $version,
        string $downloadUrl,
        ?string $changelogNotes = null,
        ?string $zipPath = null,
    ): array {
        $webhookUrl = $this->resolveWebhookUrl($tenantId);
        if ($webhookUrl === null) {
            return ['sent' => false, 'skipped' => true];
        }

        $versionLabel = $version !== null && trim($version) !== '' ? trim($version) : null;
        $title = $versionLabel !== null
            ? 'Pack Overwatch ' . $versionLabel
            : 'Nouvelle version du pack Overwatch';

        $notes = trim((string) $changelogNotes);
        if ($notes === '' && $zipPath !== null && is_file($zipPath)) {
            $notes = (string) $this->extractChangelogExcerpt($zipPath, $versionLabel);
        }
        $notes = $this->formatChangelogForDiscord($notes);

        $description = $versionLabel !== null
            ? 'Une nouvelle version du pack Overwatch (**' . $versionLabel . '**) est disponible pour la communauté.'
            : 'Une nouvelle version du pack Overwatch est disponible pour la communauté.';

        if ($notes !== '') {
            $description .= "\n\n**Journal des changements**\n" . $notes;
        }

        $embed = [
            'title' => $title,
            'description' => $description,
            'url' => $downloadUrl,
            'color' => self::EMBED_COLOR,
            'fields' => [
                [
                    'name' => 'Téléchargement',
                    'value' => '[Ouvrir la page membre](' . $downloadUrl . ')',
                    'inline' => false,
                ],
            ],
            'footer' => ['text' => 'Athena — mise à jour automatique'],
        ];

        try {
            $result = $this->discord->sendEmbed(
                $webhookUrl,
                $embed,
                null,
                'Athena · Overwatch'
            );
            if (!($result['ok'] ?? false)) {
                return [
                    'sent' => false,
                    'skipped' => false,
                    'error' => (string) ($result['error'] ?? 'Envoi Discord impossible.'),
                ];
            }

            return ['sent' => true, 'skipped' => false];
        } catch (\Throwable) {
            return ['sent' => false, 'skipped' => false, 'error' => 'Envoi Discord impossible.'];
        }
    }

    private function resolveWebhookUrl(int $tenantId): ?string
    {
        if ($tenantId <= 0) {
            return null;
        }
        $url = trim((string) ($this->tenants->getSettings($tenantId)['integrations']['discord_webhook_url'] ?? ''));
        if ($url === '' || !$this->discord->isValidWebhookUrl($url)) {
            return null;
        }

        return $url;
    }

    /**
     * Lit CHANGELOG.md (ou STEAM_CHANGELOG.txt) dans le ZIP et renvoie la section de la version,
     * ou la première section si la version n'est pas trouvée.
     */
    public function extractChangelogExcerpt(string $zipPath, ?string $version = null): ?string
    {
        if (!class_exists(ZipArchive::class) || !is_file($zipPath)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }

        $raw = null;
        $preferred = [
            'changelog.md',
            'steam_changelog.txt',
            'changelog.txt',
        ];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            $base = strtolower(basename($norm));
            if (!in_array($base, $preferred, true)) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if (is_string($content) && trim($content) !== '') {
                $raw = $content;
                if ($base === 'changelog.md') {
                    break;
                }
            }
        }
        $zip->close();

        if ($raw === null) {
            return null;
        }

        return $this->pickVersionSection($raw, $version);
    }

    private function pickVersionSection(string $markdown, ?string $version): ?string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $sections = preg_split('/(?=^##\s+)/m', $markdown) ?: [];
        $versionSections = [];
        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '' || !str_starts_with($section, '##')) {
                continue;
            }
            $versionSections[] = $section;
        }
        if ($versionSections === []) {
            $plain = trim(preg_replace('/^#\s+.+$/m', '', $markdown) ?? $markdown);

            return $plain !== '' ? $plain : null;
        }

        if ($version !== null && $version !== '') {
            $needle = preg_quote($version, '/');
            foreach ($versionSections as $section) {
                if (preg_match('/^##\s+[^\n]*' . $needle . '/i', $section) === 1) {
                    return $section;
                }
            }
        }

        return $versionSections[0];
    }

    private function formatChangelogForDiscord(string $notes): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return '';
        }
        // Titre de section markdown → gras Discord
        $notes = preg_replace('/^##\s+(.+)$/m', '**$1**', $notes) ?? $notes;
        $notes = preg_replace('/^###\s+(.+)$/m', '*$1*', $notes) ?? $notes;
        // Limite pour rester dans la description d'embed
        if (mb_strlen($notes) > 2800) {
            $notes = mb_substr($notes, 0, 2790) . '…';
        }

        return $notes;
    }
}
