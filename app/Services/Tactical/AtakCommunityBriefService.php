<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\TenantAtakConfigRepository;
use App\Support\AtakCommunityBrief;

/**
 * Lecture et écriture des consignes et du kit de l'app Communauté depuis le back-office.
 * Le stockage reste celui du mod : deux textes (community_notes, community_kit) dans l'expérience de la communauté.
 *
 * Une note ou une entrée n'a pas d'identifiant stable, seulement sa place dans le texte : chaque écriture porte donc
 * l'empreinte du texte qu'elle a sous les yeux. Si le texte a changé entre-temps, rien n'est écrit (conflit).
 */
final class AtakCommunityBriefService
{
    public const NOTES = 'community_notes';

    public const KIT = 'community_kit';

    public function __construct(
        private ?AtakExperienceService $experience = null,
        private ?TenantAtakConfigRepository $config = null,
    ) {
        $this->experience ??= new AtakExperienceService();
        $this->config ??= new TenantAtakConfigRepository();
    }

    public function ready(): bool
    {
        return $this->config->isExperienceSchemaReady();
    }

    /**
     * @return array{notes: list<array{kind: string, title: string, body: string}>, kit: list<array{classes: list<string>, count: int, label: string}>, notes_rev: string, kit_rev: string, updated_at: string, guide: string, unread: array{notes_over: int, notes_cut: int, kit: list<string>}, fit: array{bytes: int, fits: bool, notes_kept: int, kit_kept: int}}
     */
    public function state(int $tenantId): array
    {
        $pack = $this->experience->get($tenantId);
        $notesText = (string) ($pack['settings'][self::NOTES] ?? '');
        $kitText = (string) ($pack['settings'][self::KIT] ?? '');
        $notes = AtakCommunityBrief::parseNotes($notesText);
        $kit = AtakCommunityBrief::parseKit($kitText);

        return [
            'notes' => $notes,
            'kit' => $kit,
            'notes_rev' => self::revision($notesText),
            'kit_rev' => self::revision($kitText),
            'updated_at' => $pack['updated_at'],
            'guide' => $pack['guide'],
            'unread' => AtakCommunityBrief::unread($notesText, $kitText),
            'fit' => AtakCommunityBrief::phoneFit($notes, $kit),
        ];
    }

    public static function revision(string $text): string
    {
        return substr(sha1($text), 0, 16);
    }

    /**
     * Applique une modification à la liste enregistrée, si elle n'a pas changé depuis l'affichage.
     *
     * @param self::NOTES|self::KIT $field
     * @param callable(list<array<string, mixed>>): list<array<string, mixed>> $change lève \DomainException pour refuser
     * @return array{ok: bool, status: int, message: string, conflict?: bool}
     */
    public function change(int $tenantId, string $field, string $revision, callable $change): array
    {
        $settings = $this->experience->get($tenantId)['settings'];
        $text = (string) ($settings[$field] ?? '');
        if (!hash_equals(self::revision($text), $revision)) {
            return [
                'ok' => false,
                'status' => 409,
                'conflict' => true,
                'message' => 'Cette liste vient d’être modifiée par ailleurs : rien n’a été enregistré. Voici la version à jour, refaites votre changement.',
            ];
        }
        $notesText = (string) ($settings[self::NOTES] ?? '');
        $kitText = (string) ($settings[self::KIT] ?? '');
        $notes = AtakCommunityBrief::parseNotes($notesText);
        $kit = AtakCommunityBrief::parseKit($kitText);
        $before = AtakCommunityBrief::phoneFit($notes, $kit)['bytes'];

        try {
            if ($field === self::NOTES) {
                /** @var list<array{kind: string, title: string, body: string}> $notes */
                $notes = $change($notes);
                $source = AtakCommunityBrief::serializeNotes($notes);
            } else {
                /** @var list<array{classes: list<string>, count: int, label: string}> $kit */
                $kit = $change($kit);
                $source = AtakCommunityBrief::serializeKit($kit);
            }
        } catch (\DomainException $e) {
            return ['ok' => false, 'status' => 422, 'message' => $e->getMessage()];
        }
        $after = AtakCommunityBrief::phoneFit($notes, $kit);
        // Une liste déjà trop lourde reste modifiable, tant que la modification ne l'alourdit pas.
        if (!$after['fits'] && $after['bytes'] > $before) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => sprintf('Le téléphone reçoit %d octets au plus, notes d’abord, puis kit : la suite serait perdue sans prévenir. Raccourcissez ou retirez une note.', AtakCommunityBrief::MAX_PHONE_BYTES),
            ];
        }
        if (mb_strlen($source) > AtakCommunityBrief::MAX_SOURCE) {
            return ['ok' => false, 'status' => 422, 'message' => 'Cette liste est trop longue pour être enregistrée. Retirez une entrée.'];
        }
        if (!$this->experience->putChecked($tenantId, [$field => $source])) {
            return ['ok' => false, 'status' => 500, 'message' => 'L’enregistrement a échoué : rien n’a été modifié. Réessayez dans un instant.'];
        }

        return ['ok' => true, 'status' => 200, 'message' => ''];
    }

    /**
     * Ajoute (place null) ou remplace une ligne.
     *
     * @template T of array
     * @param list<T> $list
     * @param T $row
     * @return list<T>
     */
    public static function put(array $list, ?int $index, array $row, int $max, string $fullMessage): array
    {
        if ($index === null) {
            if (count($list) >= $max) {
                throw new \DomainException($fullMessage);
            }
            $list[] = $row;

            return $list;
        }
        self::assertIndex($list, $index);
        $list[$index] = $row;

        return $list;
    }

    /**
     * @template T of array
     * @param list<T> $list
     * @return list<T>
     */
    public static function remove(array $list, int $index): array
    {
        self::assertIndex($list, $index);
        array_splice($list, $index, 1);

        return $list;
    }

    /**
     * Monte (-1) ou descend (+1) une note. Avec un filtre de genre, elle change de place avec la note voisine
     * de ce genre : la voisine affichée, pas une note masquée (sinon le clic semblerait sans effet).
     *
     * @param list<array{kind: string, title: string, body: string}> $notes
     * @return array{0: list<array{kind: string, title: string, body: string}>, 1: int} liste et nouvelle place
     */
    public static function move(array $notes, int $index, int $step, string $kind = ''): array
    {
        self::assertIndex($notes, $index);
        $target = $index + $step;
        while ($kind !== '' && isset($notes[$target]) && $notes[$target]['kind'] !== $kind) {
            $target += $step;
        }
        if (!isset($notes[$target])) {
            throw new \DomainException($step < 0 ? 'Cette note est déjà la première.' : 'Cette note est déjà la dernière.');
        }
        [$notes[$index], $notes[$target]] = [$notes[$target], $notes[$index]];

        return [$notes, $target];
    }

    /**
     * Une même classe en double ferait compter le même objet deux fois.
     *
     * @param list<array{classes: list<string>, count: int, label: string}> $kit
     * @param list<string> $classes
     */
    public static function assertNoDuplicate(array $kit, array $classes, ?int $ignoreIndex): void
    {
        $wanted = array_map('strtolower', $classes);
        foreach ($kit as $i => $entry) {
            if ($i !== $ignoreIndex && array_intersect($wanted, array_map('strtolower', $entry['classes'])) !== []) {
                throw new \DomainException('Cet objet est déjà dans le kit : modifiez plutôt son entrée.');
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $list
     */
    private static function assertIndex(array $list, int $index): void
    {
        if (!isset($list[$index])) {
            throw new \DomainException('Cet élément n’existe plus. Rechargez la page.');
        }
    }
}
