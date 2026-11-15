<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAttachmentRepository;
use App\Repositories\SseTagRepository;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseFieldNoteTagCatalog as Cat;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFieldNoteTagsFixture as Fx;
use Tests\Support\SseFieldNoteTagsSqlite as Sq;

/**
 * Référentiel des fiches de renseignement sur une vraie base sqlite (aucun double) : catalogue livré et réglages par
 * communauté, étiquettes propres, fusion, archivage, suppression, étiquetage des fiches, filtres de la liste, règles par type,
 * import et export, champs ajoutés à l'API du jeu, catalogue unique partagé avec la fenêtre « Rattacher », base non migrée.
 */
final class SseFieldNoteTagServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $ids = [];

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1, 'sse_tenant_id' => 1];
        $this->pdo = Sq::open();
        $this->ids = Fx::seed($this->pdo);
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        Sq::close();
        $_SESSION = [];
    }

    private function service(): SseFieldNoteTagService
    {
        return new SseFieldNoteTagService(new SseTagRepository(), function (array $entry): void {
            $this->audit[] = $entry;
        });
    }

    private function scalar(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    public function testCommunityReferentialAndUntouchedNewCommunity(): void
    {
        $s = $this->service();
        $stats = $s->stats(1);
        self::assertSame(35, $stats['active']);
        self::assertSame(8, $stats['custom']);
        self::assertSame(3, $stats['archived']);
        self::assertSame(60, $stats['notes']);
        self::assertSame(4, $stats['kinds']);
        // Communauté sans réglage : le catalogue livré, la grille d'analyse désactivée d'origine.
        $fresh = $s->stats(2);
        self::assertSame(82, $fresh['active']);
        self::assertSame(0, $fresh['custom']);
        self::assertSame(13, $fresh['disabled']);
        self::assertSame(5, $fresh['kinds']);
        self::assertSame('disabled', $s->find(2, 'political')['status']);
        self::assertNull($s->find(2, 'route-watch'), 'une étiquette propre ne sort pas de sa communauté');
        self::assertSame('Besoin prioritaire du chef de corps', $s->find(1, 'pir')['label_fr']);
        self::assertSame('Besoin prioritaire en renseignement', $s->find(2, 'pir')['label_fr']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM sse_attach_tags WHERE tenant_id = 2'));
    }

    public function testCreateRenameAndRefuseBadInput(): void
    {
        $s = $this->service();
        $r = $s->saveTag(1, ['code' => 'night ops', 'family' => 'theme', 'label_fr' => 'Opérations de nuit', 'label_en' => 'Night operations', 'desc_fr' => 'Activité constatée de nuit.', 'color' => 'blue', 'sort' => 0, 'qualifier' => ''], Fx::ACTOR);
        self::assertTrue($r['ok'], implode(' ', $r['errors']));
        self::assertSame('night-ops', $r['key']);
        $tag = $s->find(1, 'night-ops');
        self::assertSame('NIGHT OPS', $tag['code']);
        self::assertSame('custom', $tag['origin']);
        self::assertSame('active', $tag['status']);
        self::assertSame('field_note.tag.create', $this->audit[0]['action']);
        self::assertSame(1, $this->audit[0]['tenant_id']);
        self::assertSame('all', $this->scalar("SELECT scope FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'night-ops'"), 'une étiquette propre vaut aussi pour les rattachements');

        // Renommer une étiquette livrée : le code et la famille ne bougent pas, la communauté 2 n'en voit rien.
        $r = $s->saveTag(1, ['key' => 'humint', 'code' => 'PIRATE', 'family' => 'product', 'label_fr' => 'Source humaine', 'label_en' => 'Human source', 'desc_fr' => 'Entretien.', 'color' => 'green', 'sort' => 5], Fx::ACTOR);
        self::assertTrue($r['ok']);
        $tag = $s->find(1, 'humint');
        self::assertSame(['HUMINT', 'discipline', 'Source humaine', 'green', 5, 'active'], [$tag['code'], $tag['family'], $tag['label_fr'], $tag['color'], $tag['sort'], $tag['status']]);
        self::assertSame('Renseignement d’origine humaine', $s->find(2, 'humint')['label_fr']);
        self::assertSame('field_note', $this->scalar("SELECT scope FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'humint'"));

        foreach ([
            [['code' => 'HUMINT', 'family' => 'theme', 'label_fr' => 'Doublon', 'color' => 'blue'], 'existe déjà'],
            [['code' => 'ARMEMENT', 'family' => 'category', 'label_fr' => 'Doublon', 'color' => 'blue'], 'existe déjà'],
            [['code' => 'FLASH', 'family' => 'theme', 'label_fr' => 'Doublon', 'color' => 'blue'], 'existe déjà'],
            [['code' => 'X', 'family' => 'theme', 'label_fr' => 'Trop court', 'color' => 'blue'], 'Le code s’écrit'],
            [['code' => 'OK CODE', 'family' => 'precedence', 'label_fr' => 'Famille fermée', 'color' => 'blue'], 'Choisissez une famille'],
            [['code' => 'OK CODE', 'family' => 'theme', 'label_fr' => '', 'color' => 'blue'], 'libellé français'],
            [['code' => 'OK CODE', 'family' => 'theme', 'label_fr' => 'Couleur', 'color' => 'fuchsia'], 'couleur'],
            [['code' => 'INTERPOL', 'family' => 'theme', 'label_fr' => 'Avis', 'color' => 'blue'], 'organisation réelle'],
            [['code' => 'LIAISON', 'family' => 'theme', 'label_fr' => 'Liaison avec le FBI', 'color' => 'blue'], 'organisation réelle'],
        ] as [$input, $needle]) {
            $r = $s->saveTag(1, $input + ['sort' => 0, 'qualifier' => ''], Fx::ACTOR);
            self::assertFalse($r['ok'], $needle);
            self::assertStringContainsString($needle, implode(' ', $r['errors']));
        }
        self::assertNull($s->find(1, 'ok-code'));
        self::assertFalse($s->saveTag(1, ['key' => 'inconnue', 'label_fr' => 'X', 'color' => 'blue'], Fx::ACTOR)['ok']);
    }

    public function testStatusArchiveDeleteAndIdor(): void
    {
        $s = $this->service();
        // Une étiquette utilisée ne se supprime pas : elle s'archive, les fiches la gardent.
        $r = $s->deleteTag(1, 'route-watch', Fx::ACTOR);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('archivez-la', $r['errors'][0]);
        self::assertTrue($s->setStatus(1, 'route-watch', 'archived', Fx::ACTOR)['ok']);
        self::assertSame('archived', $s->find(1, 'route-watch')['status']);
        $tags = $s->tagsForNotes(1, [$this->ids['long']])[$this->ids['long']];
        self::assertContains('ROUTE WATCH', array_column($tags, 'code'), 'la fiche garde son étiquette archivée');
        self::assertNotContains('route-watch', array_keys($s->pickerFamilies(1)['theme']['tags'] ? array_column($s->pickerFamilies(1)['theme']['tags'], null, 'key') : []));
        // Une étiquette livrée ne s'archive ni ne se supprime : elle se désactive.
        self::assertTrue($s->setStatus(1, 'sigint', 'archived', Fx::ACTOR)['ok']);
        self::assertSame('disabled', $s->find(1, 'sigint')['status']);
        self::assertFalse($s->deleteTag(1, 'sigint', Fx::ACTOR)['ok']);
        // Étiquette propre sans fiche : suppression réelle.
        $s->saveTag(1, ['code' => 'TEMP TAG', 'family' => 'theme', 'label_fr' => 'Provisoire', 'color' => 'slate', 'sort' => 0, 'qualifier' => ''], Fx::ACTOR);
        self::assertTrue($s->deleteTag(1, 'temp-tag', Fx::ACTOR)['ok']);
        self::assertNull($s->find(1, 'temp-tag'));
        // Clé d'une autre communauté : introuvable, rien n'est touché.
        foreach ([$s->setStatus(2, 'route-watch', 'archived', Fx::ACTOR), $s->deleteTag(2, 'checkpoint', Fx::ACTOR), $s->merge(2, 'checkpoint', 'safe-house', Fx::ACTOR), $s->saveTag(2, ['key' => 'checkpoint', 'label_fr' => 'Pris', 'color' => 'red'], Fx::ACTOR)] as $r) {
            self::assertFalse($r['ok']);
        }
        self::assertSame('Point de contrôle', $s->find(1, 'checkpoint')['label_fr']);
        self::assertSame('active', $s->find(1, 'checkpoint')['status']);
        // La dernière catégorie active ne se désactive pas.
        foreach (['cat-armement', 'cat-person', 'cat-logist', 'cat-mouv', 'cat-secur', 'narco'] as $key) {
            self::assertTrue($s->setStatus(1, $key, 'disabled', Fx::ACTOR)['ok'], $key);
        }
        self::assertFalse($s->setStatus(1, 'frontiere', 'disabled', Fx::ACTOR)['ok']);
        self::assertFalse($s->setStatus(1, 'frontiere', 'n-importe', Fx::ACTOR)['ok']);
    }

    public function testMergeMovesNotesAndArchivesTheSource(): void
    {
        $s = $this->service();
        $before = $s->usage(1);
        self::assertGreaterThan(0, $before['checkpoint']);
        $both = (int) $this->scalar("SELECT COUNT(*) FROM sse_tag_links a JOIN sse_tag_links b ON b.object_id = a.object_id AND b.tenant_id = a.tenant_id WHERE a.tag_slug = 'checkpoint' AND b.tag_slug = 'route-watch'");
        $r = $s->merge(1, 'checkpoint', 'route-watch', Fx::ACTOR);
        self::assertTrue($r['ok']);
        self::assertSame($before['checkpoint'], $r['moved']);
        $after = $s->usage(1);
        self::assertSame(0, (int) ($after['checkpoint'] ?? 0));
        self::assertSame($before['route-watch'] + $before['checkpoint'] - $both, $after['route-watch']);
        self::assertSame(['archived', 'route-watch'], [$s->find(1, 'checkpoint')['status'], $s->find(1, 'checkpoint')['merged_into']]);
        self::assertSame('field_note.tag.merge', end($this->audit)['action']);
        // Refus : familles différentes, même étiquette, catégorie livrée (inscrite sur les fiches).
        self::assertFalse($s->merge(1, 'humint', 'pir', Fx::ACTOR)['ok']);
        self::assertFalse($s->merge(1, 'humint', 'humint', Fx::ACTOR)['ok']);
        self::assertFalse($s->merge(1, 'cat-armement', 'narco', Fx::ACTOR)['ok']);
        // Aucune fiche modifiée : les catégories d'origine sont intactes.
        self::assertSame(60, (int) $this->scalar("SELECT COUNT(*) FROM sse_field_notes WHERE tenant_id = 1 AND themes LIKE '[%'"));
    }

    public function testResetFamilyRestoresTheDeliveredCatalog(): void
    {
        $s = $this->service();
        self::assertSame('disabled', $s->find(1, 'geoint')['status']);
        $r = $s->resetFamily(1, 'discipline', Fx::ACTOR);
        self::assertTrue($r['ok']);
        self::assertGreaterThan(0, $r['reset']);
        self::assertSame('active', $s->find(1, 'geoint')['status']);
        self::assertSame('Besoin prioritaire du chef de corps', $s->find(1, 'pir')['label_fr'], 'les autres familles ne bougent pas');
        self::assertNotNull($s->find(1, 'route-watch'), 'les étiquettes propres restent');
        self::assertFalse($s->resetFamily(1, 'evaluation', Fx::ACTOR)['ok']);
    }

    public function testTaggingANoteSelectionQualifiersAndMarking(): void
    {
        $s = $this->service();
        $noteId = $this->ids['notes'][5];
        self::assertSame([], $s->tagsForNotes(1, [$noteId]));
        $picked = $s->normalizeSelection(1, ['humint', 'PIR', 'rel-to', 'perishable', 'geoint', 'op-hiver', 'inconnue', 'cat-armement', 'humint', 'handle-via', 'orcon'], [
            'pir' => ' 2b ', 'rel-to' => 'usa, altis', 'perishable' => '2026-11-20T18:00', 'humint' => 'ignoré',
        ]);
        self::assertSame(['humint', 'pir', 'rel-to', 'perishable', 'orcon'], array_column($picked['selection'], 'slug'), 'désactivées, archivées, inconnues et catégories livrées écartées');
        self::assertSame(['', '2B', 'USA, ALTIS', '2026-11-20 18:00', ''], array_column($picked['selection'], 'qualifier'));
        self::assertTrue($s->setNoteTags(1, $noteId, $picked['selection'], Fx::ACTOR));
        self::assertSame('field_note.tags.set', end($this->audit)['action']);
        self::assertSame($noteId, end($this->audit)['object_id']);

        $note = $s->decorate(1, ['id' => $noteId, 'classification' => 'confidentiel', 'urgency' => 'urgent', 'themes' => ['ARMEMENT'], 'intel_source' => 'TECHINT', 'source_reliability' => 'B', 'info_credibility' => 2, 'note_kind' => 'FRC']);
        self::assertSame(['HUMINT', 'PIR 2B', 'REL TO USA, ALTIS', 'ORCON', 'PERISHABLE'], array_column($note['tags'], 'text'));
        // REL TO remplace le NOFORN du niveau ; ORCON s'ajoute.
        self::assertSame('SECRET//REL TO USA, ALTIS/ORCON', $note['marking']);
        self::assertSame('IMMEDIATE', $note['precedence']['code']);
        self::assertSame(['TECHINT', 'HUMINT'], array_column($note['tag_disciplines'], 'code'), 'le mode de recueil de la fiche ouvre la liste');
        self::assertSame(['reliability' => 'Usually reliable', 'credibility' => 'Probably true'], $note['evaluation_us']);
        self::assertSame('Fiche d’entretien', $note['note_kind_label']);
        self::assertStringContainsString('valable jusqu’au 20/11/2026 à 18:00', $note['tag_groups']['validity'][0]['note']);

        // Autres marquages : aucun droit ne change, c'est une ligne de texte.
        self::assertSame('SECRET//NOFORN', $s->marking(1, 'confidentiel', []));
        self::assertSame('CUI', $s->marking(1, 'interne', []));
        self::assertSame('TOP SECRET//SCI//NOFORN/ORCON', $s->marking(1, 'tres_restreint', [['code' => 'ORCON', 'qualifier' => '']]));
        self::assertSame('SECRET//NOFORN/REL TO USA, ALTIS', $s->marking(1, 'confidentiel', [['code' => 'NOFORN', 'qualifier' => ''], ['code' => 'REL TO', 'qualifier' => '']]));
        self::assertSame('CUI//SP-CTI//FOUO', $s->marking(1, 'interne', [['code' => 'CUI', 'qualifier' => 'SP-CTI'], ['code' => 'FOUO', 'qualifier' => ''], ['code' => 'HANDLE VIA', 'qualifier' => 'X']]));

        // Un complément qui cite une organisation réelle est retiré et signalé.
        $picked = $s->normalizeSelection(1, ['rel-to'], ['rel-to' => 'NATO']);
        self::assertSame('', $picked['selection'][0]['qualifier']);
        self::assertStringContainsString('organisation réelle', $picked['errors'][0]);
        // Le jeu envoie des codes ou des objets ; une étiquette déjà portée reste permise même archivée.
        $picked = $s->normalizeSelection(1, [['code' => 'sir', 'qualifier' => '4'], 'op-hiver'], [], ['op-hiver' => '']);
        self::assertSame(['sir', 'op-hiver'], array_column($picked['selection'], 'slug'));
        // Seize étiquettes au plus ; une fiche d'une autre communauté ne reçoit rien de celle-ci.
        self::assertLessThan(Cat::TAGS_PER_NOTE + 1, count($s->normalizeSelection(2, array_keys(Cat::delivered()))['selection']));
        self::assertSame([], $s->tagsForNotes(2, [$noteId]));
        self::assertSame([], $s->normalizeSelection(2, ['route-watch'])['selection']);
    }

    public function testListFiltersByTagFamilyAndCode(): void
    {
        $pir = Fx::listData(1, ['tag' => 'pir'])['notes'];
        self::assertGreaterThan(0, count($pir));
        foreach ($pir as $note) {
            self::assertContains('PIR', array_column($note['tags'], 'code'));
        }
        $family = Fx::listData(1, ['tag_family' => 'dissemination'])['notes'];
        foreach ($family as $note) {
            self::assertNotSame([], $note['tag_groups']['dissemination'] ?? []);
        }
        self::assertLessThan(60, count($family));
        self::assertGreaterThan(0, count($family));
        // Recherche par code : les fiches qui portent LTIOV, sans que le mot soit dans leur texte.
        $ltiov = Fx::listData(1, ['q' => 'ltiov'])['notes'];
        self::assertGreaterThan(0, count($ltiov));
        foreach ($ltiov as $note) {
            self::assertContains('LTIOV', array_column($note['tags'], 'code'));
        }
        // Catégorie d'origine : filtre sur la colonne themes, inchangée.
        $cat = Fx::listData(1, ['tag' => 'cat-armement'])['notes'];
        foreach ($cat as $note) {
            self::assertContains('ARMEMENT', $note['themes']);
        }
        self::assertSame([], Fx::listData(1, ['tag' => 'hvi', 'q' => 'zzzz-introuvable'])['notes']);
        self::assertSame(60, count(Fx::listData(1, ['tag' => 'inconnue'])['notes']), 'une clé inconnue ne filtre rien');
        self::assertSame(2, count(Fx::listData(2, [])['notes']));
        self::assertSame([], Fx::listData(2, ['tag' => 'pir'])['notes']);
    }

    public function testKindsRulesAndWebValidation(): void
    {
        $s = $this->service();
        $kinds = $s->kinds(1);
        self::assertSame(['FRM', 'FRO', 'FRT', 'FRC', 'FRA'], array_keys($kinds), 'ordre de la communauté');
        self::assertFalse($kinds['FRA']['enabled']);
        self::assertArrayHasKey('FRT', $s->activeKinds(1));
        self::assertSame(['FRM', 'FRO', 'FRT', 'FRC'], array_keys($s->activeKinds(1)));
        self::assertStringContainsString('Effectif', $kinds['FRO']['template']);
        self::assertSame(['humint', 'pir', 'spotrep', 'patrol-debrief', 'unconfirmed'], $s->kinds(2)['FRM']['suggested']);
        // Règles du type à la rédaction sur le portail.
        $errors = $s->validateForKind(1, 'FRT', ['place_label' => '', 'intel_source' => ''], []);
        self::assertCount(2, $errors);
        self::assertStringContainsString('« Lieu »', $errors[0]);
        self::assertStringContainsString('Discipline de recueil', $errors[1]);
        self::assertSame([], $s->validateForKind(1, 'FRT', ['place_label' => 'Nidasos', 'intel_source' => 'TECHINT'], []));
        self::assertSame([], $s->validateForKind(1, 'FRT', ['place_label' => 'Nidasos'], [['slug' => 'domex', 'qualifier' => '']]));
        self::assertStringContainsString('n’est plus proposé', $s->validateForKind(1, 'FRA', [], [])[0]);
        self::assertSame([], $s->validateForKind(2, 'FRT', [], []));
        // Validité par défaut de la communauté, sauf si le rédacteur en a posé une.
        self::assertSame(['unconfirmed'], array_column($s->withDefaults(1, []), 'slug'));
        self::assertSame(['corroborated'], array_column($s->withDefaults(1, [['slug' => 'corroborated', 'qualifier' => '']]), 'slug'));
        self::assertSame([], $s->withDefaults(2, []));
        // Correspondance urgence ↔ priorité réglable ; clés d'urgence inchangées.
        self::assertTrue($s->saveRules(1, ['default_validity' => '', 'precedence' => ['critique' => 'FLASH', 'urgent' => 'FLASH', 'normal' => 'PRIORITY', 'routine' => 'ROUTINE'], 'rel_to' => 'usa, altis'], Fx::ACTOR)['ok']);
        self::assertSame('FLASH', $s->precedenceFor(1, 'urgent')['code']);
        self::assertSame('IMMEDIATE', $s->precedenceFor(2, 'urgent')['code']);
        self::assertSame('IMMEDIATE', $s->precedenceFor(2, 'priorite')['code'], 'ancienne clé d’urgence relue');
        self::assertSame('USA, ALTIS', $s->rules(1)['rel_to']);
        self::assertFalse($s->saveRules(1, ['default_validity' => 'humint', 'precedence' => ['critique' => 'ZULU'], 'rel_to' => 'NATO'], Fx::ACTOR)['ok']);
        // Types : libellé, trame, garde-fous.
        self::assertFalse($s->saveKind(1, 'ZZZ', ['label' => 'X'], Fx::ACTOR)['ok']);
        self::assertFalse($s->saveKind(1, 'FRM', ['template' => 'Transmettre à Interpol'], Fx::ACTOR)['ok']);
        foreach (['FRM', 'FRO', 'FRT'] as $kind) {
            self::assertTrue($s->saveKind(1, $kind, ['enabled' => false], Fx::ACTOR)['ok']);
        }
        self::assertFalse($s->saveKind(1, 'FRC', ['enabled' => false], Fx::ACTOR)['ok'], 'il reste toujours un type actif');
        self::assertSame(SseFieldNoteCatalog::KINDS['FRC']['label'], $s->kinds(1)['FRC']['default_label']);
    }

    public function testExportThenStrictImport(): void
    {
        $s = $this->service();
        $export = $s->export(1);
        self::assertSame(SseFieldNoteTagService::FORMAT, $export['format']);
        self::assertContains('ROUTE WATCH', array_column($export['tags'], 'code'));
        self::assertNotContains('HVI', array_column($export['tags'], 'code'), 'une étiquette livrée sans réglage n’est pas exportée');
        $json = (string) json_encode($export + ['exported_at' => '2026-11-15T10:00:00+00:00']);

        // La communauté 2 reprend le référentiel de la communauté 1.
        $r = $s->import(2, $json, Fx::ACTOR);
        self::assertTrue($r['ok'], implode(' ', $r['errors']));
        self::assertSame(11, $r['created']);
        $s->forget();
        $stats = $s->stats(2);
        self::assertSame([35, 8, 3], [$stats['active'], $stats['custom'], $stats['archived']]);
        self::assertSame('unconfirmed', $s->rules(2)['default_validity']);
        self::assertFalse($s->kinds(2)['FRA']['enabled']);
        self::assertSame('field_note.referential.import', end($this->audit)['action']);
        self::assertSame(2, end($this->audit)['tenant_id']);

        // Validation stricte : une seule erreur, rien n'est écrit.
        $bad = $export;
        $bad['tags'][] = ['code' => 'GOOD ONE', 'family' => 'theme', 'label_fr' => 'Bonne', 'color' => 'blue'];
        $bad['tags'][] = ['code' => 'BAD ONE', 'family' => 'theme', 'label_fr' => 'Liaison avec Europol', 'color' => 'blue'];
        $r = $s->import(1, (string) json_encode($bad), Fx::ACTOR);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('organisation réelle', implode(' ', $r['errors']));
        self::assertNull($s->find(1, 'good-one'));
        foreach ([
            '{',
            '{"format":"autre","version":1,"tags":[]}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[],"surprise":1}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[{"code":"A B","family":"evaluation","label_fr":"x","color":"blue"}]}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[{"code":"A B","family":"theme","label_fr":"x","color":"blue","sql":"x"}]}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[{"code":"HUMINT","family":"theme","label_fr":"x","color":"blue"}]}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[{"code":"A B","family":"theme","label_fr":["x"],"color":"blue"}]}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[],"kinds":{"ZZZ":{}}}',
            '{"format":"' . SseFieldNoteTagService::FORMAT . '","version":1,"tags":[],"rules":{"precedence":{"critique":"ZULU"}}}',
        ] as $json) {
            self::assertFalse($s->import(1, $json, Fx::ACTOR)['ok'], $json);
        }
        self::assertSame(35, $s->stats(1)['active']);
    }

    public function testGameCatalogIsAdditive(): void
    {
        $s = $this->service();
        $base = SseFieldNoteCatalog::clientCatalog();
        $extra = $s->clientCatalog(1);
        self::assertSame([], array_intersect_key($base, $extra), 'aucune clé existante n’est remplacée');
        self::assertSame(['tags_enabled', 'tags_max', 'tag_families', 'tags', 'tag_suggestions', 'precedences', 'evaluation'], array_keys($extra));
        self::assertTrue($extra['tags_enabled']);
        $codes = array_column($extra['tags'], 'code');
        self::assertContains('ROUTE WATCH', $codes);
        self::assertContains('PIR', $codes);
        self::assertNotContains('GEOINT', $codes, 'désactivée par la communauté');
        self::assertNotContains('OP HIVER', $codes, 'archivée');
        self::assertNotContains('ARMEMENT', $codes, 'les catégories d’origine restent dans « themes »');
        self::assertSame(30, count($codes));
        self::assertSame(['key', 'code', 'family', 'label', 'label_en', 'hint', 'hint_en', 'color', 'qualifier'], array_keys($extra['tags'][0]));
        self::assertSame(['urgency' => 'critique', 'code' => 'FLASH', 'label' => 'Flash', 'label_en' => 'Flash'], $extra['precedences'][0]);
        self::assertCount(6, $extra['evaluation']['reliability']);
        // Sans communauté : le catalogue livré, étiquetage non ouvert.
        $anon = $s->clientCatalog(null);
        self::assertFalse($anon['tags_enabled']);
        self::assertSame(65, count($anon['tags']));
        $api = SseFieldNoteTagService::apiTags($s->tagsForNotes(1, [$this->ids['long']])[$this->ids['long']]);
        self::assertSame(['key', 'code', 'family', 'label', 'qualifier', 'text', 'color'], array_keys($api[0]));
        self::assertSame('PIR 2', $api[1]['text']);
    }

    public function testOneCatalogSharedWithTheAttachWindow(): void
    {
        $s = $this->service();
        $attach = new SseAttachmentRepository();
        // Une étiquette née dans la fenêtre « Rattacher » : visible dans le référentiel des fiches, pas encore proposée.
        $id = $attach->tagId(1, 'Pont nord', true, 7, 'blue');
        self::assertGreaterThan(0, $id);
        self::assertSame('attach', $this->scalar('SELECT scope FROM sse_attach_tags WHERE id = ' . $id));
        $s->forget();
        $tag = $s->find(1, 'pont-nord');
        self::assertSame(['PONT NORD', 'theme', 'disabled', true], [$tag['code'], $tag['family'], $tag['status'], $tag['from_attach']]);
        self::assertSame([], $s->normalizeSelection(1, ['pont-nord'])['selection']);
        // Activée pour les fiches : même ligne, portée commune, toujours listée par la fenêtre.
        self::assertTrue($s->setStatus(1, 'pont-nord', 'active', Fx::ACTOR)['ok']);
        self::assertSame(['all', 'active', $id], [$this->scalar('SELECT scope FROM sse_attach_tags WHERE id = ' . $id), $this->scalar('SELECT status FROM sse_attach_tags WHERE id = ' . $id), (int) $this->scalar("SELECT id FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'pont-nord'")]);
        self::assertSame(['pont-nord'], array_column($s->normalizeSelection(1, ['pont-nord'])['selection'], 'slug'));

        $names = array_column($attach->tags(1), 'slug');
        self::assertContains('pont-nord', $names, 'pont-nord');
        // Une étiquette propre des fiches est proposée aux rattachements ; un réglage du catalogue livré ne l'est pas.
        self::assertContains('route-watch', $names, 'route-watch');
        self::assertNotContains('geoint', $names);
        self::assertNotContains('pir', $names);
        self::assertSame((int) $this->scalar("SELECT id FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'route-watch'"), $attach->tagId(1, 'Route watch', true, 7));
        // Un réglage du catalogue livré repris pour un rattachement devient commun.
        $pir = $attach->tagId(1, 'PIR', true, 7);
        self::assertSame('all', $this->scalar('SELECT scope FROM sse_attach_tags WHERE id = ' . $pir));
        self::assertContains('pir', array_column($attach->tags(1), 'slug'), 'pir commun');
        // Le nom court lu par la fenêtre tient dans sa colonne ; le libellé entier est gardé à part.
        $s->saveTag(1, ['code' => 'LONG LABEL', 'family' => 'theme', 'label_fr' => 'Compte rendu d’entretien avec un responsable local du secteur', 'color' => 'slate', 'sort' => 0, 'qualifier' => ''], Fx::ACTOR);
        self::assertSame(40, mb_strlen((string) $this->scalar("SELECT name FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'long-label'")));
        self::assertSame('Compte rendu d’entretien avec un responsable local du secteur', $s->find(1, 'long-label')['label_fr']);
        // Une étiquette dont un rattachement se sert ne se supprime pas.
        $rowId = (int) $this->scalar("SELECT id FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'long-label'");
        $this->pdo->exec("INSERT INTO sse_link_attachments (tenant_id, source_type, source_id, target_type, target_id, tags, created_at) VALUES (1, 'note', '1', 'case', '1', '," . $rowId . ",', '2026-11-15 10:00:00')");
        $r = $s->deleteTag(1, 'long-label', Fx::ACTOR);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('rattachements', $r['errors'][0]);
    }

    public function testStepIsIdempotentAndDegradedModeIsReadOnly(): void
    {
        // Relancer l'étape ne change rien.
        $out = Sq::runStep($this->pdo);
        self::assertStringContainsString('déjà présentes sur sse_attach_tags', $out);
        self::assertStringContainsString('déjà présentes.', $out);
        self::assertSame(35, $this->service()->stats(1)['active']);
        $root = dirname(__DIR__, 2);
        $sql = str_replace("\r\n", "\n", (string) file_get_contents($root . '/migrations/2026_11_21_100_sse_tags.sql'));
        self::assertSame(2, substr_count($sql, 'CREATE TABLE IF NOT EXISTS'));
        self::assertStringNotContainsString('ADD UNIQUE', strtoupper($sql));
        self::assertSame(2, substr_count($sql, 'UNIQUE KEY'));
        $registry = require $root . '/database/steps/registry.php';
        $files = array_column($registry, 'file');
        self::assertContains('4850_sse_tags.php', $files);
        self::assertGreaterThan(array_search('4840_sse_attachments.php', $files, true), array_search('4850_sse_tags.php', $files, true));
        self::assertSame(count($files), count(array_unique($files)));
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_tags_down.sql');
        foreach (['DROP TABLE IF EXISTS sse_tag_links', 'DROP TABLE IF EXISTS sse_field_note_settings', 'DROP COLUMN scope'] as $needle) {
            self::assertStringContainsString($needle, $down);
        }
        self::assertStringNotContainsString('DROP TABLE IF EXISTS sse_attach_tags', $down);

        // Base où l'étape n'est pas passée : catalogue livré en lecture seule, étiquetage désactivé, rien ne casse.
        Sq::close();
        $this->pdo = Sq::open(false);
        $ids = Fx::seed($this->pdo, false);
        $s = $this->service();
        self::assertFalse($s->ready());
        self::assertSame(82, $s->stats(1)['active']);
        foreach ([$s->saveTag(1, ['code' => 'NEW ONE', 'family' => 'theme', 'label_fr' => 'X', 'color' => 'blue'], Fx::ACTOR), $s->setStatus(1, 'humint', 'disabled', Fx::ACTOR), $s->merge(1, 'humint', 'sigint', Fx::ACTOR), $s->resetFamily(1, 'theme', Fx::ACTOR), $s->saveKind(1, 'FRM', ['label' => 'X'], Fx::ACTOR), $s->saveRules(1, [], Fx::ACTOR), $s->import(1, '{}', Fx::ACTOR)] as $r) {
            self::assertFalse($r['ok']);
            self::assertStringContainsString('lecture seule', $r['errors'][0]);
        }
        self::assertFalse($s->setNoteTags(1, $ids['long'], [['slug' => 'humint', 'qualifier' => '']], Fx::ACTOR));
        self::assertSame([], $s->tagsForNotes(1, [$ids['long']]));
        self::assertSame('CUI', $s->decorate(1, ['id' => $ids['short'], 'classification' => 'interne', 'urgency' => 'routine', 'themes' => ['MOUV']])['marking']);
        self::assertFalse($s->clientCatalog(1)['tags_enabled']);
        // La fenêtre « Rattacher » fonctionne comme avant.
        $attach = new SseAttachmentRepository();
        self::assertGreaterThan(0, $attach->tagId(1, 'Pont nord', true, 7));
        self::assertSame(['pont-nord'], array_column($attach->tags(1), 'slug'));
    }
}
