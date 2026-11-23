<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseDocumentLocks;
use App\Support\SseDocumentTemplates;
use App\Support\SseRichText;
use PHPUnit\Framework\TestCase;

/**
 * Texte verrouillé : le serveur assainit data-lock (valeur 1 seulement, aucun autre data-*) et refuse tout enregistrement qui
 * modifie, déplace ou retire un bloc verrouillé de la version précédente ou du modèle.
 */
final class SseDocumentLocksTest extends TestCase
{
    public function testSanitizerKeepsOnlyLockOne(): void
    {
        $out = SseRichText::sanitize('<p data-lock="1" data-x="a" onclick="x()">A</p><p data-lock="0">B</p><p data-lock="javascript:1">C</p><img src=x data-lock="1">');
        self::assertStringContainsString('<p data-lock="1">A</p>', $out);
        self::assertStringNotContainsString('data-x', $out);
        self::assertStringNotContainsString('onclick', $out);
        self::assertSame(1, substr_count($out, 'data-lock'));
    }

    public function testTemplatesLockTheirHeaderAndMandatoryNotice(): void
    {
        foreach (array_keys(SseDocumentTemplates::CATALOGUE) as $code) {
            $blocks = SseDocumentLocks::blocks(SseDocumentTemplates::body($code));
            self::assertGreaterThanOrEqual(2, count($blocks), $code);
            self::assertStringStartsWith('<table', $blocks[0], $code);
        }
    }

    public function testUntouchedLocksPass(): void
    {
        $old = SseDocumentTemplates::body('flash');
        self::assertSame([], SseDocumentLocks::violations($old, $old . '<p>Ajout libre</p>'));
        // La valeur d'un champ dynamique change à l'affichage : pas une violation.
        $new = str_replace('Attribuée à l’enregistrement', 'SSE-FL-26-0001', $old);
        self::assertSame([], SseDocumentLocks::violations($old, $new));
    }

    public function testEditingOrUnlockingOrRemovingALockedBlockIsRefused(): void
    {
        $old = SseDocumentTemplates::body('flash');
        self::assertNotSame([], SseDocumentLocks::violations($old, str_replace('Rédacteur', 'Auteur', $old)), 'texte modifié');
        self::assertNotSame([], SseDocumentLocks::violations($old, str_replace(' data-lock="1"', '', $old)), 'verrou retiré');
        self::assertNotSame([], SseDocumentLocks::violations($old, '<p>Tout remplacé</p>'), 'bloc supprimé');
    }

    public function testOrderIsEnforced(): void
    {
        $a = '<p data-lock="1">Premier</p><p data-lock="1">Second</p>';
        $b = '<p data-lock="1">Second</p><p data-lock="1">Premier</p>';
        self::assertNotSame([], SseDocumentLocks::violations($a, $b));
        self::assertSame([], SseDocumentLocks::violations($a, '<p>x</p>' . $a));
    }

    public function testNoLockInReferenceMeansNothingToEnforce(): void
    {
        self::assertSame([], SseDocumentLocks::violations('<p>Libre</p>', '<p>Autre</p>'));
    }
}
