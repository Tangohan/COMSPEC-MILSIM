<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Tirage d'une identité fictive cohérente (bouton « Générer » du registre).
 *
 * Même tirage que le générateur du mod SSE : hachage déterministe comspec_sse_fnc_hash (deux voies, ×31 mod 524287 et
 * ×33 mod 508361, résultat sur 24 bits) et choix comspec_sse_fnc_pickFromSeed (hachage modulo la taille de la liste).
 * Une graine donne toujours la même identité, sur le site comme en jeu. Réservoirs de noms : par pays fictif, sinon
 * ceux des régions du générateur SSE (fn_getNarrativePools).
 */
final class SseIdentityGeneratorService
{
    public const MAX_SEED = 16777215;

    /** @var array<string, array{m: list<string>, f: list<string>, last: list<string>, places: list<string>}> */
    private const COUNTRY_POOLS = [
        'ALTIS' => [
            'm' => ['Nikos', 'Giorgos', 'Dimitris', 'Kostas', 'Yannis', 'Christos', 'Panagiotis', 'Vasilis', 'Stavros', 'Andreas', 'Michalis', 'Petros', 'Spyros', 'Thanos', 'Alexandros'],
            'f' => ['Maria', 'Eleni', 'Katerina', 'Sofia', 'Anna', 'Georgia', 'Despina', 'Ioanna', 'Dimitra', 'Vasiliki', 'Christina', 'Angeliki'],
            'last' => ['Papadopoulos', 'Georgiou', 'Nikolaidis', 'Konstantinou', 'Dimitriou', 'Papadakis', 'Vlachos', 'Karagiannis', 'Oikonomou', 'Makris', 'Antoniou', 'Stavrou', 'Pappas', 'Kyriakou', 'Theodorou', 'Mavridis'],
            'places' => ['Kavala', 'Pyrgos', 'Athira', 'Sofia', 'Zaros', 'Neochori', 'Agios Dionysios', 'Paros', 'Charkia', 'Telos', 'Kore', 'Molos'],
        ],
        'TANOA' => [
            'm' => ['Josefa', 'Viliame', 'Semi', 'Tevita', 'Jone', 'Peni', 'Etonia', 'Malakai', 'Isikeli', 'Ratu', 'Samuela', 'Waisea'],
            'f' => ['Mere', 'Ana', 'Litia', 'Salote', 'Losana', 'Vika', 'Asenaca', 'Unaisi', 'Mereani', 'Sereana'],
            'last' => ['Naivalu', 'Tuilagi', 'Rabuka', 'Vunipola', 'Koroi', 'Tabua', 'Delai', 'Nakarawa', 'Ravouvou', 'Lomani', 'Waqa', 'Seru', 'Baleiwai', 'Matanitobua'],
            'places' => ['Georgetown', 'Lijnhaven', 'Katkoula', 'Tuvanaka', 'La Rochelle', 'Balavu', 'Ouméré', 'Savaka', 'Moddergat', 'Nicolet'],
        ],
        'LIVONIA' => [
            'm' => ['Piotr', 'Tomasz', 'Krzysztof', 'Paweł', 'Michał', 'Marek', 'Jakub', 'Andrzej', 'Grzegorz', 'Łukasz', 'Wojciech', 'Kamil'],
            'f' => ['Anna', 'Katarzyna', 'Magdalena', 'Agnieszka', 'Joanna', 'Monika', 'Ewa', 'Zofia', 'Małgorzata', 'Natalia'],
            'last' => ['Nowak', 'Kowalski', 'Wiśniewski', 'Wójcik', 'Kamiński', 'Lewandowski', 'Zieliński', 'Szymański', 'Woźniak', 'Dąbrowski', 'Kozłowski', 'Jankowski', 'Mazur', 'Krawczyk'],
            'places' => ['Lubjansk', 'Gieraltów', 'Topolin', 'Nadbór', 'Sobótka', 'Dolnik', 'Grabin', 'Lipina', 'Huta', 'Wrzeszcz'],
        ],
        'MALDEN' => [
            'm' => ['Jean', 'Pierre', 'Louis', 'Antoine', 'Mathieu', 'Nicolas', 'Julien', 'Olivier', 'Thomas', 'Laurent', 'Étienne', 'Rémi'],
            'f' => ['Marie', 'Claire', 'Camille', 'Julie', 'Élodie', 'Sophie', 'Anne', 'Céline', 'Manon', 'Hélène'],
            'last' => ['Martin', 'Bernard', 'Moreau', 'Laurent', 'Lefèvre', 'Girard', 'Rousseau', 'Fournier', 'Morel', 'Guérin', 'Mercier', 'Blanc', 'Faure', 'André', 'Chevalier', 'Leroy'],
            'places' => ['Le Port', 'La Pessagne', 'Lolisse', 'Goisse', 'Arudy', 'Chapoi', 'Larche', 'Houdan', 'Vigny', 'Saint-Louis'],
        ],
    ];

