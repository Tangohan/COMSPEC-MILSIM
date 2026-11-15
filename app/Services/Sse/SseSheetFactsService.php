<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseIdentityCatalog as IC;
use App\Support\SseRedactionExplain;
use App\Support\SseSheetText;
use App\Support\SseWantedCatalog as C;

/**
 * Données d'une fiche PDF, construites à partir de la FICHE PERSONNE (sse_persons, photos, relevés) et, quand elle existe, de
 * l'identité du registre qui lui est rattachée (alias, signalement, liens, avis actif). Une personne sans identité du registre
 * donne une fiche complète ; une identité sans personne aussi.
 *
 * Caviardage : les champs sont rangés dans les catégories de SseRedactionService (identité, lieu, biométrie, source,
 * horodatage) et mask() les remplace par la barre de caviardage selon le niveau de DIFFUSION du document (sa classification,
 * jamais au-dessus de l'habilitation du rédacteur). leaks() vérifie en plus qu'aucune valeur masquée n'est recopiée dans un
 * texte libre. Le compte d'un membre n'est jamais lu : ni nom, ni pseudo.
 */
final class SseSheetFactsService
{
    /** Contrôles repris au plus par fiche. */
    public const MAX_CHECKS = 12;

    private const EYES_EN = ['brown' => 'Brown', 'black' => 'Black', 'hazel' => 'Hazel', 'green' => 'Green', 'blue' => 'Blue', 'gray' => 'Grey'];

    private const HAIR_EN = ['black' => 'Black', 'brown' => 'Brown', 'blond' => 'Blond', 'red' => 'Red', 'gray' => 'Grey', 'white' => 'White', 'bald' => 'Bald or shaved'];

    /** Champs simples masqués : champ => catégorie de SseRedactionService. */
    public const SCALAR_CATEGORY = [
        'last_name' => 'identite', 'first_name' => 'identite', 'display' => 'identite', 'birth_date' => 'identite', 'birth_place' => 'identite',
        'nationality' => 'identite', 'id_document' => 'identite', 'place' => 'lieu', 'last_seen_place' => 'lieu', 'last_seen_at' => 'horodatage',
        'zone' => 'lieu',
    ];

    /** Listes de liens masquées : genre de lien => catégorie. */
    public const LINK_CATEGORY = ['address' => 'lieu', 'associate' => 'identite'];

    /** Champs d'un contrôle masqués : champ => catégorie. */
    public const CHECK_CATEGORY = ['place' => 'lieu', 'at' => 'horodatage', 'controller' => 'source', 'presented' => 'identite'];

    public function __construct(
        private ?SsePersonRepository $persons = null,
        private ?SseIdentityRepository $identities = null,
        private ?SsePersonRegistryLinkService $links = null,
    ) {
        $this->persons ??= new SsePersonRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->links ??= new SsePersonRegistryLinkService($this->identities);
    }

    /**
     * Le sujet d'une fiche : une personne (avec son identité du registre lisible) ou une identité (avec sa personne).
     * Null si le sujet n'existe pas dans la communauté ou si l'identité est une vérité de jeu cachée au demandeur.
     *
     * @return array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string}|null
     */
    public function subject(int $tenantId, string $kind, int $id, SseSheetAccess $access): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        $truth = $access->wanted()->canSeeTruth();
        if ($kind === 'identity') {
            $identity = $this->identities->find($id, $tenantId);
            if ($identity === null || (!$truth && in_array((string) $identity['kind'], MemberIdentityPapersService::SECRET, true))) {
                return null;
            }
            $person = (int) ($identity['person_id'] ?? 0) > 0 ? $this->persons->findById((int) $identity['person_id'], $tenantId) : null;

            return ['person' => $person, 'identity' => $identity, 'identities' => [$identity], 'person_id' => $person !== null ? (int) $person['id'] : null, 'identity_id' => (int) $identity['id'], 'ref' => (string) $identity['ref']];
        }
        $person = $this->persons->findById($id, $tenantId);
        if ($person === null) {
            return null;
        }
        $identities = $this->links->identitiesOf($tenantId, $id, $truth);

