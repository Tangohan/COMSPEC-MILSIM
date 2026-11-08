<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;

/**
 * Couverture complète et cohérente, tirée d'une graine (bouton « Générer » de Ma couverture).
 *
 * Personnes fictives seulement : noms, lieux, métiers, employeurs, numéros de téléphone (série 555) et numéros de papier
 * sortent de listes ou de formules, jamais d'une donnée réelle. Une graine donne toujours la même couverture.
 * Cohérence : l'employeur et le motif du déplacement suivent le métier, la famille suit l'âge, les personnes qui peuvent
 * confirmer sont des proches et un collègue, le papier suit le format et la durée du pays.
 * Champs = ceux de la couverture née en jeu (SseIdGameCoverService), plus prénom, nom, sexe et histoire.
 */
final class SseCoverGeneratorService
{
    public const LANGS = ['fr', 'en'];

    /** Métier (clé du générateur d'identité) => [métier EN, employeur FR, employeur EN, famille de motifs]. */
    private const JOBS = [
        'Agriculteur' => ['Farmer', 'Exploitation {last}', '{last} farm', 'trade'],
        'Commerçant' => ['Shopkeeper', 'Commerce {last} et fils', '{last} & Sons store', 'trade'],
        'Chauffeur' => ['Driver', 'Transports {place}', '{place} Haulage', 'work'],
        'Mécanicien' => ['Mechanic', 'Garage {last}', '{last} Garage', 'work'],
        'Pêcheur' => ['Fisherman', 'Coopérative de pêche de {place}', '{place} Fishing Cooperative', 'trade'],
        'Enseignant' => ['Teacher', 'École communale de {place}', '{place} Primary School', 'admin'],
        'Étudiant' => ['Student', 'Lycée technique de {place}', '{place} Technical College', 'admin'],
        'Ouvrier du bâtiment' => ['Construction worker', 'Bâtiment {last}', '{last} Building Works', 'work'],
        'Infirmier' => ['Nurse', 'Dispensaire de {place}', '{place} Clinic', 'medical'],
        'Fonctionnaire' => ['Civil servant', 'Mairie de {place}', '{place} Town Hall', 'admin'],
        'Électricien' => ['Electrician', 'Électricité {last}', '{last} Electrical', 'work'],
        'Sans emploi' => ['Unemployed', 'Aucun (travaux de saison)', 'None (seasonal work)', 'family'],
        'Boulanger' => ['Baker', 'Boulangerie {last}', '{last} Bakery', 'trade'],
        'Artisan' => ['Craftsman', 'Atelier {last}', '{last} Workshop', 'trade'],
        'Gardien' => ['Caretaker', 'Résidence de {place}', '{place} Residence', 'work'],
    ];

    private const TRAVEL = [
        'trade' => [
            ['Vendre ma production au marché de {dest}', 'Selling my produce at the {dest} market'],
            ['Acheter du matériel à {dest}', 'Buying supplies in {dest}'],
            ['Livrer une commande à {dest}', 'Delivering an order in {dest}'],
        ],
        'work' => [
            ['Un chantier m’attend à {dest}', 'A job is waiting for me in {dest}'],
            ['Convoyage pour mon employeur vers {dest}', 'Driving for my employer to {dest}'],
            ['Dépannage chez un client à {dest}', 'Repair call at a customer’s in {dest}'],
        ],
        'family' => [
            ['Rendre visite à ma famille à {dest}', 'Visiting my family in {dest}'],
            ['Chercher du travail à {dest}', 'Looking for work in {dest}'],
            ['Un mariage dans la famille à {dest}', 'A family wedding in {dest}'],
        ],
        'medical' => [
            ['Ma garde commence à {dest}', 'My shift starts in {dest}'],
            ['Rendre visite à un patient à {dest}', 'Visiting a patient in {dest}'],
            ['Une formation à {dest}', 'A training course in {dest}'],
        ],
        'admin' => [
            ['Une démarche à la préfecture de {dest}', 'An errand at the {dest} prefecture'],
            ['Une réunion de service à {dest}', 'A staff meeting in {dest}'],
            ['Un rendez-vous au tribunal de {dest}', 'An appointment at the {dest} court'],
        ],
    ];