    /** Réservoirs des régions du générateur SSE (fn_getNarrativePools), pour un pays sans réservoir propre. */
    private const SSE_POOLS = [
        'LEVANT' => [
            'first' => ['Karim', 'Omar', 'Hassan', 'Farid', 'Mustafa', 'Youssef', 'Ali', 'Samir', 'Rami', 'Nabil', 'Tarek', 'Walid', 'Adnan', 'Bilal', 'Faisal', 'Mahmoud', 'Ibrahim', 'Khaled', 'Ziad', 'Bassam', 'Ghassan', 'Marwan', 'Sami', 'Amine', 'Hicham', 'Yasin', 'Anas', 'Othman', 'Jamil', 'Rafik'],
            'last' => ['Haddad', 'Mansour', 'Khoury', 'Nasser', 'Saleh', 'Farouk', 'Abbas', 'Rahman', 'Qasim', 'Hussein', 'Darwish', 'Amari', 'Saad', 'Zidan', 'Khalil', 'Alami', 'Badr', 'Hamdan', 'Jaber', 'Kassab', 'Najjar', 'Sabbagh', 'Tawil', 'Younes', 'Zahra', 'Barakat', 'Fakhoury', 'Ghazi', 'Halabi', 'Issa'],
        ],
        'AFRICA_SAHEL' => [
            'first' => ['Amadou', 'Ibrahim', 'Moussa', 'Oumar', 'Abdoulaye', 'Boubacar', 'Seydou', 'Mamadu', 'Issa', 'Bakary', 'Cheick', 'Modibo', 'Youssouf', 'Souleymane', 'Hamza', 'Idrissa', 'Aboubacar', 'Mahamadou', 'Sidi', 'Lassana'],
            'last' => ['Traoré', 'Diallo', 'Keita', 'Touré', 'Cissé', 'Konaté', 'Sangaré', 'Coulibaly', 'Dembélé', 'Diop', 'Ba', 'Sow', 'Camara', 'Sy', 'Kane', 'Ndiaye', 'Fall', 'Gueye', 'Diarra', 'Fofana'],
        ],
        'RUSSIA' => [
            'first' => ['Ivan', 'Dmitri', 'Sergei', 'Andrei', 'Pavel', 'Alexei', 'Nikolai', 'Viktor', 'Mikhail', 'Yuri', 'Oleg', 'Roman', 'Anton', 'Kirill', 'Igor', 'Vladimir', 'Maxim', 'Denis', 'Artem', 'Boris', 'Gleb', 'Stepan', 'Timur', 'Ruslan', 'Fyodor', 'Anatoly', 'Vadim', 'Konstantin', 'Grigori', 'Leonid'],
            'last' => ['Ivanov', 'Petrov', 'Sidorov', 'Smirnov', 'Kuznetsov', 'Popov', 'Vasiliev', 'Sokolov', 'Mikhailov', 'Novikov', 'Fedorov', 'Morozov', 'Volkov', 'Alekseev', 'Lebedev', 'Semenov', 'Egorov', 'Pavlov', 'Kozlov', 'Stepanov', 'Nikolaev', 'Orlov', 'Andreev', 'Makarov', 'Nikitin'],
        ],
        'GENERIC' => [
            'first' => ['Karim', 'Omar', 'Hassan', 'Farid', 'Mustafa', 'Youssef', 'Ali', 'Samir', 'Rami', 'Nabil', 'John', 'Viktor', 'Sergei', 'Andrei', 'Pavel', 'Ivan', 'Dmitri', 'Alex', 'Mark', 'Daniel'],
            'last' => ['Haddad', 'Mansour', 'Petrov', 'Ivanov', 'Kowalski', 'Novak', 'Horvat', 'Popescu', 'Dimitrov', 'Rossi', 'Garcia', 'Silva', 'Smith', 'Brown', 'Miller'],
        ],
    ];

    private const PROFESSIONS = [
        'Agriculteur', 'Commerçant', 'Chauffeur', 'Mécanicien', 'Pêcheur', 'Enseignant', 'Étudiant', 'Ouvrier du bâtiment',
        'Infirmier', 'Fonctionnaire', 'Électricien', 'Sans emploi', 'Boulanger', 'Artisan', 'Gardien',
    ];

    /** Lettres d'un numéro de papier (sans I, O, Q, trop proches de 1 et 0). */
    private const NUMBER_LETTERS = 'ABCDEFGHJKLMNPRSTUVWXYZ';