        return [
            'person' => $person, 'identity' => $identities[0] ?? null, 'identities' => $identities, 'person_id' => $id,
            'identity_id' => isset($identities[0]) ? (int) $identities[0]['id'] : null, 'ref' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Toutes les données connues du sujet, NON caviardées (à passer par mask() avant tout usage).
     *
     * @param array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string} $subject
     * @return array<string, mixed>
     */
    public function raw(int $tenantId, array $subject, SseSheetAccess $access, string $lang): array
    {
        $person = $subject['person'] ?? [];
        $identity = $subject['identity'] ?? [];
        $dossier = [];
        if ($identity !== []) {
            try {
                $dossier = (new SseIdentityDossierService())->build($tenantId, $identity, $access->wanted(), $lang);
            } catch (\Throwable) {
                $dossier = [];
            }
        }
        $profile = is_array($dossier['profile'] ?? null) ? $dossier['profile'] : [];
        $notice = is_array($dossier['notice'] ?? null) ? $dossier['notice'] : null;
        $en = $lang === 'en';

        $last = trim((string) ($person['last_name'] ?? '')) !== '' ? (string) $person['last_name'] : (string) ($identity['last_name'] ?? '');
        $first = trim((string) ($person['first_name'] ?? '')) !== '' ? (string) $person['first_name'] : (string) ($identity['first_name'] ?? '');
        $sex = strtoupper(substr(trim((string) ($person['sex_apparent'] ?? $identity['sex'] ?? '')), 0, 1));
        $aliases = [];
        foreach ([$person['alias'] ?? null, $identity['alias'] ?? null] as $a) {
            if (is_string($a) && trim($a) !== '') {
                $aliases[] = trim($a);
            }
        }
        foreach ($dossier['fileAliases'] ?? [] as $a) {
            if (trim((string) ($a['alias'] ?? '')) !== '') {
                $aliases[] = trim((string) $a['alias']);
            }
        }
        $aliases = array_slice(array_values(array_unique($aliases)), 0, 8);

        $hair = (string) ($profile['hair'] ?? '');
        $eyes = (string) ($profile['eyes'] ?? '');
        $build = (string) ($profile['build'] ?? '');
        $marks = trim((string) ($profile['marks'] ?? '')) !== '' ? (string) $profile['marks'] : (string) ($person['distinguishing_marks'] ?? '');

        $links = ['vehicle' => [], 'address' => [], 'phone' => [], 'email' => [], 'associate' => []];
        foreach ($dossier['fileLinks'] ?? [] as $l) {
            $k = (string) ($l['kind'] ?? '');
            if (isset($links[$k]) && trim((string) ($l['value'] ?? '')) !== '' && count($links[$k]) < 8) {
                $rel = trim((string) ($l['relation_label'] ?? ''));
                $links[$k][] = (string) $l['value'] . ($k === 'associate' && $rel !== '' && $rel !== '—' ? ' (' . $rel . ')' : '');
            }
        }

        $terrain = $this->terrain($tenantId, $person);
        $samples = is_array($terrain['biometric_samples'] ?? null) ? $terrain['biometric_samples'] : [];
        $modality = static fn (string $kind): string => $en ? str_replace(['Non relevées', 'Non relevé', 'Relevées', 'Relevé'], ['Not recorded', 'Not recorded', 'Recorded', 'Recorded'], SseTerrainService::biometricModalityLabel($samples, $kind)) : SseTerrainService::biometricModalityLabel($samples, $kind);
        $bio = [
            'prints' => $modality('empreintes'),
            'iris' => $modality('iris'),
            'dna' => $modality('adn'),
            'quality' => ($terrain['acquisition_quality_avg'] ?? null) !== null ? (int) $terrain['acquisition_quality_avg'] : null,
            'tier' => (string) ($terrain['identity_tier_label'] ?? ''),
            'subject_id' => (string) ($terrain['subject_id'] ?? ''),
            'seek_stage' => (string) ($terrain['seek_stage_label'] ?? ''),
        ];

        $idDoc = !empty($person['id_document_present']) ? trim((string) ($person['id_document_type'] ?? '') . ' ' . (string) ($person['id_document_number'] ?? '')) : '';
        $placeParts = array_values(array_filter([trim((string) ($notice['last_seen_place'] ?? '')), trim((string) ($person['location_description'] ?? '')), trim((string) ($person['grid_reference'] ?? ''))], static fn (string $v): bool => $v !== ''));
        $placeParts = array_values(array_intersect_key($placeParts, array_unique(array_map(static fn (string $p): string => mb_strtolower($p, 'UTF-8'), $placeParts))));
        $seenAt = (string) ($notice['last_seen_at'] ?? $person['updated_at'] ?? $person['created_at'] ?? $identity['updated_at'] ?? '');
        $threat = $notice !== null ? (string) $notice['threat_level'] : '';

        return [
            'subject_ref' => (string) $subject['ref'],
            'last_name' => $last, 'first_name' => $first, 'display' => trim(mb_strtoupper($last, 'UTF-8') . ' ' . $first),
            'sex' => in_array($sex, ['M', 'F'], true) ? $sex : 'X',
            'age_estimated' => isset($person['age_estimated']) ? (int) $person['age_estimated'] : null,
            'birth_date' => $this->day((string) ($person['birth_date'] ?? $identity['birth_date'] ?? '')),
            'birth_place' => trim((string) ($person['birth_place'] ?? $identity['birth_place'] ?? '')),
            'nationality' => strtoupper(trim((string) ($person['nationality'] ?? $identity['nationality'] ?? ''))),
            'language' => trim((string) ($person['language_spoken'] ?? $identity['language'] ?? '')),
            'affiliation' => trim((string) ($profile['affiliation'] ?? '')) !== '' ? (string) $profile['affiliation'] : trim((string) ($person['affiliation'] ?? $identity['faction'] ?? '')),
            'aliases' => $aliases,
            'marks' => trim($marks),
            'height_cm' => is_numeric($profile['height_cm'] ?? null) ? (int) $profile['height_cm'] : null,
            'weight_kg' => is_numeric($profile['weight_kg'] ?? null) ? (int) $profile['weight_kg'] : null,
            'build' => $build !== '' ? ($en ? ucfirst($build) : S::BUILDS[$build] ?? $build) : '',
            'hair' => $hair !== '' ? ($en ? self::HAIR_EN[$hair] ?? $hair : S::HAIR[$hair] ?? $hair) : '',
            'eyes' => $eyes !== '' ? ($en ? self::EYES_EN[$eyes] ?? $eyes : S::EYES[$eyes] ?? $eyes) : '',
            'id_document' => $idDoc,
            'photos' => $this->photoPaths($tenantId, $person, $identity, $dossier),
            'bio' => $bio,
            'last_seen_place' => implode(' · ', array_slice($placeParts, 0, 2)),
            'last_seen_at' => $seenAt,
            'zone' => (string) ($notice['zone'] ?? $identity['zone'] ?? ''),
            'affiliation_known' => trim((string) ($person['affiliation'] ?? '')) !== '',
            'threat' => in_array($threat, array_keys(C::THREATS), true) ? $threat : '',
            'armed' => (string) ($notice['armed'] ?? ''),
            'notice' => $notice !== null ? [
                'id' => (int) $notice['id'], 'ref' => (string) $notice['ref'], 'type' => (string) $notice['notice_type'], 'threat' => (string) $notice['threat_level'],
                'armed' => (string) $notice['armed'], 'reason' => (string) ($notice['reason'] ?? ''), 'instructions' => (string) ($notice['instructions'] ?? ''),
                'issued_at' => (string) $notice['issued_at'], 'expires_at' => $notice['expires_at'] ?? null,
            ] : null,
            'links' => $links,
            'weapons' => array_slice(array_map(static fn ($w): string => is_array($w) ? implode(' ', array_filter(array_map('strval', $w))) : (string) $w, (array) ($person['weapons'] ?? [])), 0, 8),
            'equipment' => array_slice(array_map(static fn ($w): string => is_array($w) ? implode(' ', array_filter(array_map('strval', $w))) : (string) $w, (array) ($person['equipment'] ?? [])), 0, 8),
            'circumstances' => trim((string) ($person['circumstances_label'] ?? $person['circumstances'] ?? '')),
            'statements' => trim((string) ($person['statements'] ?? '')),
            'checks' => $this->checks($tenantId, $subject, $lang),
            'sensitive_identity_kind' => (string) ($identity['kind'] ?? ''),
        ];
    }

