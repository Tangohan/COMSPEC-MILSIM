<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Consignes, ordres, tips et kit obligatoire de la communauté, lus par l'app Communauté du téléphone
 * (GET /api/atak/community-brief, commande GetCommunityBrief de COMSPEC Link).
 *
 * Saisis dans Ressources › Consignes et kit ATAK (AdminAtakBriefController) et rangés en texte avec l'expérience de la
 * communauté (community_notes, community_kit). Une ligne par note : « genre | titre | texte ». Kit : une entrée par ligne
 * ou séparée par une virgule : « classe », « classe*2 », « classeA/classeB*2 », suivie de « : libellé » au besoin.
 * Texte brut seulement : le jeu l'affiche sans interpréter de balise.
 */
final class AtakCommunityBrief
{
    public const KINDS = ['consigne', 'ordre', 'tip'];

    public const MAX_NOTES = 40;

    public const MAX_TITLE = 60;

    public const MAX_BODY = 600;

    public const MAX_KIT = 40;

    public const MAX_COUNT = 99;

    /** Taille du texte gardé pour chaque champ de saisie. */
    public const MAX_SOURCE = 30000;

    /** Classes acceptées pour une même entrée du kit (objet + variantes). */
    public const MAX_VARIANTS = 8;

    /** La DLL COMSPEC Link coupe sa réponse à ce volume, à la ligne près : notes d'abord, puis kit (PollOkClipped). */
    public const MAX_PHONE_BYTES = 7996;

