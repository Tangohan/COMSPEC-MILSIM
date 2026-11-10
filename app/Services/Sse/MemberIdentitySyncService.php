<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCoverLinkRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityHistoryRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;

/**
 * COMSPEC IDENTITY — identité Athena ↔ identité utilisée en jeu, sans écrasement.
 *
 * 1. Transmission en jeu (diagnose) : pour le compte, chaque identité et chaque papier, un état Prêt / Incomplet /
 *    Erroné / Bloqué, la liste de ce qui manque ou est faux, une phrase d'explication, un exemple et le geste qui corrige.
 *    Prêt : part en jeu tel quel. Incomplet : part, mais le jeu complète ou se trompe (sexe, photo, lieu). Erroné : part
 *    avec une donnée fausse (expiré, numéro hors format). Bloqué : ne part pas (non émis, révoqué, hors kit, Steam).
 * 2. Différence avec le jeu (diff, resolve) : l'identité remontée par le téléphone du joueur (cover_report, origine
 *    athena | generated | game | cover | import) comparée champ par champ à l'identité d'Athena. Rien n'est écrit sans
 *    un choix explicite par champ ; la valeur écartée, quel que soit le côté, va dans sse_identity_history et se restaure.
 * 3. Envoyer la version Athena vers le jeu (sendToGame) : kit par défaut composé des papiers valables de l'identité.
 *
 * Confidentialité : une identité d'emprunt, une couverture (remontée sans origine ou d'origine cover / import / game) ne
 * sont lues et traitées qu'avec la vérité de jeu (habilitation Confidentiel), comme le reste de l'onglet.
 */
final class MemberIdentitySyncService
{
    public const STATES = ['ready' => 'Prêt', 'incomplete' => 'Incomplet', 'error' => 'Erroné', 'blocked' => 'Bloqué'];

    /** Champs comparés au jeu : clé de l'identité Athena => [libellé, clé de la remontée du jeu]. */
    public const FIELDS = [
        'last_name' => ['Nom', 'last'],
        'first_name' => ['Prénom', 'first'],
        'birth_date' => ['Date de naissance', 'dob'],
        'birth_place' => ['Lieu de naissance', 'pob'],
        'nationality' => ['Nationalité', 'country'],
        'sex' => ['Sexe', 'sex'],
    ];

    /**
     * Origines qui relèvent de la vérité de jeu : couverture, couverture importée, papiers donnés en jeu (un faux remis par
     * le Zeus y passe aussi, sans dire sa nature). Seules « athena » et « generated » se lisent sans habilitation.
     */
    public const SECRET_ORIGINS = ['cover', 'import', 'game'];

    private const RANK = ['ready' => 0, 'incomplete' => 1, 'error' => 2, 'blocked' => 3];

