<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\PaymentLedgerRepository;
use App\Repositories\PlatformSettingsRepository;

/**
 * Bandeau de don affiché sur tout le site (réglage Admin > Paiements > Dons > Bandeau de don).
 *
 * Réglages plateforme (table platform_settings, clés donation_banner.*) : activé, message FR / EN, objectif en
 * euros, dates de début et de fin facultatives, numéro de version (augmenté à chaque enregistrement, pour que le
 * bandeau fermé par un visiteur réapparaisse quand le message change).
 *
 * Progression : dons payés et non remboursés (soutiens du cœur, dons par carte, dons saisis par l'administration)
 * depuis la date de début, sinon depuis le 1er du mois en cours, jusqu'à la date de fin. Les formules payées ne
 * comptent pas. Réglages et montant gardés 5 minutes dans storage/cache/donation_banner.json : aucune requête
 * par page vue.
 */
final class DonationBanner
{
    public const KEY_ENABLED = 'donation_banner.enabled';
    public const KEY_MESSAGE_FR = 'donation_banner.message_fr';
    public const KEY_MESSAGE_EN = 'donation_banner.message_en';
    public const KEY_GOAL_EUR = 'donation_banner.goal_eur';
    public const KEY_STARTS_ON = 'donation_banner.starts_on';
    public const KEY_ENDS_ON = 'donation_banner.ends_on';
    public const KEY_VERSION = 'donation_banner.version';
    public const KEY_STYLE = 'donation_banner.style';

    /** Toutes les clés du réglage, dans l'ordre de lecture. */
    public const KEYS = [self::KEY_ENABLED, self::KEY_MESSAGE_FR, self::KEY_MESSAGE_EN, self::KEY_GOAL_EUR, self::KEY_STARTS_ON, self::KEY_ENDS_ON, self::KEY_VERSION, self::KEY_STYLE];

    /** Message d'exemple montré dans l'aperçu tant qu'aucun n'est écrit, et enregistré tel quel si l'on active sans rien écrire. */
    public const DEFAULT_MESSAGE_FR = 'Athena est gratuite et faite par des joueurs. Un petit don garde les serveurs allumés.';
    public const DEFAULT_MESSAGE_EN = 'Athena is free and built by players. A small donation keeps the servers running.';

    /**
     * Styles du bandeau (clé => [nom, description]), choisis dans Admin > Paiements > Dons.
     * Terrain : photo de nuit assombrie, titre display, jauge. Rouge : même chose en palette rouge. Cœurs : cœurs
     * qui montent doucement derrière le texte (immobiles si l'animation est réduite).
     */
    public const STYLES = [
        'terrain' => ['Terrain', 'Photo de nuit assombrie, grand titre, jauge de l’objectif. Le plus visible.'],
        'terrain_rouge' => ['Terrain rouge', 'Le style Terrain en rouge : plus pressant, pour une fin d’objectif.'],
        'terrain_coeurs' => ['Terrain cœurs', 'Le style Terrain avec des petits cœurs qui montent doucement.'],
        'terrain_rouge_coeurs' => ['Terrain rouge cœurs', 'Rouge et cœurs réunis : idéal pour un appel du cœur.'],
        'indicatif' => ['Indicatif', 'Bande fine façon statut de serveur. Discrète mais vivante.'],
        'emeraude' => ['Émeraude', 'Montants en un clic (5, 10, 25 €) : moins d’étapes avant le don.'],
        'portail' => ['Portail clair', 'Ton sobre, clair le jour et sombre la nuit. Se fond dans le portail.'],
        'compact' => ['Compact', 'Une seule ligne de 40 px. Le plus discret, idéal sur mobile.'],
    ];
    public const DEFAULT_STYLE = 'terrain';

    public const MESSAGE_MAX = 180;
    public const GOAL_MAX_EUR = 1000000;
    public const CACHE_TTL_SEC = 300;

