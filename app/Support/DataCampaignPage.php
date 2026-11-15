<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Présentation de la page « Fiabilisation des données » du membre (/jnet/moi/fiabilisation) : ordre des blocs, progression,
 * libellés d'état et d'action, échéance, résumés de valeurs (identifiants masqués). Fonctions pures, sans base ni session :
 * la vue ne fait que les afficher. Aucune règle métier ici (états, droits et confirmation restent dans DataCampaignLoginPrompt
 * et DataCampaignService) : seulement ce que le membre voit et dans quel ordre.
 *
 * Les textes sont écrits en français ; la traduction anglaise est faite à l'affichage (HtmlTranslator, lang/en/site_auto.php).
 */
final class DataCampaignPage
{
    /** Valeur que le lecteur de fiche écrit quand une donnée manque. */
    public const MISSING = 'Non renseigné';

    /** Ordre d'affichage : ce que le membre doit faire d'abord, puis le suivi, puis ce qui est fait. */
    private const PRIORITY = ['incomplete' => 0, 'to_confirm' => 1, 'flagged' => 2, 'ok' => 3];

    public static function pageTitle(): string
    {
        return 'Vérifions votre fiche';
    }

    public static function pageLead(): string
    {
        return 'Relisez vos informations et confirmez qu’elles sont exactes. Cela prend environ 2 minutes.';
    }

    /** « À traiter » : le membre a une action à faire (compléter ou confirmer). */
    public static function isTodo(string $status): bool
    {
        return $status === 'incomplete' || $status === 'to_confirm';
    }

    public static function statusLabel(string $status): string
    {
        return ['ok' => 'Exact', 'to_confirm' => 'À confirmer', 'incomplete' => 'À compléter', 'flagged' => 'Correction demandée'][$status] ?? 'À confirmer';
    }

    /** Bouton principal d'un bloc. */
    public static function actionLabel(string $status): string
    {
        return ['ok' => 'Revoir', 'to_confirm' => 'Confirmer', 'incomplete' => 'Compléter', 'flagged' => 'Corriger'][$status] ?? 'Confirmer';
    }