    private const STREETS = [
        ['rue de l’Église', 'Church Street'], ['rue du Moulin', 'Mill Road'], ['chemin des Oliviers', 'Olive Lane'],
        ['rue de la Fontaine', 'Fountain Street'], ['avenue de la Gare', 'Station Avenue'], ['impasse des Jardins', 'Garden Close'],
        ['rue du Port', 'Harbour Road'], ['route de la Colline', 'Hill Road'], ['place du Marché', 'Market Square'],
        ['chemin du Puits', 'Well Lane'],
    ];

    private const ROLES = [
        'spouse' => ['Conjoint(e)', 'Spouse'], 'sibling' => ['Frère / sœur', 'Sibling'], 'parent' => ['Parent', 'Parent'],
        'colleague' => ['Collègue', 'Colleague'], 'neighbour' => ['Voisin(e)', 'Neighbour'],
    ];

    public function __construct(private ?SseIdentityGeneratorService $identities = null)
    {
        $this->identities ??= new SseIdentityGeneratorService();
    }

    /**
     * @param array<string, mixed> $country SseIdCountryRepository::hydrate()
     * @return array<string, mixed>
     */
    public function generate(array $country, ?int $seed = null, ?string $sex = null, string $lang = 'fr', ?\DateTimeImmutable $today = null): array
    {
        $en = $lang === 'en';
        $today ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id = $this->identities->generate($country, $seed, $sex);
        $seed = $id['seed'];
        $h = static fn (string $salt): int => SseIdentityGeneratorService::hash($seed, $salt);
        $name = trim($id['first_name'] . ' ' . $id['last_name']);

        // Lieux : résidence et destination tirées parmi les lieux du pays (deux lieux différents s'il y en a plusieurs).
        $places = $this->places($country, $id['birth_place']);
        $city = $places[$h('city') % count($places)];
        $dest = $places[$h('dest') % count($places)];
        if ($dest === $city && count($places) > 1) {
            $dest = $places[($h('dest') + 1) % count($places)];
        }

        $job = (string) $id['profession'];
        $spec = self::JOBS[$job] ?? [$job, 'Employeur local à {place}', 'Local employer in {place}', 'family'];
        $employer = str_replace(['{last}', '{place}'], [$id['last_name'], $city], $en ? $spec[2] : $spec[1]);
        $travelSet = self::TRAVEL[$spec[3]] ?? self::TRAVEL['family'];
        $travel = str_replace('{dest}', $dest, $travelSet[$h('travel') % count($travelSet)][$en ? 1 : 0]);

        $birth = new \DateTimeImmutable($id['birth_date'], new \DateTimeZone('UTC'));
        $age = (int) $birth->diff($today)->y;
        [$family, $relatives] = $this->family($country, $id, $seed, $age, $en);

        $contacts = [];
        foreach ($relatives as $r) {
            $contacts[] = [$r['name'], self::ROLES[$r['role']][$en ? 1 : 0], sprintf('555-%04d', $h('tel' . $r['name']) % 10000)];
        }
        $colleague = $this->identities->generate($country, $h('col') % (SseIdentityGeneratorService::MAX_SEED + 1));
        $contacts[] = [
            $colleague['first_name'] . ' ' . $colleague['last_name'],
            self::ROLES[$job === 'Sans emploi' ? 'neighbour' : 'colleague'][$en ? 1 : 0],
            sprintf('555-%04d', $h('telc') % 10000),
        ];

        // Papier : carte d'identité (ou le premier type du pays), numéro au format du pays, durée du pays.
        $types = $country['doc_types'] ?? ['id_card'];
        $type = in_array('id_card', $types, true) ? 'id_card' : (string) ($types[0] ?? 'id_card');
        $issued = $today->modify('-' . (1 + $h('iss') % 36) . ' months');
        $expires = $issued->modify('+' . SseIdCountryRepository::validityFor($country, $type) . ' years');
        $docNumber = SseIdentityGeneratorService::documentNumber((string) ($country['number_format'] ?? ''), $seed);
        $countryName = (string) ($country['name'] ?? $country['code'] ?? '');

        return [
            'seed' => $seed,
            'lang' => $en ? 'en' : 'fr',
            'name' => $name,
            'first_name' => $id['first_name'],
            'last_name' => $id['last_name'],
            'sex' => $id['sex'],
            'dob' => $id['birth_date'],
            'pob' => $id['birth_place'],
            'country' => $countryName,
            'country_code' => (string) ($country['code'] ?? ''),
            'address' => $this->street($seed, $en, $city),
            'city' => $city,
            'profession' => $en ? $spec[0] : $job,
            'employer' => $employer,
            'phone' => sprintf('555-%04d', $h('tel') % 10000),
            'travel' => $travel,
            'family' => $family,
            'history' => [
                $en ? sprintf('Born in %s, grew up in %s.', $id['birth_place'], $city) : sprintf('Né(e) à %s, a grandi à %s.', $id['birth_place'], $city),
                $en ? sprintf('Works for %s.', $employer) : sprintf('Travaille chez %s.', $employer),
            ],
            'contacts' => $contacts,
            'docs' => [[
                'type' => $type,
                'country' => $countryName,
                'number' => $docNumber,
                'issued' => $issued->format('Y-m-d'),
                'expires' => $expires->format('Y-m-d'),
            ]],
        ];
    }

