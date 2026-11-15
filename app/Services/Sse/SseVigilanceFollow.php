<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAlertRepository;
use App\Repositories\SseSheetRepository;
use App\Support\SseAccessCatalog as Rights;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseWantedCodes;
use App\Support\SseWantedServices;
use App\Support\SseWantedCatalog as C;

/**
 * Interface portail des fiches de vigilance : données de l'écran d'émission (niveaux, consignes, motifs, services), lecture
 * de la saisie, liste des fiches émises d'une personne avec les droits de chaque action, et bandeau d'avertissement de la fiche.
 *
 * Aucune règle métier nouvelle : l'émission est celle de SseSheetService::issue, le renouvellement et la levée sont ceux de
 * SseAlertService (renewVigilance, liftVigilance). Droits :
 *  - émettre ou renouveler : SseSheetAccess::denial('vigilance') (écrire « Fichiers et sujets », exporter « Papiers ») ;
 *  - lever : gérer « Fichiers et sujets », ou être l'émetteur de la fiche (SseAlertService::canLift) ;
 *  - voir une fiche, son PDF et son adresse : l'habilitation de lecteur doit couvrir la classification de la fiche.
 */
final class SseVigilanceFollow
{
    /** Raccourcis de validité (jours) : parmi SseSheetCatalog::VALIDITY_DAYS. */
    public const SHORTCUT_DAYS = [7, 14, 30, 90];

    /** Niveau d'alerte choisi => menace et armement enregistrés (le niveau en découle, SseAlertLevel::key). */
    public const LEVEL_INPUT = [
        'watch' => ['threat' => 'low', 'armed' => 'no'],
        'reinforced' => ['threat' => 'medium', 'armed' => 'no'],
        'caution' => ['threat' => 'high', 'armed' => 'no'],
        'danger' => ['threat' => 'high', 'armed' => 'yes'],
    ];

    /**
     * Saisie propre à la fiche de vigilance. `measures` reste null tant que la liste n'a pas été envoyée (valeurs d'office du
     * niveau) ; le niveau choisi fixe la menace et l'armement.
     *
     * @param array<string, mixed> $raw level, measures, measures_sent, reasons, zone, service, service_other, report_channel, report_delay
     * @param array<string, mixed> $base saisie commune déjà lue (threat, armed…)
     * @return array<string, mixed>
     */
    public static function readInput(array $raw, array $base): array
    {
        $out = [];
        $level = is_string($raw['level'] ?? null) ? $raw['level'] : '';
        if (isset(self::LEVEL_INPUT[$level])) {
            $out['level'] = $level;
            $out['threat'] = self::LEVEL_INPUT[$level]['threat'];
            $out['armed'] = self::LEVEL_INPUT[$level]['armed'];
        }
        $codes = static function (mixed $list): array {
            $res = [];
            foreach (is_array($list) ? array_slice($list, 0, 40) : [] as $c) {
                $c = strtoupper(trim(is_scalar($c) ? (string) $c : ''));
                if ($c !== '' && mb_strlen($c) <= 12 && !in_array($c, $res, true)) {
                    $res[] = $c;
                }
            }

            return $res;
        };
        $out['measures'] = ($raw['measures_sent'] ?? '') === '1' ? $codes($raw['measures'] ?? []) : null;
        $out['reasons'] = $codes($raw['reasons'] ?? []);
        $out['zone'] = C::line($raw['zone'] ?? '', 120);
        $out['service'] = C::line($raw['service'] ?? '', 30);
        $out['service_other'] = C::line($raw['service_other'] ?? '', 120);
        $out['report_channel'] = C::line($raw['report_channel'] ?? '', 200);
        $out['report_delay'] = isset(SseAlertLevel::DELAYS[(string) ($raw['report_delay'] ?? '')]) ? (string) $raw['report_delay'] : '';

        return $out;
    }

    /** Niveau d'alerte (clé) pour une menace et un armement. */
    public static function levelKey(?string $threat, ?string $armed): string
    {
        return SseAlertLevel::key($threat, $armed);
    }

    /**
     * Cartes du sélecteur de niveau.
     *
     * @return list<array<string, mixed>>
     */
    public static function levels(int $ui): array
    {
        $out = [];
        foreach (SseAlertLevel::LEVELS as $key => $l) {
            $out[] = [
                'key' => $key, 'rank' => $l['rank'], 'title' => $l['title'][$ui], 'hint' => $l['hint'][$ui], 'shape' => $l['shape'],
                'hex' => SseAlertLevel::hex($key), 'ink' => self::hexOf($l['ink']),
                'measures' => SseAlertLevel::DEFAULT_MEASURES[$key],
            ];
        }

        return $out;
    }