    /**
     * Copie caviardée au niveau de diffusion : toute donnée dont la catégorie dépasse ce niveau devient la barre de caviardage.
     *
     * @param array<string, mixed> $raw
     * @return array{facts: array<string, mixed>, hidden: list<string>, shown: list<string>}
     */
    public function mask(array $raw, string $level): array
    {
        $hidden = [];
        $shown = [];
        $see = static function (string $category) use ($level, &$hidden, &$shown): bool {
            $ok = SseRedactionService::visibleAt($category, $level);
            if ($ok) {
                $shown[$category] = true;
            } else {
                $hidden[$category] = true;
            }

            return $ok;
        };
        // La barre porte sa catégorie (SseRedactionExplain::TAG) : le PDF et la page disent QUELLE catégorie cache chaque passage.
        $bar = static fn (mixed $v, string $category): string => SseRedactionExplain::bar($category);
        $f = $raw;
        foreach (self::SCALAR_CATEGORY as $field => $category) {
            if (isset($f[$field]) && is_string($f[$field]) && $f[$field] !== '' && !$see($category)) {
                $f[$field] = $bar($f[$field], $category);
            }
        }
        if ($f['aliases'] !== [] && !$see('identite')) {
            $f['aliases'] = [$bar('alias', 'identite')];
        }
        if (!$see('biometrie')) {
            $f['bio'] = ['prints' => $bar('x', 'biometrie'), 'iris' => $bar('x', 'biometrie'), 'dna' => $bar('x', 'biometrie'), 'quality' => null, 'tier' => '', 'subject_id' => '', 'seek_stage' => '', 'masked' => true];
        }
        foreach (self::LINK_CATEGORY as $kind => $category) {
            if ($f['links'][$kind] !== [] && !$see($category)) {
                $f['links'][$kind] = array_map(static fn ($v): string => $bar($v, $category), $f['links'][$kind]);
            }
        }
        foreach ($f['checks'] as $i => $c) {
            foreach (self::CHECK_CATEGORY as $field => $category) {
                if (($c[$field] ?? '') !== '' && !$see($category)) {
                    $f['checks'][$i][$field] = $bar($c[$field], $category);
                }
            }
        }
        if (is_array($f['notice']) && !$see('lieu')) {
            $f['notice']['zone'] = '';
        }

        return ['facts' => $f, 'hidden' => array_keys($hidden), 'shown' => array_keys($shown)];
    }

