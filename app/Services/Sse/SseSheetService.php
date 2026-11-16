<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseSheetRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseAuditLogRepository;
use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseWantedCodes;
use App\Support\SseSheetCatalog as D;
use App\Support\SseSheetText as T;
use App\Support\SseRedactionExplain;
use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog as C;

/**
 * Fiches PDF d'une personne ou d'une identité fictive : prépare les textes (trames par défaut, variables résolues selon
 * l'habilitation, assainissement, contrôle des fuites), génère l'aperçu, émet (référence, journal, historique) et ré-édite un
 * document déjà émis depuis le texte réellement imprimé. Émettre ou prévisualiser ne modifie jamais la fiche.
 */
final class SseSheetService
{
    /** Plafond de débit : émissions par utilisateur sur la fenêtre. */
    public const ISSUE_LIMIT = 20;

    public const ISSUE_WINDOW_MINUTES = 10;

    /** Aperçus par minute et par session. */
    public const PREVIEW_LIMIT = 30;

    public function __construct(
        private ?SseSheetFactsService $facts = null,
        private ?SseSheetRepository $repo = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseSheetPdf $template = null,
        private ?SseSheetVisibility $visibility = null,
    ) {
        $this->facts ??= new SseSheetFactsService();
        $this->repo ??= new SseSheetRepository();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->template ??= new SseSheetPdf();
        $this->visibility ??= new SseSheetVisibility();
    }

    /** Niveaux de diffusion proposés au rédacteur : du plus large à son habilitation (jamais au-dessus). @return list<string> */
    public static function levelsFor(SseSheetAccess $access): array
    {
        $own = SseRedactionService::levelRank($access->level());

        return array_values(array_filter(array_keys(SseRedactionService::LEVELS), static fn (string $l): bool => SseRedactionService::levelRank($l) <= $own));
    }

    /**
     * Saisie de la génération rapide : aucun texte à remplir (trames par défaut du modèle, dans la langue du lecteur), classification
     * par défaut de la communauté ramenée à l'habilitation du rédacteur. `quick` : le PDF part même sans l'étape « fiches PDF ».
     *
     * @return array<string, mixed>
     */
    public function quickInput(int $tenantId, SseSheetAccess $access, string $lang): array
    {
        $config = $this->settings->get($tenantId);

        return ['lang' => D::lang($lang), 'classification' => self::clampLevel($config['default_classification'] ?? '', $access), 'faces' => 'auto', 'quick' => true];
    }

    /** Niveau demandé, ramené à l'habilitation du rédacteur. */
    public static function clampLevel(mixed $requested, SseSheetAccess $access): string
    {
        $r = is_string($requested) ? $requested : '';
        if (!isset(SseRedactionService::LEVELS[$r]) || SseRedactionService::levelRank($r) > SseRedactionService::levelRank($access->level())) {
            return isset(SseRedactionService::LEVELS[$access->level()]) ? $access->level() : SseCaseRepository::CLASS_INTERNAL;
        }

        return $r;
    }

    /** @return array{0: string, 1: string} libellés français et anglais d'un niveau de diffusion */
    public static function levelLabels(string $level): array
    {
        return [
            SseCaseRepository::CLASSIFICATION_LABELS[$level] ?? $level,
            isset(SseUsClassification::SCALE[$level]) ? SseUsClassification::label($level, 'en') : $level,
        ];
    }

    /**
     * Textes par défaut du modèle, déjà résolus selon le niveau de diffusion (une variable de donnée masquée donne la barre).
     *
     * @param array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string} $subject
     * @return array<string, string>
     */
    public function defaults(int $tenantId, array $subject, string $model, string $lang, string $level, SseSheetAccess $access, array $override = []): array
    {
        $lang = D::lang($lang);
        $raw = $this->facts->raw($tenantId, $subject, $access, $lang);

        return $this->defaultTexts($tenantId, $model, $lang, $raw, $level, 'APERCU', $this->visibility->resolve($tenantId, $model, $override));
    }

    /** Niveaux requis par catégorie pour un document (modèle de la communauté puis surcharge d'émission, jamais sous le plancher). @return array<string, string> */
    public function visibilityFor(int $tenantId, string $model, array $input = []): array
    {
        return $this->visibility->resolve($tenantId, $model, is_array($input['visibility'] ?? null) ? $input['visibility'] : []);
    }