    /**
     * Données de l'écran d'émission : catalogue de consignes et de motifs de la communauté, services, délais, valeurs par défaut.
     *
     * @param array<string, mixed> $config réglages de la communauté
     * @return array<string, mixed>
     */
    public static function editorData(array $config, int $ui): array
    {
        $cat = SseWantedCodes::catalogue();
        $group = static function (string $kind) use ($config, $cat, $ui): array {
            $famKey = $kind === SseWantedCodes::MEASURES ? 'measure_families' : 'reason_families';
            $groups = [];
            foreach (SseWantedCodes::active($config, $kind) as $code => $c) {
                $fam = (string) ($c['family'] ?? '');
                $label = isset($cat[$famKey][$fam]) ? (string) $cat[$famKey][$fam][$ui === 1 ? 'en' : 'fr'] : ($ui === 1 ? 'Community codes' : 'Codes de la communauté');
                $groups[$fam !== '' && isset($cat[$famKey][$fam]) ? $fam : '_custom']['label'] = $label;
                $groups[$fam !== '' && isset($cat[$famKey][$fam]) ? $fam : '_custom']['items'][] = [
                    'code' => (string) $code, 'label' => (string) ($ui === 1 ? $c['en'] : $c['fr']), 'text' => (string) ($ui === 1 ? ($c['text_en'] ?? '') : ($c['text_fr'] ?? '')),
                    'order' => in_array((string) $code, SseAlertLevel::ACTION_CODES, true) ? 'action' : (in_array((string) $code, SseAlertLevel::HOLD_CODES, true) ? 'hold' : ''),
                ];
            }

            return array_values($groups);
        };
        $svcGroups = [];
        $families = SseWantedServices::families();
        $palette = SseWantedServices::palette();
        $names = [];
        foreach (SseWantedServices::available($config) as $code => $svc) {
            $fam = (string) $svc['family'];
            $svcGroups[$fam]['label'] = (string) (($families[$fam] ?? ['fr' => $fam, 'en' => $fam])[$ui === 1 ? 'en' : 'fr']);
            $row = [
                'code' => (string) $code, 'name' => (string) ($ui === 1 ? $svc['en'] : $svc['fr']), 'ac' => (string) $svc['ac'],
                'contact' => SseWantedServices::contactLine($svc), 'color' => $palette[(string) $svc['color']] ?? '#1F3A5F',
                'desc' => (string) ($ui === 1 ? ($svc['desc_en'] ?? '') : ($svc['desc_fr'] ?? '')),
            ];
            $svcGroups[$fam]['items'][] = $row;
            $names[$code] = $row;
        }
        $delays = [];
        foreach (SseAlertLevel::DELAYS as $k => $d) {
            $delays[$k] = $d[$ui];
        }

        return [
            'levels' => self::levels($ui),
            'measures' => $group(SseWantedCodes::MEASURES), 'reasons' => $group(SseWantedCodes::REASONS),
            'services' => array_values($svcGroups), 'serviceByCode' => $names, 'defaultService' => SseWantedServices::defaultCode($config),
            'defaultLabel' => (string) ($config['contact_label'] ?? ''), 'defaultChannel' => (string) ($config['report_channel'] ?? ''),
            'delays' => $delays, 'defaultDelay' => isset(SseAlertLevel::DELAYS[(string) ($config['report_delay'] ?? '')]) ? (string) $config['report_delay'] : SseAlertReportTo::DEFAULT_DELAY,
            'maxCodes' => \App\Support\SseSheetCatalog::MAX_CODES, 'shortcuts' => self::SHORTCUT_DAYS, 'hours' => \App\Support\SseSheetCatalog::VALIDITY_HOURS, 'today' => gmdate('Y-m-d'),
        ];
    }

    // --------------------------------------------------------------- Fiches émises