    /**
     * Valeurs masquées recopiées dans un texte libre (nom, prénom, alias, naissance, nationalité, pièce, lieu, adresses,
     * personnes liées) : la liste est vide si le texte est sûr pour ce niveau de diffusion.
     *
     * @param array<string, mixed> $raw
     * @param array<string, string> $texts
     * @return list<string>
     */
    public function leaks(array $raw, array $texts, string $level): array
    {
        $secret = [];
        $add = static function (string $category, mixed $value) use (&$secret, $level): void {
            if (!SseRedactionService::visibleAt($category, $level) && is_string($value) && mb_strlen(trim($value), 'UTF-8') >= 3) {
                $secret[mb_strtolower(trim($value), 'UTF-8')] = trim($value);
            }
        };
        // Valeur entière puis ses morceaux (« Zoë-Élodie » : Zoë, Élodie ; « Pyrgos, marché » : Pyrgos, marché).
        $addParts = static function (string $category, mixed $value, string $split) use ($add): void {
            $add($category, $value);
            if (is_string($value)) {
                foreach ((array) preg_split($split, $value) as $part) {
                    $add($category, is_string($part) ? $part : '');
                }
            }
        };
        foreach (['last_name', 'first_name'] as $f) {
            $addParts('identite', $raw[$f] ?? '', "/[\s\-'’]+/u");
        }
        foreach (['birth_place', 'id_document'] as $f) {
            $addParts('identite', $raw[$f] ?? '', '/[,;()]+/u');
        }
        $add('identite', $raw['birth_date'] ?? '');
        $add('identite', $raw['nationality'] ?? '');
        foreach ($raw['aliases'] ?? [] as $a) {
            $addParts('identite', $a, "/[\s\-'’]+/u");
        }
        foreach ($raw['links']['associate'] ?? [] as $a) {
            $addParts('identite', preg_replace('/\s*\(.*\)$/u', '', (string) $a), "/\s+/u");
        }
        foreach ($raw['links']['address'] ?? [] as $a) {
            $addParts('lieu', $a, '/[,;]+/u');
        }
        foreach (explode(' · ', (string) ($raw['last_seen_place'] ?? '')) as $p) {
            $addParts('lieu', $p, '/[,;]+/u');
        }
        $found = [];
        $haystack = mb_strtolower(implode("\n", array_map(static fn (string $t): string => SseSheetText::plain($t), $texts)), 'UTF-8');
        foreach ($secret as $needle => $original) {
            if (str_contains($haystack, $needle)) {
                $found[] = $original;
            }
        }

        return $found;
    }