    /**
     * Prépare une fiche : textes assainis et résolus, données caviardées, contrôles de longueur et de fuite. Rien n'est écrit.
     *
     * @param array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string} $subject
     * @param array<string, mixed> $input lang, classification, faces, validity, threat, armed, checks (liste de références), texts (clé => texte)
     * @return array{ok: bool, errors: list<string>, data: ?array<string, mixed>, texts: array<string, string>, level: string, lang: string, faces: string, sensitive: list<string>, snapshot: array<string, mixed>, params: array<string, mixed>}
     */
    public function prepare(int $tenantId, array $subject, string $model, array $input, SseSheetAccess $access, string $ref = 'APERCU'): array
    {
        $lang = D::lang($input['lang'] ?? 'fr');
        $level = self::clampLevel($input['classification'] ?? '', $access);
        $faces = D::faces($input['faces'] ?? null);
        $out = ['ok' => false, 'errors' => [], 'data' => null, 'texts' => [], 'level' => $level, 'lang' => $lang, 'faces' => $faces, 'sensitive' => [], 'snapshot' => [], 'params' => []];
        if (!D::exists($model) || $model === 'notice') {
            $out['errors'][] = 'Modèle inconnu.';

            return $out;
        }
        $raw = $this->facts->raw($tenantId, $subject, $access, $lang);
        // Contrôles retenus (fiche de contrôle) : seulement ceux de la fiche, jamais une référence étrangère.
        if (isset($input['checks']) && is_array($input['checks'])) {
            $wanted = array_map('strval', $input['checks']);
            $raw['checks'] = array_values(array_filter($raw['checks'], static fn (array $c): bool => in_array((string) $c['ref'], $wanted, true)));
        }
        $required = $this->visibilityFor($tenantId, $model, $input);
        $masked = $this->facts->mask($raw, $level, $required);
        $facts = $masked['facts'];
        $threat = (string) ($input['threat'] ?? $facts['threat']);
        $facts['threat'] = isset(C::THREATS[$threat]) ? $threat : (string) $facts['threat'];
        $armed = (string) ($input['armed'] ?? $facts['armed']);
        $facts['armed'] = isset(C::ARMED[$armed]) ? $armed : ((string) $facts['armed'] !== '' ? (string) $facts['armed'] : 'unknown');

        $config = $this->settings->get($tenantId);
        $authority = [(string) $config['authority_fr'], (string) $config['authority_en']];
        $service = trim((string) $config['contact_label']);
        $issued = gmdate('Y-m-d');
        // Fiche de vigilance : niveau d'alerte, conduite à tenir et motifs codifiés, zone saisie, service à informer.
        $alert = null;
        if ($model === 'vigilance') {
            $zone = C::line($input['zone'] ?? '', 120);
            if ($zone !== '') {
                $facts['zone'] = $zone;
            }
            $built = $this->alert($config, $input, $facts, $lang);
            $alert = $built['alert'];
            $out['errors'] = array_merge($out['errors'], $built['errors']);
            $authority = [$alert['report']['auth_fr'] !== '' ? $alert['report']['auth_fr'] : $authority[0], $alert['report']['auth_en'] !== '' ? $alert['report']['auth_en'] : $authority[1]];
            $service = SseAlertReportTo::label($alert['report'], $lang);
        }
        $context = ['ref' => $ref, 'service' => $service, 'date' => $issued];
        $vars = $this->facts->variables($facts, $lang, $context);

        $texts = [];
        $used = [];
        $typed = is_array($input['texts'] ?? null) ? $input['texts'] : [];
        foreach (D::texts($model) as [$key, $max, $labels]) {
            $source = array_key_exists($key, $typed) ? T::clean($typed[$key], $max * 2) : $this->defaultSource($model, $key, $lang, $raw);
            $res = T::resolve($source, $vars);
            $used = array_merge($used, $res['used']);
            $clean = T::clean($res['text'], $max + 1000);
            if (T::length($clean) > $max) {
                $out['errors'][] = sprintf('« %s » : %d caractères au lieu de %d au plus.', $labels[0], T::length($clean), $max);
            }
            $texts[$key] = T::clean($clean, $max);
        }
        foreach ($this->facts->leaks($raw, $texts, $level, $required) as $secret) {
            $out['errors'][] = sprintf('Le texte reprend « %s », une donnée réservée à une diffusion plus restreinte : retirez-la ou relevez la classification.', $secret);
        }

        $validity = null;
        if ($model === 'vigilance') {
            $term = D::term($input);
            $validity = $term['days'];
        }
        $now = time();
        $expiresAt = null;
        if ($validity !== null) {
            $validUntil = gmdate('Y-m-d', strtotime($issued . ' UTC +' . $validity . ' days'));
        } elseif (($term['hours'] ?? null) !== null) {
            // Durée courte : échéance exacte à l'heure (UTC), la date imprimée est celle de cette échéance.
            $expiresAt = gmdate('Y-m-d H:i:s', $now + $term['hours'] * 3600);
            $validUntil = substr($expiresAt, 0, 10);
        } else {
            $validUntil = null;
        }
        if ($alert !== null) {
            $token = is_string($input['_token'] ?? null) && SseAlertRepository::validToken((string) $input['_token']) ? (string) $input['_token'] : '';
            $alert['token'] = $token;
            $alert['url'] = SseAlertReportTo::url($token);
            $alert['perishable'] = SseAlertLevel::perishable($expiresAt ?? $validUntil);
        }
        $labels = self::levelLabels($level);
        $sensitive = array_values(array_unique(array_merge($masked['shown'], T::categories($used))));
        $data = [
            'model' => $model, 'lang' => $lang, 'faces' => $faces, 'ref' => $ref,
            'title' => D::MODELS[$model]['label'], 'accent' => SseSheetPdf::ACCENTS[$model] ?? [55, 65, 81],
            'authority' => $authority, 'service' => $service,
            'issued_at' => $issued, 'issued_dt' => gmdate('Y-m-d H:i:s'), 'valid_until' => $validUntil, 'expires_at' => $expiresAt, 'until_lifted' => $model === 'vigilance' && ($term['lifted'] ?? false), 'classification' => $labels, 'level' => $level, 'visibility' => $required,
            'facts' => $facts, 'texts' => $texts, 'text_labels' => $this->textLabels($model),
            'emblem' => (string) $config['emblem'],
            'generated_at' => gmdate($lang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i') . ' UTC',
        ];
        if ($alert !== null) {
            $data['alert'] = $alert;
            $data['issuer'] = $access->actorLabel();
        }
        $out['ok'] = $out['errors'] === [];
        $out['data'] = $data;
        $out['texts'] = $texts;
        $out['sensitive'] = $sensitive;
        $out['params'] = ['lang' => $lang, 'classification' => $level, 'faces' => $faces, 'validity' => $validity, 'threat' => $facts['threat'], 'armed' => $facts['armed'], 'checks' => array_values(array_map(static fn (array $c): string => (string) $c['ref'], $facts['checks']))];
        $override = SseSheetVisibility::clean($input['visibility'] ?? []);
        if ($override !== []) {
            $out['params']['visibility'] = $override;
        }
        if ($alert !== null) {
            $out['params'] += [
                'measures' => array_column($alert['measures'], 'code'), 'reasons' => array_column($alert['reasons'], 'code'), 'zone' => (string) $facts['zone'],
                'service' => $alert['report']['code'], 'service_other' => $alert['report']['code'] === '' ? $alert['report']['fr'] : '',
                'report_delay' => $alert['report']['delay'], 'report_channel' => $alert['report']['channel'], 'level' => $alert['key'],
                'validity_hours' => $term['hours'] ?? null, 'until_lifted' => (bool) ($term['lifted'] ?? false),
            ];
        }
        // Instantané imprimé : données caviardées, sans image (les clichés sont rechargés au re-téléchargement).
        $out['snapshot'] = ['data' => array_diff_key($data, ['emblem' => 1, 'generated_at' => 1]), 'photos' => $facts['photos']];

        return $out;
    }

    /**
     * Octets du PDF à partir des données préparées (images chargées ici), ou null (moteur PDF indisponible).
     *
     * @param array<string, mixed> $data
     * @return array{bytes: string, pages: int, overflow: list<string>}|null
     */
    public function render(int $tenantId, array $data): ?array
    {
        $photos = [];
        foreach ((array) ($data['facts']['photos'] ?? []) as $path) {
            $bytes = SseWantedNoticePdfService::imageBytes((string) $path, false);
            if ($bytes !== null) {
                $photos[] = $bytes;
            }
        }
        $data['photo'] = $photos[0] ?? null;
        $data['extra_photos'] = array_slice($photos, 1, 3);
        $data['emblem_path'] = null;
        if (($data['emblem'] ?? 'neutral') === 'community') {
            try {
                $logo = AthenaPdfTheme::meta(['tenant_id' => $tenantId])['logo'];
                $data['emblem_path'] = is_string($logo) ? $logo : null;
            } catch (\Throwable) {
                $data['emblem_path'] = null;
            }
        }
        $data['generated_at'] ??= gmdate($data['lang'] === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i') . ' UTC';
        // Emblème téléversé du service à informer (sinon l'emblème générique dessiné par le gabarit).
        $code = (string) ($data['alert']['report']['code'] ?? '');
        if ($code !== '') {
            $data['alert']['report']['emblem_bytes'] = self::serviceEmblem($tenantId, $code);
        }

        return $this->template->render($data);
    }

    /** Octets de l'emblème téléversé d'un service de la communauté, ou null (aucun, étape de base absente, image illisible). */
    public static function serviceEmblem(int $tenantId, string $code): ?string
    {
        try {
            if ($code === '' || !class_exists(SseServiceEmblems::class)) {
                return null;
            }
            $bytes = (new SseServiceEmblems())->bytesFor($tenantId, $code);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Bloc d'alerte d'une fiche de vigilance : niveau (échelle de menace des avis, relevée par l'armement), conduite à tenir et
     * motifs codifiés (catalogue de la communauté), lignes « à ne pas faire », service à informer.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $input measures, reasons (listes de codes ou null : valeurs d'office du niveau), service, service_other, report_delay, report_channel
     * @param array<string, mixed> $facts
     * @return array{alert: array<string, mixed>, errors: list<string>}
     */
    private function alert(array $config, array $input, array $facts, string $lang): array
    {
        $level = SseAlertLevel::of((string) $facts['threat'], (string) $facts['armed']);
        $pick = static function (mixed $raw, string $kind) use ($config): array {
            $list = is_array($raw) ? $raw : (is_string($raw) ? explode(',', $raw) : []);
            $out = [];
            foreach ($list as $code) {
                $code = strtoupper(trim(is_scalar($code) ? (string) $code : ''));
                if ($code === '' || isset($out[$code]) || SseWantedCodes::kindOf($code) !== $kind) {
                    continue;
                }
                $c = SseWantedCodes::find($code, $config);
                if ($c !== null && count($out) < D::MAX_CODES) {
                    $out[$code] = ['code' => $code, 'fr' => (string) $c['fr'], 'en' => (string) $c['en'], 'text_fr' => (string) ($c['text_fr'] ?? ''), 'text_en' => (string) ($c['text_en'] ?? '')];
                }
            }

            return array_values($out);
        };
        $measures = $pick(($input['measures'] ?? null) !== null ? $input['measures'] : SseAlertLevel::DEFAULT_MEASURES[$level['key']], SseWantedCodes::MEASURES);
        $reasons = $pick($input['reasons'] ?? [], SseWantedCodes::REASONS);
        $codes = array_column($measures, 'code');
        $resolved = SseAlertReportTo::resolve($config, [
            'service' => $input['service'] ?? '', 'other' => $input['service_other'] ?? '', 'channel' => $input['report_channel'] ?? '', 'delay' => $input['report_delay'] ?? '',
        ]);

        return [
            'alert' => [
                'key' => $level['key'], 'rank' => $level['rank'], 'order' => SseAlertLevel::order($codes),
                'measures' => $measures, 'reasons' => $reasons,
                'donts' => [SseAlertLevel::donts($codes, 'fr'), SseAlertLevel::donts($codes, 'en')],
                'report' => $resolved['report'], 'token' => '', 'url' => '', 'perishable' => false,
            ],
            'errors' => $resolved['errors'],
        ];
    }

    /**
     * Aperçu : PDF et rapport de mise en page, sans rien écrire.
     *
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>, bytes: ?string, pages: int, overflow: list<string>, sensitive: list<string>}
     */
    public function preview(int $tenantId, array $subject, string $model, array $input, SseSheetAccess $access): array
    {
        if ($model === 'notice') {
            return $this->previewNotice($tenantId, $subject, $input, $access);
        }
        $p = $this->prepare($tenantId, $subject, $model, $input, $access, 'APERCU-' . (D::MODELS[$model]['prefix'] ?? 'FX'));
        $rendered = $p['data'] !== null ? $this->render($tenantId, $p['data']) : null;
        if ($rendered === null && $p['errors'] === []) {
            $p['errors'][] = 'Le PDF n’a pas pu être généré : le moteur PDF ou ses polices sont indisponibles.';
        }

        return ['ok' => $rendered !== null, 'errors' => $p['errors'], 'bytes' => $rendered['bytes'] ?? null, 'pages' => $rendered['pages'] ?? 0, 'overflow' => $rendered['overflow'] ?? [], 'sensitive' => $p['sensitive']];
    }

    /**
     * Émet la fiche : droits, plafond de débit, aperçu sans erreur, référence, enregistrement (texte imprimé conservé) puis trace
     * dans la chronologie de l'identité. La fiche elle-même n'est jamais modifiée.
     *
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>, error: string, id: int, ref: string, bytes: ?string, filename: string, pages: int}
     */
    public function issue(int $tenantId, array $subject, string $model, array $input, SseSheetAccess $access): array
    {
        $fail = static fn (string $code, array $errors = []): array => ['ok' => false, 'errors' => $errors, 'error' => $code, 'id' => 0, 'ref' => '', 'bytes' => null, 'filename' => '', 'pages' => 0];
        $denial = $access->denial($model);
        if ($denial !== null) {
            return $fail('forbidden', [$denial]);
        }
        $quick = !empty($input['quick']);
        if ($model !== 'notice' && !$quick && !SseSheetRepository::ready()) {
            return $fail('unavailable', ['Les fiches PDF seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée par l’équipe technique.']);
        }
        $userId = $access->userId();
        if ($userId !== null && SseSheetRepository::ready()) {
            $since = gmdate('Y-m-d H:i:s', time() - self::ISSUE_WINDOW_MINUTES * 60);
            if ($this->repo->countIssuesSince($tenantId, $userId, $since) >= self::ISSUE_LIMIT) {
                return $fail('rate', [sprintf('Trop d’émissions en peu de temps (%d en %d minutes) : patientez avant d’en émettre une autre.', self::ISSUE_LIMIT, self::ISSUE_WINDOW_MINUTES)]);
            }
        }
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $notice = null;
            if ($model === 'notice') {
                $built = $this->buildNotice($tenantId, $subject, $input, $access);
                if ($built['error'] !== '') {
                    return $fail($built['error'], $built['errors']);
                }
                $ref = SseSheetRepository::ready() ? $this->repo->nextRef($tenantId, 'notice', (string) $built['notice']['ref'], $attempt) : ['ref' => (string) $built['notice']['ref'], 'year' => (int) gmdate('Y'), 'seq' => 0];
                $rendered = $built['bytes'];
                $texts = $built['texts'];
                $snapshot = ['notice_id' => (int) $built['notice']['id'], 'notice_ref' => (string) $built['notice']['ref'], 'params' => $built['params']];
                $sensitive = $built['sensitive'];
                $level = $built['level'];
                $lang = $built['lang'];
                $faces = $built['faces'];
                $pages = $built['pages'];
                $notice = $built['notice'];
            } else {
                // Génération rapide sans l'étape « fiches PDF » : référence locale non enregistrée, le PDF part directement.
                $ref = SseSheetRepository::ready() ? $this->repo->nextRef($tenantId, $model, null, $attempt) : ['ref' => (D::MODELS[$model]['prefix'] ?? 'FX') . '-' . gmdate('Y') . '-Q' . strtoupper(bin2hex(random_bytes(3))), 'year' => (int) gmdate('Y'), 'seq' => 0];
                // Adresse de signalement de la fiche de vigilance : le jeton est tiré avant l'impression (il est sur le PDF et dans le QR code).
                $input['_token'] = $model === 'vigilance' && SseSheetRepository::ready() && SseAlertRepository::ready() ? SseAlertRepository::newToken() : null;
                $p = $this->prepare($tenantId, $subject, $model, $input, $access, $ref['ref']);
                if (!$p['ok'] || $p['data'] === null) {
                    return $fail('invalid', $p['errors']);
                }
                $r = $this->render($tenantId, $p['data']);
                if ($r === null) {
                    return $fail('engine', ['Le PDF n’a pas pu être généré : le moteur PDF ou ses polices sont indisponibles.']);
                }
                $rendered = $r['bytes'];
                $texts = $p['texts'];
                $snapshot = $p['snapshot'] + ['params' => $p['params'], 'redaction' => $r['redaction'] ?? null];
                if (is_array($input['_reissue'] ?? null)) {
                    $snapshot['reissue'] = $input['_reissue'];
                }
                $sensitive = $p['sensitive'];
                $level = $p['level'];
                $lang = $p['lang'];
                $faces = $p['faces'];
                $pages = $r['pages'];
            }
            $id = 0;
            if (SseSheetRepository::ready()) {
                try {
                    $id = $this->repo->createIssue($tenantId, [
                        'person_id' => $subject['person_id'], 'identity_id' => $subject['identity_id'], 'model' => $model, 'lang' => $lang, 'classification' => $level,
                        'faces' => $faces, 'texts' => $texts, 'snapshot' => $snapshot, 'sensitive' => $sensitive, 'user_id' => $userId, 'label' => $access->actorLabel(),
                    ], $ref);
                } catch (\PDOException) {
                    continue;
                }
            }
            if ($id > 0 && $model === 'vigilance' && is_string($input['_token'] ?? null)) {
                try {
                    (new SseAlertRepository())->ensureLink($tenantId, SseAlertRepository::DOC_VIGILANCE, $id, [
                        'token' => $input['_token'], 'report' => $snapshot['data']['alert']['report'] ?? null, 'valid_until' => $snapshot['data']['valid_until'] ?? null,
                        'client_id' => is_string($input['cid'] ?? null) ? $input['cid'] : null, 'user_id' => $userId,
                    ]);
                } catch (\Throwable $e) {
                    error_log('Fiche de vigilance : adresse de signalement non enregistrée : ' . $e->getMessage());
                }
            }
            $this->trace($tenantId, $subject, $model, $ref['ref'], $lang, $notice !== null ? (int) $notice['id'] : null, $access);

            return ['ok' => true, 'errors' => [], 'error' => '', 'id' => $id, 'ref' => $model === 'notice' ? (string) ($notice['ref'] ?? $ref['ref']) : $ref['ref'], 'bytes' => $rendered, 'filename' => self::filename($model, (string) ($notice['ref'] ?? $ref['ref']), $lang), 'pages' => $pages];
        }

        return $fail('conflict', ['La référence du document n’a pas pu être réservée : réessayez.']);
    }

    /**
     * Re-télécharge un document émis : mêmes textes, même niveau de diffusion. Il faut au moins l'habilitation de sa
     * classification et le droit de lire les fichiers.
     *
     * Avec `$atLevel`, le PDF est rendu à ce niveau de diffusion (SseSheetLevelService) : même référence, même instantané, rien n'est émis.
     *
     * @return array{ok: bool, error: string, bytes: ?string, filename: string, issue: ?array<string, mixed>}
     */
    public function reissue(int $tenantId, int $issueId, SseSheetAccess $access, ?string $atLevel = null): array
    {
        $fail = static fn (string $e, ?array $issue = null): array => ['ok' => false, 'error' => $e, 'bytes' => null, 'filename' => '', 'issue' => $issue];
        $issue = $this->repo->findIssue($tenantId, $issueId);
        if ($issue === null) {
            return $fail('notfound');
        }
        if (!$access->canDraft() || SseRedactionService::levelRank($issue['classification']) > SseRedactionService::levelRank($access->level())) {
            return $fail('forbidden', $issue);
        }
        $snap = $issue['snapshot'];
        $levels = $atLevel !== null ? new SseSheetLevelService($this->repo, $this->facts, $this) : null;
        $plan = $levels?->plan($issue, (string) $atLevel);
        if ($plan !== null && !in_array($plan['state'], ['origin', 'lower', 'higher'], true)) {
            return $fail($plan['state'] === 'reissue' ? 'reissue' : 'level', $issue);
        }
        if ($issue['model'] === 'notice') {
            $notice = (new SseWantedNoticeRepository())->find($tenantId, (int) ($snap['notice_id'] ?? 0));
            if ($notice === null) {
                return $fail('notfound', $issue);
            }
            $svc = new SseWantedNoticePdfService();
            $data = $svc->data($tenantId, $notice, $issue['lang'], $issue['faces']);
            if ($data === null) {
                return $fail('notfound', $issue);
            }
            foreach (['reason', 'instructions'] as $k) {
                if (isset($issue['texts'][$k])) {
                    $data[$k] = T::plain((string) $issue['texts'][$k]);
                }
            }
            $bytes = (new SseWantedNoticePdf())->render($data);
        } else {
            $data = is_array($snap['data'] ?? null) ? $snap['data'] : [];
            if ($data === []) {
                return $fail('notfound', $issue);
            }
            $data['ref'] = $issue['ref'];
            $data['texts'] = $issue['texts'];
            // Documents émis avant les niveaux américains : le code stocké donne le marquage (mêmes codes, nouveaux libellés).
            $data['level'] = SseUsClassification::normalize((string) $issue['classification']);
            $data['classification'] = self::levelLabels($data['level']);
            $data['facts']['photos'] = is_array($snap['photos'] ?? null) ? $snap['photos'] : [];
            // Documents émis avant l'étiquetage des barres par catégorie : la catégorie se déduit du champ (le PDF stocké ne change pas).
            $data['facts'] = SseRedactionExplain::retag($data['facts']);
            if ($plan !== null && $plan['state'] !== 'origin') {
                $data = SseSheetLevelService::apply($data, $plan, $data['level'], (string) $atLevel);
            }
            $data['emblem'] = (string) $this->settings->get($tenantId)['emblem'];
            // Fiche de vigilance levée depuis : le PDF re-téléchargé le dit (mention et date), sans rien changer d'autre.
            if ($issue['model'] === 'vigilance' && is_array($data['alert'] ?? null)) {
                $link = (new SseAlertRepository())->linkFor($tenantId, SseAlertRepository::DOC_VIGILANCE, (int) $issue['id']);
                $data['alert']['lifted_at'] = $link['lifted_at'] ?? null;
                $data['alert']['perishable'] = ($link['lifted_at'] ?? null) === null && SseAlertLevel::perishable((string) (($data['expires_at'] ?? null) ?: ($data['valid_until'] ?? '')));
            }
            $bytes = $this->render($tenantId, $data)['bytes'] ?? null;
        }
        if ($bytes === null) {
            return $fail('engine', $issue);
        }

        return ['ok' => true, 'error' => '', 'bytes' => $bytes, 'filename' => self::filename($issue['model'], (string) ($snap['notice_ref'] ?? $issue['ref']), $issue['lang']), 'issue' => $issue];
    }

    /** Nom de fichier sûr, fabriqué ici : « fiche-vigilance-FV-2026-0007-fr.pdf ». */
    public static function filename(string $model, string $ref, string $lang): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ref), '-');
        $m = trim((string) preg_replace('/[^a-z]+/', '', $model));

        return ($model === 'notice' ? 'avis' : 'fiche-' . ($m !== '' ? $m : 'doc')) . '-' . ($safe !== '' ? $safe : 'doc') . '-' . D::lang($lang) . '.pdf';
    }

    /** Aperçus par minute : plafond par session (compteur en session, pas de base). */
    public static function previewAllowed(): bool
    {
        $now = time();
        $hits = array_values(array_filter((array) Session::get('sse_sheet_previews', []), static fn ($t): bool => is_int($t) && $now - $t < 60));
        if (count($hits) >= self::PREVIEW_LIMIT) {
            Session::set('sse_sheet_previews', $hits);

            return false;
        }
        $hits[] = $now;
        Session::set('sse_sheet_previews', $hits);

        return true;
    }

    // ----------------------------------------------------------------- Avis

    /**
     * L'avis actif de la fiche, ses textes (modifiables) et son PDF. La notice garde son moteur (SseWantedNoticePdf).
     *
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $input
     * @return array{error: string, errors: list<string>, notice: ?array<string, mixed>, bytes: ?string, texts: array<string, string>, params: array<string, mixed>, sensitive: list<string>, level: string, lang: string, faces: string, pages: int}
     */
    private function buildNotice(int $tenantId, array $subject, array $input, SseSheetAccess $access): array
    {
        $none = ['error' => '', 'errors' => [], 'notice' => null, 'bytes' => null, 'texts' => [], 'params' => [], 'sensitive' => [], 'level' => SseCaseRepository::CLASS_INTERNAL, 'lang' => 'fr', 'faces' => 'auto', 'pages' => 1];
        $identityId = (int) ($subject['identity_id'] ?? 0);
        $notice = $identityId > 0 ? (new SseWantedNoticeRepository())->activeForIdentity($tenantId, $identityId) : null;
        if ($notice === null) {
            return ['error' => 'nonotice', 'errors' => ['Aucun avis de recherche actif : placez-en un depuis la fiche du registre.']] + $none;
        }
        $lang = D::lang($input['lang'] ?? 'fr');
        $faces = D::faces($input['faces'] ?? null);
        $level = self::clampLevel($input['classification'] ?? '', $access);
        $svc = new SseWantedNoticePdfService();
        $data = $svc->data($tenantId, $notice, $lang, $faces);
        if ($data === null) {
            return ['error' => 'notfound', 'errors' => ['Avis introuvable.']] + $none;
        }
        // Textes modifiables de la notice : motifs et mesures, assainis ; ce que la notice imprime est du texte brut.
        $raw = $this->facts->raw($tenantId, $subject, $access, $lang);
        $texts = [];
        $errors = [];
        $typed = is_array($input['texts'] ?? null) ? $input['texts'] : [];
        $vars = $this->facts->variables($this->facts->mask($raw, $level, $this->visibilityFor($tenantId, 'notice', $input))['facts'], $lang, ['ref' => (string) $notice['ref'], 'service' => (string) ($notice['contact_label'] ?? ''), 'date' => gmdate('Y-m-d')]);
        foreach (D::texts('notice') as [$key, $max, $labels]) {
            $source = array_key_exists($key, $typed) ? T::clean($typed[$key], $max * 2) : T::clean((string) ($notice[$key] ?? ''), $max);
            $res = T::resolve($source, $vars);
            $clean = T::clean($res['text'], $max + 1000);
            if (T::length($clean) > $max) {
                $errors[] = sprintf('« %s » : %d caractères au lieu de %d au plus.', $labels[0], T::length($clean), $max);
            }
            $texts[$key] = T::clean($clean, $max);
        }
        if ($errors !== []) {
            return ['error' => 'invalid', 'errors' => $errors] + $none;
        }
        foreach ($texts as $k => $v) {
            $data[$k] = T::plain($v);
        }
        $bytes = (new SseWantedNoticePdf())->render($data);
        if ($bytes === null) {
            return ['error' => 'engine', 'errors' => ['Le PDF n’a pas pu être généré : le moteur PDF ou ses polices sont indisponibles.']] + $none;
        }

        return [
            'error' => '', 'errors' => [], 'notice' => $notice, 'bytes' => $bytes, 'texts' => $texts,
            'params' => ['lang' => $lang, 'classification' => $level, 'faces' => $faces], 'sensitive' => ['identite'], 'level' => $level, 'lang' => $lang, 'faces' => $faces,
            'pages' => max(1, preg_match_all('~/Type\s*/Page\b(?!s)~', $bytes) ?: 1),
        ];
    }

    /** @param array<string, mixed> $subject @param array<string, mixed> $input @return array{ok: bool, errors: list<string>, bytes: ?string, pages: int, overflow: list<string>, sensitive: list<string>} */
    private function previewNotice(int $tenantId, array $subject, array $input, SseSheetAccess $access): array
    {
        $b = $this->buildNotice($tenantId, $subject, $input, $access);

        return ['ok' => $b['bytes'] !== null, 'errors' => $b['errors'], 'bytes' => $b['bytes'], 'pages' => $b['pages'], 'overflow' => [], 'sensitive' => $b['sensitive']];
    }

    // ---------------------------------------------------------------- Outils

    /** @param array<string, mixed> $raw @return array<string, string> */
    private function defaultTexts(int $tenantId, string $model, string $lang, array $raw, string $level, string $ref, ?array $required = null): array
    {
        $masked = $this->facts->mask($raw, $level, $required);
        $config = $this->settings->get($tenantId);
        $vars = $this->facts->variables($masked['facts'], $lang, ['ref' => $ref, 'service' => (string) $config['contact_label'], 'date' => gmdate('Y-m-d')]);
        $out = [];
        foreach (D::texts($model) as [$key, $max]) {
            $source = $model === 'notice' ? T::clean((string) ($raw['notice'][$key] ?? ''), $max) : $this->defaultSource($model, $key, $lang, $raw);
            $out[$key] = T::clean(T::resolve($source, $vars)['text'], $max);
        }

        return $out;
    }

    /** @param array<string, mixed> $raw */
    private function defaultSource(string $model, string $key, string $lang, array $raw): string
    {
        unset($raw);

        return D::default($model, $key, $lang);
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function textLabels(string $model): array
    {
        $out = [];
        foreach (D::texts($model) as [$key, , $labels]) {
            $out[$key] = $labels;
        }

        return $out;
    }

    /** @param array<string, mixed> $subject */
    private function trace(int $tenantId, array $subject, string $model, string $docRef, string $lang, ?int $noticeId, SseSheetAccess $access): void
    {
        // Journal d'audit : chaque émission, avec son auteur (jamais bloquant).
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $access->userId(), 'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Fiches PDF',
                'action' => 'document.issued', 'object_type' => 'sse_doc_issue', 'object_uuid' => mb_substr($docRef, 0, 36), 'reason' => '',
                'after' => ['ref' => $docRef, 'model' => $model, 'lang' => $lang, 'person_id' => $subject['person_id'] ?? null, 'identity_id' => $subject['identity_id'] ?? null],
            ]);
        } catch (\Throwable $e) {
            error_log('Fiches PDF : audit indisponible : ' . $e->getMessage());
        }
        $identityId = (int) ($subject['identity_id'] ?? 0);
        if ($identityId < 1 || !SseIdentityEventRepository::ready()) {
            return;
        }
        $labels = D::MODELS[$model]['label'];
        try {
            (new SseIdentityEventRepository())->add(
                $tenantId, $identityId, $model === 'notice' ? 'notice.pdf' : 'document.issued',
                sprintf('%s %s émis (%s).', $labels[0], $docRef, strtoupper($lang)),
                ['ref' => $docRef, 'doc_ref' => $docRef, 'lang' => $lang, 'model' => $model, 'model_label_fr' => $labels[0], 'model_label_en' => $labels[1]],
                $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId
            );
        } catch (\Throwable $e) {
            error_log('Fiches PDF : émission non notée dans la chronologie : ' . $e->getMessage());
        }
    }
}
