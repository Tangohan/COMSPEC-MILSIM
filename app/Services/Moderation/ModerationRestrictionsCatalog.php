<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Clés internes des modules soumis aux limitations de sanction (UI = libellés métier).
 */
final class ModerationRestrictionsCatalog
{
    public const KEY_FORUM = 'forum';

    public const KEY_DOCUMENTS = 'documents';

    public const KEY_TRAINING = 'training';

    public const KEY_ENLISTMENT = 'enlistment';

    public const KEY_ATAK = 'atak';

    public const KEY_COURRIER = 'courrier';

    /** @return list<string> */
    public static function moduleKeys(): array
    {
        return [
            self::KEY_DOCUMENTS,
            self::KEY_TRAINING,
            self::KEY_ENLISTMENT,
            self::KEY_ATAK,
            self::KEY_COURRIER,
        ];
    }

    /** @return array<string, string> slug => libellé affichage admin */
    public static function moduleLabels(): array
    {
        return [
            self::KEY_DOCUMENTS => 'Documents',
            self::KEY_TRAINING => 'Formations',
            self::KEY_ENLISTMENT => 'Candidatures / enrôlement',
            self::KEY_ATAK => 'ATAK / cartographie',
            self::KEY_COURRIER => 'Courrier officiel',
        ];
    }
}