    /**
     * Valeurs des variables de texte ({nom}, {alias}…), déjà caviardées : une variable de donnée masquée donne la barre.
     *
     * @param array<string, mixed> $facts faits caviardés (mask())
     * @param array<string, mixed> $context ref, service, date
     * @return array<string, string>
     */
    public function variables(array $facts, string $lang, array $context = []): array
    {
        $en = $lang === 'en';
        $none = $en ? 'not available' : 'non communiqué';
        $v = static fn (mixed $x): string => str_replace(['*', '<', '>', '{', '}'], '', trim(is_scalar($x) ? (string) $x : ''));
        $or = static fn (string $x) => $x !== '' ? $x : $none;
        $height = $facts['height_cm'] !== null ? $facts['height_cm'] . ' cm' : '';
        $sig = implode(', ', array_filter([$height, $facts['build'] !== '' ? $facts['build'] : '', $facts['eyes'] !== '' ? ($en ? 'eyes ' : 'yeux ') . mb_strtolower((string) $facts['eyes']) : '', $facts['hair'] !== '' ? ($en ? 'hair ' : 'cheveux ') . mb_strtolower((string) $facts['hair']) : '']));
        $threat = $facts['threat'] !== '' ? mb_strtolower(C::label(C::THREATS, (string) $facts['threat'], $lang)) : '';
        $seen = trim((string) $facts['last_seen_at']);

        return [
            'nom' => $or($v($facts['last_name'])),
            'prenom' => $or($v($facts['first_name'])),
            'nom_complet' => $or($v(trim((string) $facts['first_name'] . ' ' . mb_strtoupper((string) $facts['last_name'], 'UTF-8')))),
            'alias' => $or($v(implode(', ', (array) $facts['aliases']))),
            'date_naissance' => $or($v($facts['birth_date'])),
            'nationalite' => $or($v($facts['nationality'])),
            'affiliation' => $or($v($facts['affiliation'])),
            'signalement' => $or($v($sig)),
            'marques' => $or($v($facts['marks'])),
            'menace' => $or($v($threat)),
            'dernier_lieu' => $or($v($facts['last_seen_place'])),
            'derniere_date' => $or($v($seen !== '' ? (str_starts_with($seen, '█') ? $seen : substr($seen, 0, 10)) : '')),
            'nb_controles' => (string) count((array) $facts['checks']),
            'ref' => $v($context['ref'] ?? ''),
            'service' => $or($v($context['service'] ?? '')),
            'date' => $v($context['date'] ?? gmdate('Y-m-d')),
        ];
    }

    // ----------------------------------------------------------------- Outils