    /** Portage exact de comspec_sse_fnc_hash. */
    public static function hash(int $seed, int|string $salt): int
    {
        $p1 = 524287;
        $p2 = 508361;
        $s = abs($seed);
        $a = $s % $p1;
        $b = $s % $p2;
        if (is_string($salt)) {
            foreach (mb_str_split($salt) as $ch) {
                $c = mb_ord($ch) % 1024;
                $a = (($a * 31) + $c) % $p1;
                $b = (($b * 33) + $c) % $p2;
            }
        } else {
            $n = abs($salt);
            $a = (($a * 31) + ($n % $p1)) % $p1;
            $b = (($b * 33) + ($n % $p2)) % $p2;
        }

        return ($a * 32) + ($b % 32);
    }

    /**
     * Portage de comspec_sse_fnc_pickFromSeed.
     *
     * @param list<string> $list
     */
    public static function pick(int $seed, string $salt, array $list): string
    {
        if ($list === []) {
            return '';
        }

        return $list[self::hash($seed, $salt) % count($list)];
    }

    public static function randomSeed(): int
    {
        return random_int(1, self::MAX_SEED);
    }

    /**
     * Identité complète pour un pays (country = SseIdCountryRepository::hydrate()).
     *
     * @param array<string, mixed> $country
     * @return array{seed: int, kind: string, last_name: string, first_name: string, sex: string, birth_date: string, birth_place: string, nationality: string, profession: string}
     */
    public function generate(array $country, ?int $seed = null, ?string $sex = null): array
    {
        $seed = $seed !== null && $seed >= 0 ? $seed % (self::MAX_SEED + 1) : self::randomSeed();
        $region = strtoupper((string) ($country['region'] ?? ''));
        $code = strtoupper((string) ($country['code'] ?? ''));
        $sex = in_array($sex, ['M', 'F'], true) ? $sex : (self::hash($seed, 'sex') % 2 === 0 ? 'M' : 'F');

        $pool = self::COUNTRY_POOLS[$region] ?? \App\Support\SseIdRealCountryCatalog::POOLS[$region] ?? null;
        if ($pool !== null) {
            // Réservoirs des pays fictifs, puis ceux des pays réels stylisés (par langue / région, même tirage en jeu).
            $first = self::pick($seed, 'fn', $sex === 'F' ? $pool['f'] : $pool['m']);
            $last = self::pick($seed, 'ln', $pool['last']) . ($sex === 'F' ? (string) ($pool['f_suffix'] ?? '') : '');
            $place = self::pick($seed, 'pob', $pool['places']);
        } else {
            $pool = self::SSE_POOLS[in_array($region, ['IRAQ', 'SYRIA'], true) ? 'LEVANT' : $region] ?? self::SSE_POOLS['GENERIC'];
            $sex = 'M';
            $first = self::pick($seed, 'fn', $pool['first']);
            $last = self::pick($seed, 'ln', $pool['last']);
            $place = (string) ($country['name'] ?? '');
        }

        $year = (int) gmdate('Y') - (18 + self::hash($seed, 'age') % 48);
        $month = 1 + self::hash($seed, 'mon') % 12;
        $day = 1 + self::hash($seed, 'day') % 28;

        return [
            'seed' => $seed,
            'kind' => 'generated',
            'last_name' => $last,
            'first_name' => $first,
            'sex' => $sex,
            'birth_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'birth_place' => $place,
            'nationality' => $code,
            'profession' => self::pick($seed, 'job', self::PROFESSIONS),
        ];
    }

    /**
     * Numéro au format du pays : « # » chiffre, « @ » lettre, le reste recopié. $salt change le tirage (doublon).
     */
    public static function documentNumber(string $format, int $seed, string $salt = ''): string
    {
        $format = $format !== '' ? $format : '@@#######';
        $out = '';
        $len = mb_strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($format, $i, 1);
            if ($ch === '#') {
                $out .= (string) (self::hash($seed, 'n' . $salt . $i) % 10);
            } elseif ($ch === '@') {
                $out .= self::NUMBER_LETTERS[self::hash($seed, 'l' . $salt . $i) % strlen(self::NUMBER_LETTERS)];
            } else {
                $out .= strtoupper($ch);
            }
        }

        return $out;
    }

    /** Le numéro saisi respecte-t-il le format du pays ? */
    public static function matchesFormat(string $number, string $format): bool
    {
        if ($format === '') {
            return $number !== '';
        }
        $re = '';
        $len = mb_strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($format, $i, 1);
            $re .= match ($ch) {
                '#' => '[0-9]',
                '@' => '[A-Z]',
                default => preg_quote(strtoupper($ch), '/'),
            };
        }

        return preg_match('/^' . $re . '$/', strtoupper($number)) === 1;
    }
}