    /** Sources du registre des paiements qui sont des dons. */
    private const DONATION_SOURCES = ['heart', 'stripe_donation', 'donation'];

    /**
     * Premier segment d'adresse où le bandeau ne s'affiche jamais :
     * administration et gestion (outils de travail), connexion / inscription / mot de passe (parcours à ne pas
     * distraire), paiement et la page de dons elle-même (déjà le bon endroit), ATAK web et outils plein écran du
     * terrain (chaque pixel compte), points techniques.
     */
    public const HIDDEN_PREFIXES = [
        'admin', 'back-office', 'backoffice', 'formation',
        'login', 'register', 'logout', 'reset-password', 'forgot-password', 'verify-email', 'two-factor', '2fa',
        'paiement', 'soutenir-atak', 'checkout', 'billing',
        'atak', 'overwatch', 'tacmap', 'tactical', 'telecommande-drone', 'tableau-operationnel', 'salle-de-guerre',
        'operateur', 'map-photo', 'map-data', 'terrain', 'jnet', 'renseignement-sse',
        'api', 'connect', 'lier', 'demo',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $state = null;

    /**
     * Réglages lus depuis les paires clé => valeur (pur).
     *
     * @param array<string, string> $raw
     * @return array{enabled: bool, message_fr: string, message_en: string, goal_eur: int, starts_on: string, ends_on: string, version: int, style: string}
     */
    public static function configFrom(array $raw): array
    {
        $flag = strtolower(trim((string) ($raw[self::KEY_ENABLED] ?? '0')));

        return [
            'enabled' => in_array($flag, ['1', 'true', 'yes', 'on'], true),
            'message_fr' => self::cleanMessage((string) ($raw[self::KEY_MESSAGE_FR] ?? '')),
            'message_en' => self::cleanMessage((string) ($raw[self::KEY_MESSAGE_EN] ?? '')),
            'goal_eur' => max(0, min(self::GOAL_MAX_EUR, (int) ($raw[self::KEY_GOAL_EUR] ?? 0))),
            'starts_on' => self::cleanDate((string) ($raw[self::KEY_STARTS_ON] ?? '')) ?? '',
            'ends_on' => self::cleanDate((string) ($raw[self::KEY_ENDS_ON] ?? '')) ?? '',
            'version' => max(0, (int) ($raw[self::KEY_VERSION] ?? 0)),
            'style' => self::cleanStyle((string) ($raw[self::KEY_STYLE] ?? '')),
        ];
    }

    /** Style connu, sinon le style par défaut. */
    public static function cleanStyle(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return isset(self::STYLES[$raw]) ? $raw : self::DEFAULT_STYLE;
    }

    /** Message nettoyé : une ligne, sans balises ni caractères de contrôle, 180 caractères. */
    public static function cleanMessage(string $raw): string
    {
        $v = strip_tags($raw);
        $v = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v);
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));

        return mb_substr($v, 0, self::MESSAGE_MAX);
    }

    /** Date AAAA-MM-JJ valide, '' pour vide, null si illisible. */
    public static function cleanDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $d !== false && $d->format('Y-m-d') === $raw ? $raw : null;
    }

    /**
     * Le bandeau est-il en ligne à cette date (pur) ? Activé, et dans la période si des dates sont posées.
     *
     * @param array{enabled: bool, starts_on: string, ends_on: string} $cfg
     */
    public static function isLive(array $cfg, ?int $now = null): bool
    {
        if (!$cfg['enabled']) {
            return false;
        }
        $today = date('Y-m-d', $now ?? time());
        if ($cfg['starts_on'] !== '' && $today < $cfg['starts_on']) {
            return false;
        }

        return !($cfg['ends_on'] !== '' && $today > $cfg['ends_on']);
    }

    /** Adresse où le bandeau ne doit pas apparaître (pur). */
    public static function hiddenOnPath(string $path): bool
    {
        $path = trim(strtolower($path), '/');
        foreach (['public/', 'index.php/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }
        if (in_array($path, ['public', 'index.php'], true)) {
            $path = '';
        }
        $first = explode('/', $path, 2)[0];

        return in_array($first, self::HIDDEN_PREFIXES, true);
    }

    /**
     * Période comptée pour la progression : [début inclus, fin incluse] au format AAAA-MM-JJ.
     *
     * @param array{starts_on: string, ends_on: string} $cfg
     * @return array{0: string, 1: string}
     */
    public static function countedPeriod(array $cfg, ?int $now = null): array
    {
        $from = $cfg['starts_on'] !== '' ? $cfg['starts_on'] : date('Y-m-01', $now ?? time());

        return [$from, $cfg['ends_on']];
    }

    /**
     * Dons reçus sur la période (pur), en centimes.
     *
     * @param list<array<string, mixed>> $ledger PaymentLedgerRepository::ledger()
     */
    public static function raisedFromLedger(array $ledger, string $from, string $to): int
    {
        $sum = 0;
        foreach ($ledger as $row) {
            if (!in_array((string) ($row['source'] ?? ''), self::DONATION_SOURCES, true)) {
                continue;
            }
            if ((string) ($row['status'] ?? '') !== 'activated' || !empty($row['refunded_at'])) {
                continue;
            }
            $day = substr((string) ($row['paid_at'] ?? ''), 0, 10);
            if ($day === '' || $day < $from || ($to !== '' && $day > $to)) {
                continue;
            }
            $sum += max(0, (int) ($row['amount_cents'] ?? 0));
        }

        return $sum;
    }

    /**
     * Ce que le bandeau affiche (pur) : null s'il est hors ligne.
     *
     * @param array{enabled: bool, message_fr: string, message_en: string, goal_eur: int, starts_on: string, ends_on: string, version: int, style?: string} $cfg
     * @return array{message: string, goal_cents: int, raised_cents: int, percent: int, key: string, style: string}|null
     */
    public static function view(array $cfg, int $raisedCents, string $locale, ?int $now = null): ?array
    {
        if (!self::isLive($cfg, $now)) {
            return null;
        }
        $en = str_starts_with(strtolower($locale), 'en');
        $message = $en ? ($cfg['message_en'] ?: $cfg['message_fr']) : ($cfg['message_fr'] ?: $cfg['message_en']);
        $goal = $cfg['goal_eur'] * 100;
        $percent = $goal > 0 ? (int) min(100, floor($raisedCents * 100 / $goal)) : 0;

        return [
            'message' => $message,
            'goal_cents' => $goal,
            'raised_cents' => $goal > 0 ? max(0, $raisedCents) : 0,
            'percent' => $percent,
            'key' => 'v' . $cfg['version'] . '-' . substr(md5($cfg['message_fr'] . '|' . $cfg['message_en'] . '|' . $cfg['goal_eur']), 0, 8),
            'style' => self::cleanStyle((string) ($cfg['style'] ?? '')),
        ];
    }

    // ------------------------------------------------------------------ lecture et enregistrement

    private static function cacheFile(): string
    {
        return base_path('storage/cache/donation_banner.json');
    }

    /**
     * Réglages et montant collecté (cache fichier de 5 minutes).
     *
     * @return array{config: array{enabled: bool, message_fr: string, message_en: string, goal_eur: int, starts_on: string, ends_on: string, version: int, style: string}, raised_cents: int}
     */
    public static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }
        $file = self::cacheFile();
        clearstatcache(true, $file);
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime > time() - self::CACHE_TTL_SEC) {
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data) && is_array($data['raw'] ?? null)) {
                return self::$state = [
                    'config' => self::configFrom(array_map('strval', $data['raw'])),
                    'raised_cents' => (int) ($data['raised_cents'] ?? 0),
                ];
            }
        }
        $raw = self::readSettings();
        $cfg = self::configFrom($raw);
        $raised = 0;
        if ($cfg['enabled'] && $cfg['goal_eur'] > 0) {
            try {
                [$from, $to] = self::countedPeriod($cfg);
                $raised = self::raisedFromLedger((new PaymentLedgerRepository())->ledger(), $from, $to);
            } catch (\Throwable) {
                $raised = 0;
            }
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, (string) json_encode(['raw' => $raw, 'raised_cents' => $raised, 'at' => gmdate('c')]), LOCK_EX);

        return self::$state = ['config' => $cfg, 'raised_cents' => $raised];
    }

    /** @return array<string, string> */
    private static function readSettings(): array
    {
        try {
            $all = (new PlatformSettingsRepository())->listAll();
        } catch (\Throwable) {
            return [];
        }

        return array_filter($all, static fn (string $k): bool => str_starts_with($k, 'donation_banner.'), ARRAY_FILTER_USE_KEY);
    }

    /** Bandeau à afficher pour cette adresse et cette langue, ou null. */
    /** Pages d'accueil des espaces membre et back-office : bandeau affiché en tête de contenu. */
    public const SHELL_HOME_PATHS = ['dashboard', 'back-office'];

    public static function isShellHomePath(string $path): bool
    {
        $path = trim(strtolower($path), '/');
        foreach (['public/', 'index.php/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }

        return in_array($path, self::SHELL_HOME_PATHS, true);
    }

    public static function forRequest(string $path, string $locale, bool $shellHome = false): ?array
    {
        if ($shellHome ? !self::isShellHomePath($path) : self::hiddenOnPath($path)) {
            return null;
        }
        try {
            $state = self::state();
        } catch (\Throwable) {
            return null;
        }

        return self::view($state['config'], $state['raised_cents'], $locale);
    }

    public static function forget(): void
    {
        self::$state = null;
        @unlink(self::cacheFile());
    }

    /**
     * Valeurs saisies dans le formulaire d'administration → paires à enregistrer, ou message d'erreur.
     *
     * @param array<string, mixed> $input
     * @return array{pairs: array<string, string>, error: ?string}
     */
    public static function pairsFromForm(array $input, int $previousVersion): array
    {
        $starts = self::cleanDate((string) ($input['starts_on'] ?? ''));
        $ends = self::cleanDate((string) ($input['ends_on'] ?? ''));
        if ($starts === null || $ends === null) {
            return ['pairs' => [], 'error' => 'Date illisible : choisissez-la dans le calendrier, ou laissez le champ vide.'];
        }
        if ($starts !== '' && $ends !== '' && $ends < $starts) {
            return ['pairs' => [], 'error' => 'La date de fin doit suivre la date de début.'];
        }
        $goalRaw = trim((string) ($input['goal_eur'] ?? ''));
        if ($goalRaw !== '' && (preg_match('/^\d{1,7}$/', $goalRaw) !== 1 || (int) $goalRaw > self::GOAL_MAX_EUR)) {
            return ['pairs' => [], 'error' => 'Objectif illisible : indiquez un montant entier en euros (par exemple 300), ou laissez vide.'];
        }
        $enabled = (string) ($input['enabled'] ?? '') === '1';
        $fr = self::cleanMessage((string) ($input['message_fr'] ?? ''));
        $en = self::cleanMessage((string) ($input['message_en'] ?? ''));
        if ($enabled && $fr === '' && $en === '') {
            // L'aperçu montre le message d'exemple tant que les champs sont vides : c'est lui qu'on enregistre.
            $fr = self::DEFAULT_MESSAGE_FR;
            $en = self::DEFAULT_MESSAGE_EN;
        }

        return ['pairs' => [
            self::KEY_ENABLED => $enabled ? '1' : '0',
            self::KEY_MESSAGE_FR => $fr,
            self::KEY_MESSAGE_EN => $en,
            self::KEY_GOAL_EUR => (string) (int) $goalRaw,
            self::KEY_STARTS_ON => $starts,
            self::KEY_ENDS_ON => $ends,
            self::KEY_VERSION => (string) ($previousVersion + 1),
            self::KEY_STYLE => self::cleanStyle((string) ($input['style'] ?? '')),
        ], 'error' => null];
    }

    /**
     * État vu par l'administration (pur) : live (affiché), scheduled (activé, période à venir), ended (période
     * passée) ou disabled.
     *
     * @param array{enabled: bool, starts_on: string, ends_on: string} $cfg
     */
    public static function status(array $cfg, ?int $now = null): string
    {
        if (!$cfg['enabled']) {
            return 'disabled';
        }
        $today = date('Y-m-d', $now ?? time());
        if ($cfg['starts_on'] !== '' && $today < $cfg['starts_on']) {
            return 'scheduled';
        }

        return ($cfg['ends_on'] !== '' && $today > $cfg['ends_on']) ? 'ended' : 'live';
    }

    /**
     * Données de la section d'administration (pur) : réglages, état, jauge, aperçu FR / EN tel que vu par les
     * visiteurs (rendu même hors période, pour juger le message avant de l'activer).
     *
     * @param array<string, string> $raw
     * @return array{config: array{enabled: bool, message_fr: string, message_en: string, goal_eur: int, starts_on: string, ends_on: string, version: int, style: string}, status: string, raised_cents: int, goal_cents: int, percent: int, period: string, preview: array{fr: ?array{message: string, goal_cents: int, raised_cents: int, percent: int, key: string, style: string}, en: ?array{message: string, goal_cents: int, raised_cents: int, percent: int, key: string, style: string}}}
     */
    public static function adminState(array $raw, int $raisedCents, ?int $now = null): array
    {
        $cfg = self::configFrom($raw);
        [$from, $to] = self::countedPeriod($cfg, $now);
        $fmt = static fn (string $d): string => date('d/m/Y', (int) strtotime($d));
        $previewCfg = ['enabled' => true, 'starts_on' => '', 'ends_on' => ''] + $cfg;
        $hasMessage = $cfg['message_fr'] !== '' || $cfg['message_en'] !== '';
        $goal = $cfg['goal_eur'] * 100;

        return [
            'config' => $cfg,
            'status' => self::status($cfg, $now),
            'raised_cents' => max(0, $raisedCents),
            'goal_cents' => $goal,
            'percent' => $goal > 0 ? (int) min(100, floor(max(0, $raisedCents) * 100 / $goal)) : 0,
            'period' => 'depuis le ' . $fmt($from) . ($to !== '' ? ' jusqu’au ' . $fmt($to) : ''),
            'preview' => [
                'fr' => $hasMessage ? self::view($previewCfg, $raisedCents, 'fr', $now) : null,
                'en' => $hasMessage ? self::view($previewCfg, $raisedCents, 'en', $now) : null,
            ],
        ];
    }

    /**
     * Section d'administration lue en base (réglages sans cache, dons réels du registre).
     *
     * @return array<string, mixed>
     */
    public static function adminSnapshot(PlatformSettingsRepository $repo): array
    {
        $raw = [];
        foreach (self::KEYS as $key) {
            $raw[$key] = (string) $repo->get($key, '');
        }
        $cfg = self::configFrom($raw);
        $raised = 0;
        if ($cfg['goal_eur'] > 0) {
            try {
                [$from, $to] = self::countedPeriod($cfg);
                $raised = self::raisedFromLedger((new PaymentLedgerRepository())->ledger(), $from, $to);
            } catch (\Throwable) {
            }
        }

        return self::adminState($raw, $raised);
    }

    /** Montant lisible (« 1 250 € »), sans centimes quand ils sont nuls. */
    public static function euros(int $cents, string $locale = 'fr'): string
    {
        $en = str_starts_with(strtolower($locale), 'en');
        $value = $cents / 100;
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return $en
            ? '€' . number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', "\u{202F}") . "\u{00A0}€";
    }
}