    /** @param array<string, mixed> $person @return array<string, mixed> */
    private function terrain(int $tenantId, array $person): array
    {
        if ($person === []) {
            return [];
        }
        try {
            return (new SseTerrainService())->personTerrainDossier($tenantId, $person);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function checks(int $tenantId, array $subject, string $lang): array
    {
        $rows = [];
        try {
            if ($subject['person_id'] !== null) {
                $rows = SseIdentityRegistryController::personPapers($tenantId, (int) $subject['person_id'])['checks'] ?? [];
            } elseif ($subject['identity_id'] !== null) {
                $rows = (new SseIdCheckRepository())->list($tenantId, ['identity_id' => (int) $subject['identity_id'], 'limit' => 30]);
            }
        } catch (\Throwable) {
            $rows = [];
        }
        $out = [];
        foreach (array_slice(is_array($rows) ? $rows : [], 0, self::MAX_CHECKS) as $c) {
            $levels = array_map(static fn (string $l): string => $l, (array) ($c['levels'] ?? []));
            $out[] = [
                'ref' => (string) ($c['ref'] ?? ''),
                'at' => substr((string) ($c['created_at'] ?? ''), 0, 16),
                'place' => trim((string) ($c['grid'] ?? '')),
                'verdict' => (string) ($c['verdict_label'] ?? ''),
                'verdict_key' => (string) ($c['verdict'] ?? ''),
                'levels' => implode(', ', $levels),
                'doc' => trim((string) ($c['doc_type'] ?? '') . ' ' . (string) ($c['country_code'] ?? '') . ' ' . (string) ($c['doc_number'] ?? '')),
                'presented' => trim((string) ($c['presented_last'] ?? '') . ' ' . (string) ($c['presented_first'] ?? '')),
                'controller' => trim((string) ($c['controller_callsign'] ?? '')),
                'case_ref' => (string) ($c['case_ref'] ?? ''),
            ];
        }
        unset($lang);

        return $out;
    }

    /**
     * Chemins d'images de la fiche : portrait de l'identité, clichés de la personne, photos du dossier (au plus 6).
     *
     * @param array<string, mixed> $person
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $dossier
     * @return list<string>
     */
    private function photoPaths(int $tenantId, array $person, array $identity, array $dossier): array
    {
        // Identité d'un membre : aucune image sans autorisation hiérarchique (même règle que la notice de recherche).
        if ((int) ($identity['user_id'] ?? 0) > 0 && !$this->memberImagesAllowed($tenantId, $identity)) {
            return [];
        }
        $out = [];
        $primary = is_array($person['primary_photo'] ?? null) ? (string) ($person['primary_photo']['image_path'] ?? '') : '';
        foreach ([$primary, (string) ($identity['photo_path'] ?? '')] as $p) {
            if ($p !== '') {
                $out[] = $p;
            }
        }
        foreach ((array) ($person['photos'] ?? []) as $ph) {
            $out[] = (string) ($ph['image_path'] ?? '');
        }
        foreach ((array) ($dossier['filePhotos'] ?? []) as $ph) {
            $out[] = (string) ($ph['path'] ?? '');
        }

        return array_slice(array_values(array_unique(array_filter($out, static fn (string $p): bool => $p !== ''))), 0, 6);
    }

    /** @param array<string, mixed> $identity */
    private function memberImagesAllowed(int $tenantId, array $identity): bool
    {
        try {
            $gate = SseMemberImageGate::forSession();
            $access = $gate->forIdentity($tenantId, $identity);
            if (!$access['allowed']) {
                $gate->recordDenied($tenantId, $gate->viewerId(), 'sheet_pdf', (int) ($identity['id'] ?? 0), $access['owner']);

                return false;
            }
            if ($access['confidential'] && $access['reason'] !== SseMemberImageGate::WHY_HOLDER) {
                $gate->recordConsultation($tenantId, (int) $gate->viewerId(), 'sheet_pdf', (int) ($identity['id'] ?? 0), $access['owner'], $access['reason']);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('Fiches PDF : image d’un membre omise (autorisation ou journal indisponible) : ' . $e->getMessage());

            return false;
        }
    }

    private function day(string $value): string
    {
        return $value !== '' ? substr($value, 0, 10) : '';
    }

    /** Niveau de classification minimal pour lire un type de donnée (aide à l'affichage). */
    public static function levelFor(string $category): string
    {
        return SseRedactionService::CATEGORIES[$category]['level'] ?? SseCaseRepository::CLASS_COMMAND;
    }

    /** Libellé d'une vérification (verdict) dans la langue du document. */
    public static function verdictLabel(string $key, string $lang): string
    {
        $fr = IC::VERDICTS[$key] ?? $key;
        $en = ['ras' => 'Clear', 'signale' => 'Flagged', 'recherche' => 'Wanted', 'hvt' => 'Priority target', 'inconnu' => 'Unknown to the files', 'revoque' => 'Document cancelled', 'vole' => 'Document stolen or lost', 'expire' => 'Document expired'];

        return $lang === 'en' ? ($en[$key] ?? $fr) : $fr;
    }
}