    /**
     * @param array<string, mixed> $settings
     * @return array{notes: list<array{id: int, kind: string, title: string, body: string, updated_at: string}>, kit: list<array{classes: list<string>, count: int, label: string}>, updated_at: string}
     */
    public static function payload(array $settings, string $updatedAt): array
    {
        $notes = [];
        foreach (self::parseNotes((string) ($settings['community_notes'] ?? '')) as $i => $note) {
            $notes[] = ['id' => $i + 1] + $note + ['updated_at' => $updatedAt];
        }

        return [
            'notes' => $notes,
            'kit' => self::parseKit((string) ($settings['community_kit'] ?? '')),
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @return list<array{kind: string, title: string, body: string}>
     */
    public static function parseNotes(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $note = self::noteFromLine($line);
            if ($note === null) {
                continue;
            }
            $out[] = ['kind' => $note['kind'], 'title' => mb_substr($note['title'], 0, self::MAX_TITLE), 'body' => mb_substr($note['body'], 0, self::MAX_BODY)];
            if (count($out) >= self::MAX_NOTES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array{kind: string, title: string, body: string}|null
     */
    private static function noteFromLine(string $line): ?array
    {
        $parts = array_map(self::clean(...), explode('|', $line));
        $kind = 'consigne';
        if (count($parts) > 1 && in_array(mb_strtolower($parts[0]), self::KINDS, true)) {
            $kind = mb_strtolower((string) array_shift($parts));
        }
        $title = count($parts) > 1 ? (string) array_shift($parts) : '';
        $body = trim(implode(' | ', $parts));

        return $title === '' && $body === '' ? null : ['kind' => $kind, 'title' => $title, 'body' => $body];
    }

    /**
     * @return list<array{classes: list<string>, count: int, label: string}>
     */
    public static function parseKit(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/u', $text) ?: [] as $entry) {
            [$spec, $label] = array_pad(explode(':', $entry, 2), 2, '');
            $spec = trim($spec);
            $count = 1;
            if (preg_match('/^(.*)\*\s*(\d+)$/u', $spec, $m) === 1) {
                $spec = trim($m[1]);
                $count = max(1, min(self::MAX_COUNT, (int) $m[2]));
            }
            $classes = array_map('trim', explode('/', $spec));
            foreach ($classes as $class) {
                if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $class) !== 1) {
                    continue 2;
                }
            }
            $out[] = ['classes' => $classes, 'count' => $count, 'label' => mb_substr(self::clean($label), 0, self::MAX_TITLE)];
            if (count($out) >= self::MAX_KIT) {
                break;
            }
        }

        return $out;
    }

    /**
     * Ce que le texte enregistré contient et que le jeu ne lit pas : une réécriture depuis le back-office le perdrait.
     *
     * @return array{notes_over: int, notes_cut: int, kit: list<string>}
     */
    public static function unread(string $notesText, string $kitText): array
    {
        $count = 0;
        $over = 0;
        $cut = 0;
        foreach (preg_split('/\R/u', $notesText) ?: [] as $line) {
            $note = self::noteFromLine($line);
            if ($note === null) {
                continue;
            }
            if (++$count > self::MAX_NOTES) {
                ++$over;
            } elseif (mb_strlen($note['title']) > self::MAX_TITLE || mb_strlen($note['body']) > self::MAX_BODY) {
                ++$cut;
            }
        }
        $kit = [];
        $read = 0;
        foreach (preg_split('/[\r\n,;]+/u', $kitText) ?: [] as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (self::parseKit($entry) === [] || $read >= self::MAX_KIT) {
                $kit[] = $entry;
                continue;
            }
            ++$read;
        }

        return ['notes_over' => $over, 'notes_cut' => $cut, 'kit' => $kit];
    }

    public static function isClass(string $class): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $class) === 1;
    }

    /**
     * Note saisie dans le back-office, ramenée à ce que parseNotes relira à l'identique. Rien n'est coupé en silence.
     *
     * @return array{kind: string, title: string, body: string}
     * @throws \DomainException message à afficher tel quel
     */
    public static function validNote(string $kind, string $title, string $body): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \DomainException('Choisissez un genre : consigne, ordre ou tip.');
        }
        $title = self::field($title);
        $body = self::field($body);
        if ($title === '' && $body === '') {
            throw new \DomainException('Écrivez au moins un titre ou un texte.');
        }
        if (str_contains($title, '|')) {
            throw new \DomainException('Le titre ne peut pas contenir le caractère | : il sépare le titre du texte.');
        }
        // Le séparateur est relu « a | b » : on l'écrit ainsi tout de suite, pour compter la vraie longueur.
        $body = trim(implode(' | ', array_map('trim', explode('|', $body))));
        if (mb_strlen($title) > self::MAX_TITLE) {
            throw new \DomainException(sprintf('Titre trop long : %d caractères au plus.', self::MAX_TITLE));
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw new \DomainException(sprintf('Texte trop long : %d caractères au plus.', self::MAX_BODY));
        }
        $note = ['kind' => $kind, 'title' => $title, 'body' => $body];
        if (self::parseNotes(self::serializeNotes([$note])) !== [$note]) {
            throw new \DomainException('Ce texte ne peut pas être enregistré tel quel. Retirez les caractères spéciaux.');
        }

        return $note;
    }

    /**
     * Entrée du kit saisie dans le back-office, ramenée à ce que parseKit relira à l'identique.
     *
     * @param list<string> $classes objet demandé, puis variantes acceptées
     * @return array{classes: list<string>, count: int, label: string}
     * @throws \DomainException message à afficher tel quel
     */
    public static function validKitEntry(array $classes, int $count, string $label): array
    {
        $unique = [];
        foreach ($classes as $class) {
            if (!self::isClass($class)) {
                throw new \DomainException('Nom de classe invalide : lettres sans accent, chiffres et _ seulement, 64 caractères au plus.');
            }
            $unique[strtolower($class)] ??= $class;
        }
        $classes = array_values($unique);
        if ($classes === []) {
            throw new \DomainException('Choisissez un objet du catalogue, ou saisissez sa classe dans l’option avancée.');
        }
        if (count($classes) > self::MAX_VARIANTS) {
            throw new \DomainException(sprintf('Trop de variantes : %d classes au plus pour une entrée.', self::MAX_VARIANTS));
        }
        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new \DomainException(sprintf('Quantité invalide : entre 1 et %d.', self::MAX_COUNT));
        }
        $label = self::field($label);
        if (preg_match('/[,;]/', $label) === 1) {
            throw new \DomainException('Le libellé ne peut pas contenir de virgule ni de point-virgule : ils séparent les entrées du kit.');
        }
        if (mb_strlen($label) > self::MAX_TITLE) {
            throw new \DomainException(sprintf('Libellé trop long : %d caractères au plus.', self::MAX_TITLE));
        }
        $entry = ['classes' => $classes, 'count' => $count, 'label' => $label];
        if (self::parseKit(self::serializeKit([$entry])) !== [$entry]) {
            throw new \DomainException('Cette entrée ne peut pas être enregistrée telle quelle. Retirez les caractères spéciaux.');
        }

        return $entry;
    }

    /**
     * @param list<array{kind: string, title: string, body: string}> $notes
     */
    public static function serializeNotes(array $notes): string
    {
        return implode("\n", array_map(static fn (array $n): string => $n['kind'] . ' | ' . $n['title'] . ' | ' . $n['body'], $notes));
    }

    /**
     * @param list<array{classes: list<string>, count: int, label: string}> $kit
     */
    public static function serializeKit(array $kit): string
    {
        return implode("\n", array_map(static function (array $e): string {
            $spec = implode('/', $e['classes']) . ($e['count'] > 1 ? '*' . $e['count'] : '');

            return $e['label'] === '' ? $spec : $spec . ' : ' . $e['label'];
        }, $kit));
    }

    /**
     * Ce qui arrive vraiment au téléphone, calculé comme la DLL (SimplifyCommunityBriefJson puis Utf8ClipLines) :
     * une ligne par note puis par entrée du kit, et plus rien au-delà de MAX_PHONE_BYTES.
     *
     * @param list<array{kind: string, title: string, body: string}> $notes
     * @param list<array{classes: list<string>, count: int, label: string}> $kit
     * @return array{bytes: int, fits: bool, notes_kept: int, kit_kept: int}
     */
    public static function phoneFit(array $notes, array $kit): array
    {
        $field = static fn (string $v): string => $v === '' ? ' ' : $v;
        $lines = [];
        foreach ($notes as $n) {
            $lines[] = ['n', "N\t" . $n['kind'] . "\t" . $field($n['title']) . "\t" . $field($n['body'])];
        }
        foreach ($kit as $e) {
            $lines[] = ['k', "K\t" . implode(',', $e['classes']) . "\t" . $e['count'] . "\t" . $field($e['label'])];
        }
        $total = 0;
        foreach ($lines as $i => [, $line]) {
            $total += strlen($line) + ($i > 0 ? 1 : 0);
        }
        $kept = ['n' => 0, 'k' => 0];
        $used = 0;
        foreach ($lines as [$type, $line]) {
            $used += strlen($line) + 1;
            if ($total > self::MAX_PHONE_BYTES && $used > self::MAX_PHONE_BYTES) {
                break;
            }
            ++$kept[$type];
        }

        return ['bytes' => $total, 'fits' => $total <= self::MAX_PHONE_BYTES, 'notes_kept' => $kept['n'], 'kit_kept' => $kept['k']];
    }

    /**
     * Une ligne, sans balise cachée : un retour à la ligne devient une espace (sinon il créerait une note de plus),
     * et un texte que strip_tags raccourcirait (« a<b suite ») est refusé plutôt que perdu à la relecture.
     */
    private static function field(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new \DomainException('Texte illisible : encodage invalide.');
        }
        $value = trim((string) preg_replace('/\R+/u', ' ', $value));
        if (strip_tags($value) !== $value) {
            throw new \DomainException('Retirez les balises : le caractère < suivi d’un mot serait lu comme une balise, et le texte après lui perdu.');
        }

        return self::clean($value);
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($value)));
    }
}