    /**
     * Fiches de vigilance d'une personne et de ses identités, présentées avec les droits de chaque action, plus le bandeau
     * (la plus grave en cours, lisible à l'habilitation du lecteur).
     *
     * @param array<string, mixed> $subject fiche (SseSheetFactsService::subject ou équivalent) : person_id, identity_id, identities
     * @return array{list: list<array<string, mixed>>, banner: ?array<string, mixed>}
     */
    public static function forSubject(int $tenantId, array $subject, SseSheetAccess $access, int $ui, ?SseAlertService $svc = null): array
    {
        if (!SseSheetRepository::ready()) {
            return ['list' => [], 'banner' => null];
        }
        $svc ??= new SseAlertService();
        $ids = array_map(static fn (array $i): int => (int) $i['id'], (array) ($subject['identities'] ?? []));
        if (($subject['identity_id'] ?? null) !== null && !in_array((int) $subject['identity_id'], $ids, true)) {
            $ids[] = (int) $subject['identity_id'];
        }
        $list = [];
        foreach ($svc->vigilancesOf($tenantId, $subject['person_id'] ?? null, $ids) as $view) {
            $list[] = self::present($view, $access, $ui);
        }
        $banner = null;
        foreach ($list as $p) {
            if (!$p['masked'] && $p['state'] === 'active' && ($banner === null || $p['rank'] > $banner['rank'])) {
                $banner = $p;
            }
        }
        if ($banner !== null) {
            $others = count(array_filter($list, static fn (array $p): bool => !$p['masked'] && $p['state'] === 'active' && $p['id'] !== $banner['id']));
            // Le bandeau n'affiche jamais plus que le niveau, la consigne, l'échéance et le lien : ni motifs, ni service, ni zone.
            $banner = array_intersect_key($banner, array_flip(['id', 'ref', 'level', 'rank', 'title', 'shape', 'hex', 'ink', 'order', 'order_label', 'measures', 'until', 'until_label', 'until_lifted', 'exact', 'days_left', 'hours_left', 'perishable', 'pdf_url']));
            $banner['others'] = $others;
        }

        return ['list' => $list, 'banner' => $banner];
    }

    /**
     * @param array<string, mixed> $view SseAlertService::vigilanceView
     * @return array<string, mixed>
     */
    public static function present(array $view, SseSheetAccess $access, int $ui): array
    {
        $id = (int) $view['id'];
        $masked = SseRedactionService::levelRank((string) $view['classification']) > SseRedactionService::levelRank($access->level());
        $base = [
            'id' => $id, 'ref' => (string) $view['ref'], 'state' => (string) $view['state'], 'masked' => $masked, 'rank' => (int) $view['rank'],
            'level' => (string) $view['level'],
        ];
        $lv = SseAlertLevel::LEVELS[(string) $view['level']] ?? SseAlertLevel::LEVELS['watch'];
        $base += ['title' => $lv['title'][$ui], 'shape' => $lv['shape'], 'hex' => SseAlertLevel::hex((string) $view['level']), 'ink' => self::hexOf($lv['ink'])];
        if ($masked) {
            return $base + ['can_pdf' => false, 'can_renew' => false, 'can_lift' => false, 'renew_denial' => null, 'lift_denial' => null, 'denial' => 'Classée au-dessus de votre habilitation.'];
        }
        $renewDenial = $access->denial('vigilance');
        if ($renewDenial === null && $view['state'] === 'lifted') {
            $renewDenial = 'Une fiche levée ne se renouvelle pas : émettez-en une nouvelle.';
        }
        $liftDenial = null;
        if ($view['state'] !== 'active') {
            $liftDenial = $view['state'] === 'lifted' ? 'Cette fiche de vigilance est déjà levée.' : 'Cette fiche est expirée : il n’y a plus rien à lever.';
        } elseif (!SseAlertRepository::ready()) {
            $liftDenial = 'La levée sera disponible dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.';
        } elseif (!SseAlertService::canLift($access, $view)) {
            $liftDenial = self::liftRightMessage();
        }
        $order = (string) $view['order'];
        $measures = array_map(static fn (array $m): string => (string) ($ui === 1 ? $m['en'] : $m['fr']), array_slice((array) $view['measures'], 0, 4));
        $reasons = array_map(static fn (array $m): string => (string) ($ui === 1 ? $m['en'] : $m['fr']), array_slice((array) $view['reasons'], 0, 4));
        $until = (string) $view['until'];
        $exact = (string) ($view['expires_at'] ?? '');
        $lifted = !empty($view['until_lifted']);
        $endTs = $exact !== '' ? strtotime($exact . ' UTC') : ($until !== '' ? strtotime($until . ' 23:59:59 UTC') : false);
        $daysLeft = $endTs !== false ? (int) floor(($endTs - time()) / 86400) : null;
        $hoursLeft = $endTs !== false ? (int) floor(($endTs - time()) / 3600) : null;
        $untilLabel = $lifted ? 'Jusqu’à levée' : ($exact !== '' && $endTs !== false ? gmdate('d/m/Y H:i', $endTs) . ' UTC' : self::dateLabel($until));
        $report = (array) $view['report'];

        return $base + [
            'name' => (string) $view['name'], 'order' => $order, 'order_label' => $order !== '' ? SseAlertLevel::ORDERS[$order][$ui] : '',
            'measures' => $measures, 'reasons' => $reasons, 'zone' => (string) $view['zone'],
            'issued_label' => self::dateLabel((string) $view['issued_at']), 'issued_by' => (string) $view['issued_by'],
            'until' => $until, 'until_label' => $untilLabel, 'until_lifted' => $lifted, 'exact' => $exact !== '', 'days_left' => $daysLeft, 'hours_left' => $hoursLeft, 'perishable' => (bool) $view['perishable'],
            'lifted_label' => self::dateLabel((string) ($view['lifted_at'] ?? '')), 'lift_reason' => (string) $view['lift_reason'], 'lifted_by' => (string) $view['lifted_by'],
            'service' => SseAlertReportTo::label($report, $ui === 1 ? 'en' : 'fr'), 'delay' => SseAlertReportTo::delayLabel((string) ($report['delay'] ?? 'now'), $ui === 1 ? 'en' : 'fr'),
            'channel' => (string) ($report['channel'] ?? ''), 'url' => (string) $view['url'], 'qr_url' => $view['url'] !== '' ? $view['url'] . '/qr.svg' : '',
            'can_pdf' => true, 'can_renew' => $renewDenial === null, 'renew_denial' => $renewDenial, 'can_lift' => $liftDenial === null, 'lift_denial' => $liftDenial,
            'pdf_url' => url('atak/sse/identites/emissions/' . $id . '/pdf'),
            'renew_url' => url('atak/sse/identites/emission/vigilances/' . $id . '/renouveler'),
            'lift_url' => url('atak/sse/identites/emission/vigilances/' . $id . '/lever'),
            'denial' => null,
        ];
    }