    /**
     * Tire jusqu'à $tries couvertures et garde la première dont le nom et le numéro de papier sont libres.
     *
     * @param array<string, mixed> $country
     * @param array<string, true> $takenNames   noms complets en minuscules déjà pris
     * @param array<string, true> $takenNumbers numéros de papier en majuscules déjà pris
     * @return array<string, mixed>|null null = aucune couverture libre trouvée
     */
    public function generateUnique(array $country, array $takenNames, array $takenNumbers, ?int $seed = null, ?string $sex = null, string $lang = 'fr', int $tries = 12, ?\DateTimeImmutable $today = null): ?array
    {
        $seed ??= SseIdentityGeneratorService::randomSeed();
        for ($i = 0; $i < $tries; $i++) {
            $cover = $this->generate($country, ($seed + $i * 7919) % (SseIdentityGeneratorService::MAX_SEED + 1), $sex, $lang, $today);
            if (isset($takenNames[mb_strtolower($cover['name'])]) || isset($takenNumbers[strtoupper($cover['docs'][0]['number'])])) {
                continue;
            }

            return $cover;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $country
     * @return non-empty-list<string>
     */
    private function places(array $country, string $birthPlace): array
    {
        $places = SseIdentityGeneratorService::placesFor(strtoupper((string) ($country['region'] ?? '')));
        if ($places === []) {
            $places = array_values(array_filter([(string) ($country['name'] ?? ''), $birthPlace]));
        }

        return $places !== [] ? $places : ['Centre'];
    }

    private function street(int $seed, bool $en, string $city): string
    {
        $s = self::STREETS[SseIdentityGeneratorService::hash($seed, 'street') % count(self::STREETS)];
        $n = 1 + SseIdentityGeneratorService::hash($seed, 'streetn') % 98;

        return sprintf('%d %s, %s', $n, $s[$en ? 1 : 0], $city);
    }

    /**
     * Situation familiale selon l'âge ; proches nommés avec les réservoirs du pays.
     *
     * @param array<string, mixed> $country
     * @param array<string, mixed> $id
     * @return array{0: string, 1: list<array{name: string, role: string}>}
     */
    private function family(array $country, array $id, int $seed, int $age, bool $en): array
    {
        $relative = function (string $salt, ?string $sex, ?string $last = null) use ($country, $seed): string {
            $g = $this->identities->generate($country, SseIdentityGeneratorService::hash($seed, $salt) % (SseIdentityGeneratorService::MAX_SEED + 1), $sex);

            return $g['first_name'] . ' ' . ($last ?? $g['last_name']);
        };
        $married = $age >= 26 && SseIdentityGeneratorService::hash($seed, 'wed') % 3 !== 0;
        $sibling = ['name' => $relative('sib', null, $id['last_name']), 'role' => 'sibling'];
        if ($married) {
            $kids = SseIdentityGeneratorService::hash($seed, 'kids') % 4;
            $spouse = $relative('spouse', $id['sex'] === 'F' ? 'M' : 'F');
            $text = $en
                ? sprintf('Married to %s, %s.', $spouse, $kids === 0 ? 'no children' : ($kids === 1 ? '1 child' : $kids . ' children'))
                : sprintf('Marié(e) à %s, %s.', $spouse, $kids === 0 ? 'sans enfant' : ($kids === 1 ? '1 enfant' : $kids . ' enfants'));

            return [$text, [['name' => $spouse, 'role' => 'spouse'], $sibling]];
        }
        $parent = $relative('par', null, $id['last_name']);
        $text = $en ? sprintf('Single, lives near %s (parent).', $parent) : sprintf('Célibataire, vit près de %s (parent).', $parent);

        return [$text, [['name' => $parent, 'role' => 'parent'], $sibling]];
    }
}