    /** @var callable(array<string, mixed>): void */
    private $audit;

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdentityHistoryRepository $history = null,
        private ?SseIdCoverLinkRepository $covers = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseIdDocumentRepository $documents = null,
        ?callable $audit = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->history ??= new SseIdentityHistoryRepository();
        $this->covers ??= new SseIdCoverLinkRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Synchro identité : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
    }

    // ------------------------------------------------------------------ 1. Transmission en jeu

    /**
     * État de transmission en jeu. Entrées : MemberIdentityPapersService::section() (identités et papiers visibles,
     * kits, steam_linked) et les pays de la communauté (archivés compris).
     *
     * @param array<string, mixed> $section
     * @param list<array<string, mixed>> $countries
     * @return array{state: string, account: list<array<string, mixed>>, identities: array<int, array<string, mixed>>, documents: array<int, array<string, mixed>>, sent_identity_id: ?int, auto_kit: bool}
     */
    public static function diagnose(array $section, array $countries, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $byCode = [];
        foreach ($countries as $c) {
            $byCode[(string) $c['code']] = $c;
        }
        $identities = $section['identities'] ?? [];
        $docs = $section['documents'] ?? [];
        $kits = $section['kits'] ?? [];
        $primary = null;
        foreach ($identities as $i) {
            if (!empty($i['is_primary'])) {
                $primary = $i;
            }
        }
        $inKit = [];
        foreach ($kits as $k) {
            foreach ($k['document_ids'] ?? [] as $d) {
                $inKit[(int) $d] = true;
            }
        }
        $autoKit = $kits === [];
        $usable = static fn (array $d): bool => ($d['workflow'] ?? '') === 'issued'
            && !in_array($d['st_validity'] ?? 'valid', ['revoked', 'suspended'], true)
            && !in_array($d['st_report'] ?? 'none', ['stolen', 'lost'], true);
        $sent = static function (array $d) use ($autoKit, $inKit, $primary, $usable): bool {
            if ($autoKit) {
                return $primary !== null && (int) $d['identity_id'] === (int) $primary['id'] && $usable($d);
            }

            return isset($inKit[(int) $d['id']]) && ($d['workflow'] ?? '') === 'issued';
        };

        $account = [];
        if (empty($section['steam_linked'])) {
            $account[] = self::issue('steam', 'blocked', 'Compte Athena non lié au joueur Steam',
                'Le serveur de jeu reconnaît le joueur par son Steam ID : sans lui, aucun papier ne lui est remis et le jeu lui tire une identité au hasard.',
                'Ex. : identité « Jake GYLENHALL » sur Athena, « PAPADOPOULOS Panagiotis » en jeu.',
                ['label' => 'Le membre lie Steam dans son profil Athena', 'href' => '']);
        }
        if ($primary === null) {
            $account[] = self::issue('no_primary', 'blocked', 'Aucune identité principale',
                'Sans identité principale, Athena n’a rien à envoyer au jeu.', 'Ex. : reprendre la fiche du personnage.',
                ['label' => 'Créer l’identité principale', 'href' => '#papiers-identite']);
        }

        $docOut = [];
        $docsByIdentity = [];
        foreach ($docs as $d) {
            $id = (int) $d['id'];
            $docsByIdentity[(int) $d['identity_id']][] = $d;
            $issues = [];
            $wf = (string) ($d['workflow'] ?? 'draft');
            $country = $byCode[(string) ($d['country_code'] ?? '')] ?? null;
            if ($wf === 'revoked' || in_array($d['st_validity'] ?? 'valid', ['revoked', 'suspended'], true)) {
                $issues[] = self::issue('revoked', 'blocked', 'Papier révoqué : non transmis',
                    'Un papier révoqué ne part plus en jeu ; un contrôle en ligne le signale annulé.', 'Ex. : identité grillée, papier remplacé.',
                    ['label' => 'Dupliquer (nouveau numéro)', 'do' => 'duplicate', 'doc' => $id]);
            } elseif ($wf !== 'issued') {
                $issues[] = self::issue('not_issued', 'blocked', 'Papier non émis',
                    'Seuls les papiers émis partent dans le paquet du serveur de jeu.', 'Ex. : papier encore en brouillon ou seulement validé.',
                    ['label' => 'Émettre', 'do' => 'issue', 'doc' => $id]);
            }
            if (in_array($d['st_report'] ?? 'none', ['stolen', 'lost'], true)) {
                $issues[] = self::issue('reported', 'error', ($d['st_report'] === 'stolen' ? 'Papier déclaré volé' : 'Papier déclaré perdu'),
                    'Il n’est plus remis automatiquement ; s’il reste dans un kit, le contrôle en jeu le signale.', 'Ex. : carte ramassée sur le corps par un autre joueur.',
                    ['label' => 'Lever le signalement', 'do' => 'clear_report', 'doc' => $id]);
            }
            if ($country === null) {
                $issues[] = self::issue('country_unknown', 'error', 'Pays émetteur inconnu',
                    'Le pays de ce papier n’est plus dans le registre des pays : le jeu ne sait pas l’imprimer.', 'Ex. : pays supprimé du registre.',
                    ['label' => 'Modifier le papier', 'edit' => $id]);
            } elseif (!SseIdentityGeneratorService::matchesFormat((string) ($d['doc_number'] ?? ''), (string) ($country['number_format'] ?? ''))) {
                $issues[] = self::issue('number_format', 'error', 'Numéro au format invalide',
                    'Le numéro ne suit pas le format des papiers de ce pays : la bande MRZ et le contrôle N3 échouent.', 'Format attendu : ' . (string) $country['number_format'] . '.',
                    ['label' => 'Modifier le papier', 'edit' => $id]);
            }
            $exp = (string) ($d['expires_on'] ?? '');
            if ($exp === '') {
                $issues[] = self::issue('no_expiry', 'incomplete', 'Date de fin de validité manquante',
                    'Le jeu imprime une date au hasard et le contrôle ne peut pas vérifier la validité.', 'Ex. : 2034-07-16.',
                    ['label' => 'Modifier le papier', 'edit' => $id]);
            } elseif ($exp < $today) {
                $issues[] = self::issue('expired', 'error', 'Papier expiré',
                    'Il part en jeu, mais tout contrôle le déclare expiré.', 'Ex. : valable jusqu’au ' . $exp . '.',
                    ['label' => 'Modifier les dates', 'edit' => $id]);
            }
            if ($wf === 'issued' && $issues === [] && !$sent($d)) {
                $issues[] = $autoKit
                    ? self::issue('not_primary', 'blocked', 'Papier d’une autre identité : non transmis',
                        'Sans kit composé, seul le papier de l’identité principale part en jeu.', 'Ex. : alias ou couverture à remettre pour une opération.',
                        ['label' => 'Composer un kit', 'href' => '#papiers-kit'])
                    : self::issue('not_in_kit', 'blocked', 'Hors du kit de mission',
                        'Un kit est composé pour ce membre et ce papier n’y est pas : il ne part pas en jeu.', 'Ex. : kit de l’opération sans la carte d’identité.',
                        ['label' => 'Composer le kit', 'href' => '#papiers-kit']);
            }
            // « Prévu » : dans le paquet d'Athena. Remis réellement en jeu : seulement d'après ce que le jeu remonte (delivery).
            $docOut[$id] = self::summarize($issues, $sent($d) ? ($autoKit ? 'Prévu pour le jeu (kit automatique).' : 'Prévu pour le jeu (kit de mission).') : 'Ne part pas en jeu.');
        }

        $identityOut = [];
        $sentIdentity = null;
        foreach ($identities as $i) {
            $id = (int) $i['id'];
            $issues = [];
            $isCover = in_array($i['kind'] ?? '', ['cover', 'alias'], true);
            foreach (['last_name' => 'Nom', 'first_name' => 'Prénom'] as $k => $label) {
                $v = trim((string) ($i[$k] ?? ''));
                if ($v === '') {
                    $issues[] = self::issue($k . '_missing', 'error', $label . ' manquant', 'Le jeu ne peut pas imprimer un papier sans ' . mb_strtolower($label) . '.', 'Ex. : GYLENHALL / Jake.', ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
                } elseif (!self::validName($v)) {
                    $issues[] = self::issue($k . '_format', 'error', $label . ' au format invalide',
                        'Lettres, espaces, tirets et apostrophes seulement, 40 caractères au plus (bande MRZ).', 'Ex. : « De-Aligeara » oui, « J4ke » ou « <OSCAR> » non.',
                        ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
                }
            }
            $dob = (string) ($i['birth_date'] ?? '');
            if ($dob === '') {
                $issues[] = self::issue('dob_missing', 'incomplete', 'Date de naissance manquante',
                    'Le jeu en tire une au hasard : le contrôle compare alors deux dates différentes.', 'Ex. : 1988-07-16.',
                    ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
            } else {
                $age = (int) substr($today, 0, 4) - (int) substr($dob, 0, 4) - (substr($today, 5) < substr($dob, 5) ? 1 : 0);
                if ($dob > $today || $age < 16 || $age > 100) {
                    $issues[] = self::issue('dob_invalid', 'error', 'Date de naissance erronée',
                        'Âge impossible pour un personnage (moins de 16 ans, plus de 100 ans ou date future).', 'Ex. : ' . $dob . ' donne ' . $age . ' ans.',
                        ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
                }
            }
            if (trim((string) ($i['birth_place'] ?? '')) === '') {
                $issues[] = self::issue('pob_missing', 'incomplete', 'Lieu de naissance manquant',
                    'Le jeu imprime une ville au hasard, que le joueur ne connaîtra pas au contrôle.', 'Ex. : Colombus.',
                    ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
            }
            $nat = (string) ($i['nationality'] ?? '');
            if ($nat === '') {
                $issues[] = $isCover
                    ? self::issue('cover_no_country', 'incomplete', 'Identité de couverture sans pays', 'Sans pays, aucun papier crédible ne peut lui être émis.', 'Ex. : ALT (Altis).', ['label' => 'Modifier l’identité', 'href' => '#papiers-alias'])
                    : self::issue('nat_missing', 'incomplete', 'Nationalité manquante', 'Le jeu prend le pays de la mission, qui peut ne pas être le sien.', 'Ex. : USA (États-Unis).', ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
            } elseif (!isset($byCode[$nat])) {
                $issues[] = self::issue('nat_unknown', 'error', 'Nationalité inconnue du registre des pays', 'Ce code n’est pas un pays de jeu de la communauté.', 'Ex. : « US » au lieu de « USA ».', ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
            }
            if (!in_array($i['sex'] ?? 'X', ['M', 'F'], true)) {
                $issues[] = self::issue('sex_missing', 'incomplete', 'Sexe non précisé', 'Le jeu imprime M par défaut.', 'Ex. : M ou F.', ['label' => 'Modifier l’identité', 'href' => '#papiers-identite']);
            }
            if (($i['photo_path'] ?? null) === null) {
                $issues[] = self::issue('photo_missing', 'incomplete', 'Photo absente', 'Le papier d’Athena n’a pas de photo ; en jeu, la photo est le visage du personnage.', 'Ex. : photo d’identité ajoutée dans l’atelier des papiers.', ['label' => 'Ajouter une photo', 'href' => '']);
            }
            $mine = $docsByIdentity[$id] ?? [];
            $issued = array_values(array_filter($mine, static fn (array $d): bool => ($d['workflow'] ?? '') === 'issued'));
            if ($issued === []) {
                $issues[] = self::issue('no_issued', 'blocked', 'Aucun papier émis', 'Une identité ne part en jeu qu’avec au moins un papier émis.', 'Ex. : carte d’identité émise.',
                    ['label' => 'Générer un papier', 'new' => $id]);
            } elseif ($nat !== '' && array_filter($issued, static fn (array $d): bool => ($d['country_code'] ?? '') === $nat) === []) {
                $issues[] = self::issue('nat_no_paper', 'incomplete', 'Nationalité sans papier émis', 'Aucun papier émis par son pays : le contrôle s’étonne d’un étranger sans pièce de son pays.', 'Ex. : nationalité ' . $nat . ', seulement un permis d’un autre pays.',
                    ['label' => 'Générer un papier', 'new' => $id]);
            }
            $isSent = array_filter($mine, $sent) !== [];
            if ($isSent) {
                $sentIdentity ??= $id;
            } elseif ($issued !== []) {
                $issues[] = self::issue('identity_not_sent', 'blocked', 'Identité non transmise', $autoKit ? 'Sans kit composé, seule l’identité principale part en jeu.' : 'Aucun de ses papiers n’est dans le kit de mission.', 'Ex. : couverture prévue mais pas dans le kit.',
                    ['label' => 'Composer un kit', 'href' => '#papiers-kit']);
            }
            $identityOut[$id] = self::summarize($issues, $isSent ? 'Prévue pour le jeu.' : 'Pas utilisée en jeu.');
        }

        $worst = 'ready';
        foreach ($account as $a) {
            $worst = self::worse($worst, $a['level']);
        }
        if ($sentIdentity !== null) {
            $worst = self::worse($worst, $identityOut[$sentIdentity]['state']);
            foreach ($docs as $d) {
                if ($sent($d)) {
                    $worst = self::worse($worst, $docOut[(int) $d['id']]['state']);
                }
            }
        } elseif ($primary !== null) {
            $worst = 'blocked';
        }

        return ['state' => $worst, 'account' => $account, 'identities' => $identityOut, 'documents' => $docOut, 'sent_identity_id' => $sentIdentity, 'auto_kit' => $autoKit];
    }

    /**
     * Ce qui est réellement arrivé en jeu, d'après la dernière remontée du téléphone du joueur (cover_report : origine,
     * étape de livraison du paquet, relais, serveur). Sans remontée : rien n'est affirmé.
     * state : received (papiers d'Athena remis) | not_received (étape où la chaîne s'est arrêtée) | other (autre identité
     * en jeu, habilitation Confidentiel seulement) | unknown.
     *
     * @param list<array<string, mixed>> $gameCovers SseIdGameCoverService::forUser(), le plus récent d'abord
     * @return array{state: string, title: string, why: string, todo: string, at: string, server: string, identity_ref: string, docs: list<string>}
     */
    public static function delivery(array $gameCovers, bool $truth): array
    {
        $g = $gameCovers[0] ?? null;
        $out = ['state' => 'unknown', 'title' => 'Pas encore confirmé par le jeu', 'at' => '', 'server' => '', 'identity_ref' => '', 'docs' => [],
            'why' => 'Aucun téléphone n’a encore remonté ce qu’il a reçu en mission. « Prévu » veut dire : dans le paquet d’Athena, pas forcément arrivé.',
            'todo' => 'En jeu, le joueur ouvre Ma couverture sur son téléphone : l’état réel apparaît ici (réglage « Transmission automatique à Athena » de la communauté).'];
        if ($g === null) {
            return $out;
        }
        $origin = (string) ($g['origin'] ?? 'cover');
        $out['at'] = substr((string) ($g['updated_at'] ?? ''), 0, 16);
        $out['server'] = (string) ($g['server'] ?? '');
        $out['identity_ref'] = (string) ($g['identity_ref'] ?? '');
        $out['docs'] = array_values(array_filter(array_map(static fn (mixed $d): string => is_array($d) ? strtoupper(trim((string) ($d[2] ?? ''))) : '', $g['docs'] ?? [])));
        $step = (string) ($g['pack'] ?? '');
        $code = (string) ($g['pack_code'] ?? '');
        if ($origin === 'athena') {
            $why = [
                'server' => 'Le téléphone du joueur a confirmé ses papiers d’Athena (paquet lu par le serveur de jeu).',
                'zeus' => 'Le téléphone du joueur a confirmé ses papiers d’Athena (paquet relayé par le poste du Zeus).',
                'self' => 'Le téléphone du joueur a confirmé ses papiers d’Athena (papiers relayés par son propre téléphone).',
            ][(string) ($g['pack_via'] ?? '')] ?? 'Le téléphone du joueur a confirmé ses papiers d’Athena.';

            return ['state' => 'received', 'title' => 'Reçue en jeu', 'why' => $why, 'todo' => ''] + $out;
        }
        if ($origin !== 'generated') {
            return $truth
                ? ['state' => 'other', 'title' => 'Autre identité en jeu', 'why' => 'En jeu, le joueur utilise une couverture ou des papiers donnés en jeu, pas son identité Athena.', 'todo' => 'Voir « Différence avec le jeu » ci-dessous.'] + $out
                : $out;
        }
        $why = match (true) {
            $step === 'ok' => 'Le serveur de jeu a bien reçu le paquet d’Athena, mais aucun papier n’a été remis au joueur.',
            $step === 'server_fetch' && in_array($code, ['unauthorized', 'forbidden'], true) => 'Le serveur de jeu a COMSPEC Link, mais Athena refuse sa clé d’accès.',
            $step === 'server_fetch' && $code === 'no_tenant' => 'Le serveur de jeu a COMSPEC Link, mais sa clé ne désigne aucune communauté.',
            $step === 'server_fetch' => 'Le serveur de jeu a COMSPEC Link, mais Athena ne lui a pas répondu.',
            $step === 'relay_zeus' => 'Le serveur de jeu n’a pas COMSPEC Link : il a demandé le paquet au poste du Zeus, sans réponse.',
            $step === 'relay_self' => 'Le serveur de jeu n’a pas COMSPEC Link : il a demandé les papiers au téléphone du joueur, sans réponse.',
            $step === 'relay_fail' && $code === 'uplink_off' => 'Le serveur de jeu n’a pas COMSPEC Link et la liaison Athena est coupée sur le poste du joueur (service Athena sur Non).',
            $step === 'relay_fail' && in_array($code, ['unauthorized', 'forbidden', 'unlinked', 'no_tenant'], true) => 'Le serveur de jeu n’a pas COMSPEC Link et le compte Athena du joueur n’est pas relié en jeu.',
            $step === 'relay_fail' => 'Le serveur de jeu n’a pas COMSPEC Link et le poste qui devait relayer le paquet n’a pas pu le lire sur Athena.',
            $step === 'no_relay' => 'Le serveur de jeu n’a pas COMSPEC Link et aucun poste relié à Athena n’était connecté pour lui relayer le paquet.',
            $step === 'wait' => 'Le paquet d’Athena n’était pas encore arrivé sur le serveur de jeu quand le joueur a ouvert Ma couverture.',
            default => 'Le serveur de jeu n’avait pas reçu le paquet d’identité d’Athena : le mod a tiré une identité au hasard.',
        };
        $todo = match (true) {
            $step === 'ok' => 'Vérifiez ci-dessus : compte Steam lié, papier émis et dans le kit de cette carte.',
            $step === 'server_fetch' && in_array($code, ['unauthorized', 'forbidden', 'no_tenant'], true) => 'Le responsable du serveur renseigne la clé de communauté d’Athena dans COMSPEC Link du serveur.',
            in_array($code, ['unauthorized', 'forbidden', 'unlinked', 'no_tenant'], true) => 'Le joueur relie son compte Athena en jeu, puis touche Ma couverture › Demander la mise à jour.',
            $code === 'uplink_off' => 'Le joueur remet le service Athena sur Oui, puis touche Ma couverture › Demander la mise à jour.',
            default => 'Le mod réessaie seul ; en jeu, Ma couverture › Demander la mise à jour relance tout de suite. En attendant : Télécharger ma couverture ci-dessous, puis Importer ma couverture en jeu.',
        };

        return ['state' => 'not_received', 'title' => 'Pas reçue en jeu', 'why' => $why, 'todo' => $todo] + $out;
    }

    /**
     * Notes « Prévu » de diagnose() remplacées par ce que le jeu confirme (delivery()).
     *
     * @param array<string, mixed> $diag diagnose()
     * @param array<string, mixed> $delivery delivery()
     * @param list<array<string, mixed>> $documents papiers visibles (doc_number)
     * @return array<string, mixed>
     */
    public static function withDelivery(array $diag, array $delivery, array $documents): array
    {
        $diag['delivery'] = $delivery;
        $sent = (int) ($diag['sent_identity_id'] ?? 0);
        if ($delivery['state'] === 'received') {
            $refId = (int) preg_replace('/\D/', '', $delivery['identity_ref']);
            $target = $refId > 0 ? $refId : $sent;
            if (isset($diag['identities'][$target])) {
                $diag['identities'][$target]['note'] = 'Reçue en jeu le ' . $delivery['at'] . ' UTC.';
            }
            foreach ($documents as $d) {
                $id = (int) $d['id'];
                if (isset($diag['documents'][$id]) && in_array(strtoupper((string) ($d['doc_number'] ?? '')), $delivery['docs'], true)) {
                    $diag['documents'][$id]['note'] = 'Remis en jeu le ' . $delivery['at'] . ' UTC.';
                }
            }
        } elseif ($delivery['state'] === 'not_received' && isset($diag['identities'][$sent])) {
            $diag['identities'][$sent]['note'] = 'Prévue pour le jeu, mais pas reçue en jeu le ' . $delivery['at'] . ' UTC.';
        }

        return $diag;
    }

    /** Lettres (accents compris), espaces, tirets, apostrophes ; 40 caractères au plus (bande MRZ). */
    public static function validName(string $v): bool
    {
        return mb_strlen($v) <= 40 && preg_match("/^\\p{L}[\\p{L}\\p{M}' .\\-]*$/u", $v) === 1;
    }

    // ------------------------------------------------------------------ 2. Différence avec le jeu

    /**
     * Identité remontée du jeu → champs comparables (nom et prénom séparés ; « NOM Prénom » d'un ancien mod découpé).
     *
     * @param array<string, mixed> $game
     * @return array<string, string>
     */
    public static function gameFields(array $game): array
    {
        $last = trim((string) ($game['last'] ?? ''));
        $first = trim((string) ($game['first'] ?? ''));
        if ($last === '' && $first === '') {
            $words = preg_split('/\s+/u', trim((string) ($game['name'] ?? ''))) ?: [];
            $lastParts = [];
            foreach ($words as $w) {
                if ($first === '' && $w !== '' && mb_strtoupper($w) === $w && preg_match('/\p{Lu}/u', $w) === 1) {
                    $lastParts[] = $w;
                } else {
                    $first .= ($first === '' ? '' : ' ') . $w;
                }
            }
            $last = implode(' ', $lastParts);
        }
        $dob = trim((string) ($game['dob'] ?? ''));

        return [
            'last_name' => $last,
            'first_name' => $first,
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}/', $dob) === 1 ? substr($dob, 0, 10) : $dob,
            'birth_place' => trim((string) ($game['pob'] ?? '')),
            'nationality' => strtoupper(trim((string) ($game['country'] ?? ''))),
            'sex' => strtoupper(trim((string) ($game['sex'] ?? ''))),
        ];
    }

    /**
     * Champs qui diffèrent (le jeu n'a pas envoyé un champ : il n'est pas comparé). Nom et lieu : sans casse ni espaces
     * en trop ; nationalité : code du pays.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $game
     * @return list<array{field: string, label: string, athena: string, game: string}>
     */
    public static function diff(array $identity, array $game): array
    {
        $g = self::gameFields($game);
        $norm = static fn (string $v): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $v)));
        $out = [];
        foreach (self::FIELDS as $field => [$label]) {
            $gv = $g[$field] ?? '';
            if ($gv === '') {
                continue;
            }
            $av = (string) ($identity[$field] ?? '');
            if ($norm($av) !== $norm($gv)) {
                $out[] = ['field' => $field, 'label' => $label, 'athena' => $av, 'game' => $gv];
            }
        }

        return $out;
    }

    /**
     * Remontées du jeu visibles par ce responsable, avec la différence pour chacune.
     *
     * @param list<array<string, mixed>> $identities identités visibles (section)
     * @return list<array<string, mixed>>
     */
    public function gameReports(int $tenantId, int $userId, array $identities, bool $truth): array
    {
        $byId = [];
        $primary = null;
        foreach ($identities as $i) {
            $byId[(int) $i['id']] = $i;
            if (!empty($i['is_primary'])) {
                $primary = $i;
            }
        }
        // Champs déjà tranchés en faveur d'Athena pour ce contenu du jeu (même valeur) : ils ne sont plus redemandés.
        $decided = [];
        foreach ($this->historyFor($tenantId, $userId, $identities) as $h) {
            if (($h['restored_at'] ?? null) === null && ($h['lost_side'] ?? '') === 'game' && ($h['game_cover_id'] ?? null) !== null) {
                $decided[(int) $h['game_cover_id'] . '|' . $h['field'] . '|' . mb_strtolower(trim((string) $h['lost_value']))] = true;
            }
        }
        $out = [];
        foreach ((new SseIdGameCoverService($this->covers))->forUser($tenantId, $userId) as $g) {
            $origin = (string) ($g['origin'] ?? 'cover');
            if (!$truth && in_array($origin, self::SECRET_ORIGINS, true)) {
                continue;
            }
            $refId = (int) preg_replace('/\D/', '', (string) ($g['identity_ref'] ?? ''));
            $identity = $byId[$refId] ?? $primary;
            if ($identity === null) {
                continue;
            }
            $g['identity'] = $identity;
            $g['diff'] = array_values(array_filter(
                self::diff($identity, $g),
                static fn (array $d): bool => !isset($decided[(int) $g['id'] . '|' . $d['field'] . '|' . mb_strtolower(trim($d['game']))])
            ));
            $out[] = $g;
        }

        return $out;
    }

    /**
     * Reprise explicite, champ par champ. $choices : champ => athena | game ; $keepBoth : tous les champs gardent
     * Athena et la version du jeu est conservée dans l'historique. Un champ sans choix n'est pas touché, et la différence
     * reste ouverte tant qu'il en reste un. Chaque valeur écartée (Athena ou jeu) est historisée, restaurable.
     *
     * @param array<string, string> $choices
     * @return array{ok: bool, message: string, taken?: int, kept?: int}
     */
    public function resolve(int $tenantId, int $userId, int $gameCoverId, array $choices, bool $keepBoth, bool $truth, ?int $actorId): array
    {
        $row = $this->covers->findGameCoverById($tenantId, $userId, $gameCoverId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Identité du jeu introuvable.'];
        }
        $report = null;
        foreach ($this->gameReports($tenantId, $userId, $this->visibleIdentities($tenantId, $userId, $truth), $truth) as $g) {
            if ((int) $g['id'] === $gameCoverId) {
                $report = $g;
            }
        }
        if ($report === null) {
            return ['ok' => false, 'message' => 'Cette identité du jeu demande l’habilitation Confidentiel.'];
        }
        $identity = $report['identity'];
        $countryCodes = array_column((new \App\Repositories\SseIdCountryRepository())->listForTenant($tenantId, false), 'code');
        $taken = 0;
        $kept = 0;
        $pending = 0;
        $changes = [];
        foreach ($report['diff'] as $d) {
            $choice = $keepBoth ? 'athena' : ($choices[$d['field']] ?? '');
            if (!in_array($choice, ['athena', 'game'], true)) {
                $pending++;
                continue;
            }
            if ($choice === 'game') {
                $error = self::invalidGameValue($d['field'], $d['game'], $countryCodes);
                if ($error !== null) {
                    return ['ok' => false, 'message' => $d['label'] . ' du jeu refusé : ' . $error];
                }
                $changes[$d['field']] = $d['game'];
            }
            $this->history->add($tenantId, [
                'user_id' => $userId, 'identity_id' => (int) $identity['id'], 'field' => $d['field'],
                'kept_value' => $choice === 'game' ? $d['game'] : $d['athena'],
                'lost_value' => $choice === 'game' ? ($d['athena'] !== '' ? $d['athena'] : null) : $d['game'],
                'lost_side' => $choice === 'game' ? 'athena' : 'game',
                'decision' => $keepBoth ? 'both' : $choice,
                'game_cover_id' => $gameCoverId, 'actor_user_id' => $actorId,
            ]);
            $choice === 'game' ? $taken++ : $kept++;
        }
        if ($taken + $kept === 0) {
            return ['ok' => false, 'message' => 'Choisissez, pour chaque champ, la version à garder.'];
        }
        if ($changes !== []) {
            $this->identities->update((int) $identity['id'], $tenantId, array_merge($identity, $changes));
        }
        if ($pending === 0) {
            $this->covers->markGameCoverReviewed($tenantId, $userId, $gameCoverId, (string) $row['content_hash'], gmdate('Y-m-d H:i:s'));
        }
        $this->log($tenantId, $actorId, $keepBoth ? 'id_member.sync_keep_both' : 'id_member.sync_resolve', (int) $identity['id'], $userId, [
            'game_cover' => $gameCoverId, 'taken_from_game' => array_keys($changes), 'kept_athena' => $kept, 'pending' => $pending,
        ]);

        return ['ok' => true, 'taken' => $taken, 'kept' => $kept, 'message' => $keepBoth
            ? 'Version d’Athena gardée ; la version du jeu est conservée dans l’historique (restaurable).'
            : sprintf('%d champ(s) repris du jeu, %d gardé(s) d’Athena%s. Les valeurs écartées sont dans l’historique.', $taken, $kept, $pending > 0 ? ', ' . $pending . ' encore à trancher' : '')];
    }

    /**
     * Restaure une valeur écartée : elle revient dans l'identité d'Athena, la valeur qu'elle remplace part à son tour
     * dans l'historique.
     *
     * @return array{ok: bool, message: string}
     */
    public function restore(int $tenantId, int $userId, int $historyId, bool $truth, ?int $actorId): array
    {
        $h = $this->history->find($tenantId, $userId, $historyId);
        if ($h === null || ($h['restored_at'] ?? null) !== null) {
            return ['ok' => false, 'message' => 'Entrée d’historique introuvable ou déjà restaurée.'];
        }
        $identity = null;
        foreach ($this->visibleIdentities($tenantId, $userId, $truth) as $i) {
            if ((int) $i['id'] === (int) $h['identity_id']) {
                $identity = $i;
            }
        }
        $field = (string) $h['field'];
        if ($identity === null || !isset(self::FIELDS[$field])) {
            return ['ok' => false, 'message' => 'Identité introuvable.'];
        }
        $value = (string) ($h['lost_value'] ?? '');
        $current = (string) ($identity[$field] ?? '');
        if ($value !== '' && ($error = self::invalidGameValue($field, $value, array_column((new \App\Repositories\SseIdCountryRepository())->listForTenant($tenantId, false), 'code'))) !== null) {
            return ['ok' => false, 'message' => 'Valeur non restaurable : ' . $error];
        }
        $this->history->add($tenantId, [
            'user_id' => $userId, 'identity_id' => (int) $identity['id'], 'field' => $field,
            'kept_value' => $value !== '' ? $value : null, 'lost_value' => $current !== '' ? $current : null,
            'lost_side' => 'athena', 'decision' => 'restore', 'game_cover_id' => ($h['game_cover_id'] ?? null) !== null ? (int) $h['game_cover_id'] : null,
            'actor_user_id' => $actorId,
        ]);
        $this->identities->update((int) $identity['id'], $tenantId, array_merge($identity, [$field => $value !== '' ? $value : null]));
        $this->history->markRestored($tenantId, $historyId, $actorId);
        $this->log($tenantId, $actorId, 'id_member.sync_restore', (int) $identity['id'], $userId, ['history' => $historyId, 'field' => $field]);

        return ['ok' => true, 'message' => self::FIELDS[$field][0] . ' restauré ; la valeur remplacée est dans l’historique.'];
    }

    /**
     * « Envoyer la version Athena vers le jeu » : kit par défaut composé des papiers valables de l'identité (un par
     * type, le plus récent). Le serveur de jeu le reçoit au prochain chargement du paquet.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendToGame(int $tenantId, int $userId, int $identityId, bool $truth, ?int $actorId): array
    {
        $identity = null;
        foreach ($this->visibleIdentities($tenantId, $userId, $truth) as $i) {
            if ((int) $i['id'] === $identityId) {
                $identity = $i;
            }
        }
        if ($identity === null) {
            return ['ok' => false, 'message' => 'Identité introuvable.'];
        }
        $byType = [];
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            if ((int) $d['identity_id'] === $identityId && $d['workflow'] === 'issued'
                && !in_array($d['st_validity'], ['revoked', 'suspended'], true) && !in_array($d['st_report'], ['stolen', 'lost'], true)
                && (!isset($byType[$d['doc_type']]) || (int) $d['id'] > $byType[$d['doc_type']])) {
                $byType[$d['doc_type']] = (int) $d['id'];
            }
        }
        if ($byType === []) {
            return ['ok' => false, 'message' => 'Aucun papier émis et valable pour cette identité : émettez-en un d’abord.'];
        }
        $n = (new MemberIdentityPapersService($this->identities, $this->documents, $this->kits, null, null, $this->audit))
            ->saveKit($tenantId, $userId, null, null, array_values($byType), $truth, $actorId);
        $this->log($tenantId, $actorId, 'id_member.sync_send_game', $identityId, $userId, ['documents' => array_values($byType)]);

        return ['ok' => $n > 0, 'message' => $n > 0
            ? 'Version d’Athena envoyée vers le jeu (kit par défaut, ' . $n . ' papier(s)) : remise au prochain chargement du paquet, ou en jeu par Ma couverture › Demander la mise à jour.'
            : 'Rien n’a pu être envoyé.'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyFor(int $tenantId, int $userId, array $identities): array
    {
        try {
            return $this->history->listForIdentities($tenantId, $userId, array_map(static fn (array $i): int => (int) $i['id'], $identities));
        } catch (\Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------ Outils

    /** @param list<string> $countryCodes */
    public static function invalidGameValue(string $field, string $value, array $countryCodes): ?string
    {
        return match ($field) {
            'last_name', 'first_name' => self::validName($value) ? null : 'format invalide.',
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? null : 'date au format AAAA-MM-JJ attendue.',
            'nationality' => in_array($value, $countryCodes, true) ? null : 'pays inconnu du registre de la communauté.',
            'sex' => in_array($value, ['M', 'F'], true) ? null : 'M ou F attendu.',
            'birth_place' => mb_strlen($value) <= 120 ? null : 'trop long.',
            default => 'champ non modifiable.',
        };
    }

    /** @return list<array<string, mixed>> */
    private function visibleIdentities(int $tenantId, int $userId, bool $truth): array
    {
        return array_values(array_filter(
            $this->identities->listForUser($tenantId, $userId),
            static fn (array $i): bool => $truth || !in_array($i['kind'], MemberIdentityPapersService::SECRET, true)
        ));
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @return array{state: string, state_label: string, issues: list<array<string, mixed>>, note: string}
     */
    private static function summarize(array $issues, string $note): array
    {
        $state = 'ready';
        foreach ($issues as $i) {
            $state = self::worse($state, $i['level']);
        }

        return ['state' => $state, 'state_label' => self::STATES[$state], 'issues' => $issues, 'note' => $note];
    }

    private static function worse(string $a, string $b): string
    {
        return self::RANK[$b] > self::RANK[$a] ? $b : $a;
    }

    /**
     * @param array<string, mixed> $fix label, et l'un de : href (ancre / URL), do + doc (action sur un papier), edit (papier),
     *                                  new (nouveau papier pour l'identité)
     * @return array{code: string, level: string, title: string, why: string, example: string, fix: array<string, mixed>}
     */
    private static function issue(string $code, string $level, string $title, string $why, string $example, array $fix): array
    {
        return ['code' => $code, 'level' => $level, 'title' => $title, 'why' => $why, 'example' => $example, 'fix' => $fix];
    }

    /** @param array<string, mixed> $extra */
    private function log(int $tenantId, ?int $actorId, string $action, int $identityId, int $memberId, array $extra = []): void
    {
        ($this->audit)([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'actor_label' => 'Fiche membre',
            'action' => $action,
            'object_type' => 'sse_identity',
            'object_id' => $identityId,
            'after' => ['member_user_id' => $memberId] + $extra,
        ]);
    }
}