    public static function liftRightMessage(): string
    {
        return 'Droit manquant : gérer « ' . Rights::sectionLabel('fichiers') . ' », ou être l’émetteur de cette fiche. Demandez-le à un gestionnaire des accès SSE.';
    }

    /** « jj/mm/aaaa » pour « aaaa-mm-jj » ou « aaaa-mm-jj hh:mm:ss » (UTC), « — » sinon. */
    public static function dateLabel(string $value): string
    {
        $ts = $value !== '' ? strtotime($value . (strlen($value) <= 10 ? ' 12:00:00' : '') . ' UTC') : false;

        return $ts === false ? '—' : gmdate('d/m/Y', $ts);
    }

    // ------------------------------------------------------------------ Rendu

    /** Pictogramme du niveau : forme pleine à la couleur du niveau, point d'exclamation à l'encre lisible. Variables --lv et --lv-ink. */
    public static function shapeSvg(string $shape, int $size = 28): string
    {
        $poly = match ($shape) {
            'diamond' => '<polygon points="12,1 23,12 12,23 1,12"/>',
            'octagon' => '<polygon points="8,1.5 16,1.5 22.5,8 22.5,16 16,22.5 8,22.5 1.5,16 1.5,8"/>',
            default => '<polygon points="12,2 23,21 1,21" stroke-linejoin="round"/>',
        };
        $mark = $shape === 'triangle'
            ? '<rect x="11" y="9" width="2" height="6.5" rx="1" class="vig-ico__mark"/><circle cx="12" cy="18" r="1.15" class="vig-ico__mark"/>'
            : '<rect x="11" y="6.5" width="2" height="7.5" rx="1" class="vig-ico__mark"/><circle cx="12" cy="17.2" r="1.2" class="vig-ico__mark"/>';

        return '<svg class="vig-ico" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><g class="vig-ico__shape">' . $poly . '</g>' . $mark . '</svg>';
    }

    /** Quatre pastilles dont « rang » sont pleines. */
    public static function pips(int $rank): string
    {
        $out = '<span class="vig-pips" aria-hidden="true">';
        for ($i = 1; $i <= 4; $i++) {
            $out .= '<i' . ($i <= $rank ? ' class="is-on"' : '') . '></i>';
        }

        return $out . '</span>';
    }

    /** @param array{0: int, 1: int, 2: int} $rgb */
    private static function hexOf(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }
}