    /**
     * Blocs dans l'ordre d'affichage : à compléter, à confirmer, correction demandée, exacts ; à égalité, l'ordre du parcours.
     *
     * @param array<string, array<string, mixed>> $blocks blocs de DataCampaignService::memberView
     * @return list<array{key: string, status: string, block: array<string, mixed>}>
     */
    public static function ordered(array $blocks): array
    {
        $rows = [];
        $i = 0;
        foreach ($blocks as $key => $block) {
            $status = DataCampaignLoginPrompt::blockStatus($block);
            $rows[] = ['key' => (string) $key, 'status' => $status, 'block' => $block, 'rank' => self::PRIORITY[$status] * 1000 + $i++];
        }
        usort($rows, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        return array_map(static fn (array $r): array => ['key' => $r['key'], 'status' => $r['status'], 'block' => $r['block']], $rows);
    }

    /**
     * Les deux groupes de la liste : à traiter, puis déjà traités (suivi en cours et exacts).
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array{todo: list<array{key: string, status: string, block: array<string, mixed>}>, done: list<array{key: string, status: string, block: array<string, mixed>}>}
     */
    public static function groups(array $blocks): array
    {
        $todo = [];
        $done = [];
        foreach (self::ordered($blocks) as $row) {
            if (self::isTodo($row['status'])) {
                $todo[] = $row;
            } else {
                $done[] = $row;
            }
        }

        return ['todo' => $todo, 'done' => $done];
    }

    /**
     * Progression : « 4 sur 7 blocs confirmés ». La barre montre les blocs exacts (vert) et ceux dont une correction est en
     * cours d'examen (ambre) ; seuls les exacts comptent comme confirmés.
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array{total: int, confirmed: int, flagged: int, remaining: int, percent: int, flaggedPercent: int}
     */
    public static function progress(array $blocks): array
    {
        $sum = DataCampaignLoginPrompt::summarize($blocks);
        $total = (int) $sum['total'];

        return [
            'total' => $total,
            'confirmed' => (int) $sum['ok'],
            'flagged' => (int) $sum['flagged'],
            'remaining' => (int) $sum['incomplete'] + (int) $sum['to_confirm'],
            'percent' => $total > 0 ? (int) floor($sum['ok'] * 100 / $total) : 0,
            'flaggedPercent' => $total > 0 ? (int) floor($sum['flagged'] * 100 / $total) : 0,
        ];
    }

    public static function progressLabel(int $confirmed, int $total): string
    {
        return $total === 1 ? $confirmed . ' sur 1 bloc confirmé' : $confirmed . ' sur ' . $total . ' blocs confirmés';
    }

    /** Phrase sous la barre : ce qui reste à faire, puis ce qui est en cours d'examen. */
    public static function remainingLabel(int $remaining, int $flagged): string
    {
        if ($remaining > 0) {
            return $remaining === 1 ? 'Il reste 1 bloc à traiter' : 'Il reste ' . $remaining . ' blocs à traiter';
        }

        return $flagged > 0 ? 'Rien d’autre à faire de votre côté' : 'Tout est vérifié';
    }

    public static function flaggedLabel(int $flagged): string
    {
        return $flagged === 1 ? '1 correction en cours d’examen' : $flagged . ' corrections en cours d’examen';
    }

    /**
     * Échéance sous forme de pastille : ton ('none'|'calm'|'soon'|'late'), date et délai. Après confirmation, plus d'échéance.
     *
     * @return array{tone: string, date: string, delay: string}
     */
    public static function due(string $dueOn, string $today, bool $confirmed): array
    {
        $dueTs = $dueOn !== '' ? strtotime($dueOn) : false;
        $todayTs = strtotime($today);
        if ($confirmed || $dueTs === false || $todayTs === false) {
            return ['tone' => 'none', 'date' => '', 'delay' => ''];
        }
        $days = (int) round(($dueTs - $todayTs) / 86400);
        $date = date('d/m/Y', $dueTs);
        if ($days < 0) {
            $late = -$days;

            return ['tone' => 'late', 'date' => 'Date limite : ' . $date, 'delay' => $late === 1 ? 'dépassée depuis 1 jour' : 'dépassée depuis ' . $late . ' jours'];
        }
        if ($days === 0) {
            return ['tone' => 'late', 'date' => 'Avant le ' . $date, 'delay' => 'dernier jour aujourd’hui'];
        }
        $delay = $days === 1 ? '1 jour restant' : $days . ' jours restants';

        return ['tone' => $days <= 7 ? 'soon' : 'calm', 'date' => 'Avant le ' . $date, 'delay' => $delay];
    }

    /**
     * Action principale du bas de page : « Tout confirmer » quand c'est possible, sinon « Continuer » vers le premier bloc à
     * traiter (ou le récapitulatif quand il ne reste que du suivi).
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array{kind: string, label: string, step: string} kind : none (déjà confirmé) | confirm_all | go
     */
    public static function primaryAction(array $blocks, bool $confirmed): array
    {
        if ($confirmed) {
            return ['kind' => 'none', 'label' => '', 'step' => ''];
        }
        if (DataCampaignLoginPrompt::summarize($blocks)['can_confirm_all']) {
            return ['kind' => 'confirm_all', 'label' => 'Tout confirmer', 'step' => ''];
        }
        $first = self::groups($blocks)['todo'][0]['key'] ?? 'recap';

        return ['kind' => 'go', 'label' => 'Continuer', 'step' => $first];
    }

    /** Masque un identifiant : seuls les 4 derniers caractères restent visibles. */
    public static function maskId(string $value): string
    {
        $v = trim($value);
        if ($v === '' || $v === self::MISSING) {
            return $v;
        }
        $len = mb_strlen($v);

        return $len <= 4 ? '••••' : '••••' . mb_substr($v, -4);
    }

    /** Masque une adresse e-mail : première lettre et domaine. */
    public static function maskEmail(string $value): string
    {
        $v = trim($value);
        $at = strrpos($v, '@');
        if ($at === false || $at < 1) {
            return $v === self::MISSING ? $v : self::maskId($v);
        }

        return mb_substr($v, 0, 1) . '•••••' . substr($v, $at);
    }

    /**
     * Valeurs clés d'un bloc, résumées pour la carte (quatre au plus). Les identifiants et l'e-mail sont masqués : les
     * valeurs complètes ne sont montrées que dans la page du bloc.
     *
     * @param array<string, mixed> $block
     * @return list<array{label: string, value: string, missing: bool}>
     */
    public static function summary(string $key, array $block): array
    {
        $rows = [];
        foreach ((array) ($block['rows'] ?? []) as $row) {
            if (is_array($row)) {
                $rows[(string) ($row['label'] ?? '')] = trim((string) ($row['value'] ?? ''));
            }
        }
        $get = static fn (string $label): string => $rows[$label] ?? '';
        $item = static fn (string $label, string $value): array => ['label' => $label, 'value' => $value === '' ? self::MISSING : $value, 'missing' => $value === '' || $value === self::MISSING];
        $first = static fn (string $joined): string => trim(explode(' · ', $joined)[0]);
        $more = static function (string $joined): string {
            $parts = array_values(array_filter(array_map('trim', explode(' · ', $joined))));

            return count($parts) > 1 ? $parts[0] . ' +' . (count($parts) - 1) : ($parts[0] ?? '');
        };
        $present = static fn (string $v): string => $v === self::MISSING ? '' : $v;

        $items = match ($key) {
            DataCampaignBlocks::IDENTITY => (static function () use ($get, $item, $present): array {
                $name = trim($present($get('Prénom')) . ' ' . $present($get('Nom')));

                return [
                    $item('Nom', $name),
                    $item('Indicatif', $present($get('Indicatif'))),
                    $item('Surnom', $present($get('Surnom principal'))),
                ];
            })(),
            DataCampaignBlocks::IDENTIFIERS => [
                $item('Membre', self::maskId($present($get('Numéro de membre de la communauté')))),
                $item('Matricule', self::maskId($present($get('Matricule de service')))),
                $item('Trigramme', $present($get('Trigramme'))),
                $item('Radio', $present($get('Indicatif radio'))),
            ],
            DataCampaignBlocks::ASSIGNMENT => [
                $item('Grade', $present($get('Grade'))),
                $item('Unité', $more($present($get('Unités')))),
                $item('Fonction', $more($present($get('Fonctions')))),
                $item('Engagé le', $present($get('Date d’engagement'))),
            ],
            DataCampaignBlocks::QUALIFICATIONS => (static function () use ($block): array {
                $all = array_values(array_filter((array) ($block['rows'] ?? []), 'is_array'));
                $obtained = count(array_filter($all, static fn (array $r): bool => str_starts_with((string) ($r['value'] ?? ''), 'Obtenue')));
                if ($all === []) {
                    return [['label' => 'Inscrites', 'value' => 'Aucune', 'missing' => true]];
                }

                return [
                    ['label' => 'Inscrites', 'value' => (string) count($all), 'missing' => false],
                    ['label' => 'Obtenues', 'value' => (string) $obtained, 'missing' => false],
                ];
            })(),
            DataCampaignBlocks::HOURS => [
                $item('Jeu', $get('Heures de jeu')),
                ...($get('Heures de vol comptées') !== '' ? [$item('Vol', $get('Heures de vol comptées'))] : []),
            ],
            DataCampaignBlocks::CONTACT => [$item('E-mail', self::maskEmail($present($get('Adresse e-mail du compte'))))],
            default => [],
        };
        if ($items === []) {
            foreach (array_slice((array) ($block['rows'] ?? []), 0, 3) as $row) {
                if (is_array($row)) {
                    $items[] = $item((string) ($row['label'] ?? ''), $present(trim((string) ($row['value'] ?? ''))));
                }
            }
        }

        return $items;
    }

    /** Tracé (24x24, trait) de l'icône d'un bloc. */
    public static function iconPath(string $key): string
    {
        return [
            DataCampaignBlocks::IDENTITY => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5"/>',
            DataCampaignBlocks::IDENTIFIERS => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.5-1.6 1.7-2.4 3-2.4s2.5.8 3 2.4M14 10h4M14 14h3"/>',
            DataCampaignBlocks::ASSIGNMENT => '<path d="M12 3 4.5 6v5.5c0 4.4 3 7.6 7.5 9.5 4.5-1.9 7.5-5.1 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
            DataCampaignBlocks::QUALIFICATIONS => '<circle cx="12" cy="9" r="5"/><path d="m9 13.5-1.5 6.5 4.5-2.5 4.5 2.5-1.5-6.5"/>',
            DataCampaignBlocks::HOURS => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/>',
            DataCampaignBlocks::CONTACT => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        ][$key] ?? '<circle cx="12" cy="12" r="8"/>';
    }
}
