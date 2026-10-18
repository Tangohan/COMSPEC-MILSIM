<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Integrations\DiscordWebhookService;
use App\Support\DiscordWebhookCatalog;
use App\Services\Security\FileRateLimiter;
use App\Support\ReconNoteCatalog;

/**
 * Notes de reco des joueurs (app Reco du téléphone) postées dans un salon Discord, une carte par note :
 * type, observation, fiabilité, carroyage, auteur (indicatif et pseudo).
 *
 * Salon : choisi par chaque communauté dans Back-office > Intégrations > Relais Discord, « Notes de reco »
 * (salon commun ou salon à part ; désactivé par défaut). Sans salon, rien n'est envoyé.
 * Envoi après la réponse HTTP (le jeu n'attend pas Discord) ; au plus 30 notes par minute et par communauté.
 * Aucun Steam, e-mail ni adresse IP n'est posté ; mentions neutralisées.
 */
final class ReconNoteDiscordService
{
    private const PER_MINUTE = 30;

    /** @var \Closure(string, array<string, mixed>): array{ok:bool} */
    private \Closure $send;

    /**
     * @param (\Closure(string, array<string, mixed>): array{ok:bool})|null $send envoi (URL, embed) ; tests
     */
    public function __construct(
        private string $webhookUrl,
        ?\Closure $send = null,
        private ?FileRateLimiter $limiter = null
    ) {
        $this->send = $send ?? static fn (string $url, array $embed): array
            => (new DiscordWebhookService())->sendRawEmbed($url, $embed, 'Athena · Reco');
    }

    /**
     * Poste la note dans le salon de la communauté, après la réponse HTTP. Jamais bloquant, jamais d'exception.
     *
     * @param array<string, mixed> $note ligne recon_notes
     */
    public static function defer(int $tenantId, array $note): void
    {
        register_shutdown_function(static function () use ($tenantId, $note): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $url = (new DiscordEventRelayService())->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ATAK_RECON_NOTES);
                if ($url !== null) {
                    (new self($url))->post($note, $tenantId);
                }
            } catch (\Throwable $e) {
                error_log('[atak/recon-notes] discord ' . $e->getMessage());
            }
        });
    }

    /**
     * @param array<string, mixed> $note
     * @return string sent, throttled, disabled, failed
     */
    public function post(array $note, int $tenantId = 0): string
    {
        if ($this->webhookUrl === '') {
            return 'disabled';
        }
        $limiter = $this->limiter ?? new FileRateLimiter();
        if ($limiter->hit('atak_recon_note_discord_' . $tenantId, 60) > self::PER_MINUTE) {
            return 'throttled';
        }
        $res = ($this->send)($this->webhookUrl, self::buildEmbed($note));

        return ($res['ok'] ?? false) ? 'sent' : 'failed';
    }

    /**
     * @param array<string, mixed> $note
     * @return array<string, mixed>
     */
    public static function buildEmbed(array $note): array
    {
        $tag = (string) ($note['tag'] ?? '');
        $label = ReconNoteCatalog::tagLabel($tag);
        $text = self::clean((string) ($note['text'] ?? ''));
        $fields = [
            ['name' => 'Carroyage', 'value' => self::grid($note), 'inline' => true],
        ];
        $conf = ReconNoteCatalog::confidenceLabel((string) ($note['confidence'] ?? ''));
        if ($conf !== '') {
            $fields[] = ['name' => 'Fiabilité', 'value' => $conf, 'inline' => true];
        }
        $author = self::clean((string) ($note['author'] ?? ''));
        if ($author !== '') {
            $fields[] = ['name' => 'Auteur', 'value' => mb_substr($author, 0, 100), 'inline' => true];
        }

        return [
            'title' => '🔭 Reco · ' . $label,
            'description' => $text !== '' ? mb_substr($text, 0, ReconNoteCatalog::TEXT_MAX) : '_Sans observation écrite._',
            'color' => (int) hexdec(ltrim(ReconNoteCatalog::tagColor($tag), '#')),
            'fields' => $fields,
            'timestamp' => gmdate('c'),
        ];
    }

    /**
     * Carroyage 8 chiffres d'après la position (mètres) : 1533 1722 pour x = 15334, y = 17228.
     *
     * @param array<string, mixed> $note
     */
    public static function grid(array $note): string
    {
        $x = (float) ($note['pos_x'] ?? 0);
        $y = (float) ($note['pos_y'] ?? 0);
        if ($x <= 0 && $y <= 0) {
            return '—';
        }

        return sprintf('%04d %04d', (int) floor($x / 10), (int) floor($y / 10));
    }

    private static function clean(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        // Pas de mention @everyone / @here ni de lien cliquable venus du jeu.
        $s = str_replace(['@everyone', '@here'], ['@ everyone', '@ here'], $s);

        return preg_replace('~https?://\S+~i', '[lien]', $s) ?? $s;
    }
}
