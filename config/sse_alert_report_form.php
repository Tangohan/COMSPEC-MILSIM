<?php

declare(strict_types=1);

/**
 * Contrat du formulaire de signalement d'une alerte SSE (avis de recherche, fiche de vigilance) : quels champs la page montre et
 * lesquels sont demandés, selon la conduite à tenir de la fiche (codes « A-XXX » du catalogue config/sse_wanted_codes.php) et son
 * niveau. Données seulement : App\Support\SseAlertReportForm les combine. Un code absent d'ici n'ajoute rien (formulaire de base).
 *
 * Champs communs, toujours présents : nature (vu / information), date et heure, lieu, repère de grille, description, certitude.
 * Champs adaptables (clés de « fields ») : dir, vehicle, people, armed, distance, duration, behaviour, photo.
 *
 * Règles d'une mesure ou d'un niveau :
 *  - add     : champs ajoutés ;
 *  - drop    : champs retirés (l'emporte toujours sur « add » : jamais de photo là où la conduite interdit d'approcher) ;
 *  - require : champs obligatoires (seulement s'ils sont montrés : ce qui n'est pas affiché n'est jamais exigé) ;
 *  - urgent  : met le service à informer et le délai en tête du formulaire ;
 *  - note    : consigne propre au formulaire, [français, anglais].
 *
 * @return array{
 *   default: list<string>,
 *   fields: array<string, array<string, mixed>>,
 *   measures: array<string, array<string, mixed>>,
 *   levels: array<int, array<string, mixed>>,
 *   armed: array<string, mixed>
 * }
 */

return [
    // Champs montrés quand aucune règle ne les retire.
    'default' => ['dir', 'vehicle', 'photo'],

    'fields' => [
        'dir' => ['type' => 'text', 'max' => 120, 'label' => ['Direction', 'Direction'], 'hint' => ['Vers le nord, sur la route de…', 'Heading north, on the road to…'], 'missing' => ['Indiquez la direction prise.', 'Say which way the subject went.']],
        'vehicle' => ['type' => 'text', 'max' => 160, 'label' => ['Véhicule', 'Vehicle'], 'hint' => ['Type, couleur, plaque', 'Type, colour, plate'], 'missing' => ['Décrivez le véhicule.', 'Describe the vehicle.']],
        'people' => ['type' => 'count', 'max' => 99, 'label' => ['Nombre de personnes', 'Number of people'], 'hint' => ['Ex. 3', 'E.g. 3'], 'missing' => ['Indiquez le nombre de personnes vues.', 'Say how many people you saw.']],
        'armed' => ['type' => 'choice', 'label' => ['Armement vu', 'Weapons seen'], 'missing' => ['Indiquez si vous avez vu des armes.', 'Say whether you saw any weapons.'],
            'options' => ['no' => ['Aucune arme vue', 'No weapons seen'], 'yes' => ['Arme vue', 'Weapons seen'], 'unknown' => ['Je ne sais pas', 'I do not know']]],
        'distance' => ['type' => 'choice', 'label' => ['Distance d’observation', 'Observation distance'], 'missing' => ['Indiquez à quelle distance vous étiez.', 'Say how far away you were.'],
            'options' => ['far' => ['Plus de 200 m', 'Over 200 m'], 'mid' => ['50 à 200 m', '50 to 200 m'], 'near' => ['Moins de 50 m', 'Under 50 m']]],
        'duration' => ['type' => 'text', 'max' => 60, 'label' => ['Durée de l’observation', 'How long you watched'], 'hint' => ['Ex. 10 minutes', 'E.g. 10 minutes'], 'missing' => ['Indiquez la durée de l’observation.', 'Say how long you watched.']],
        'behaviour' => ['type' => 'text', 'max' => 160, 'label' => ['Comportement', 'Behaviour'], 'hint' => ['Calme, pressé, regarde autour de lui…', 'Calm, hurried, looking around…'], 'missing' => ['Décrivez le comportement.', 'Describe the behaviour.']],
        'photo' => ['type' => 'photo', 'label' => ['Photo', 'Photo']],
    ],

    'measures' => [
        'A-NOI' => ['drop' => ['photo'], 'add' => ['distance', 'people', 'armed'], 'note' => ['Ne vous approchez pas et n’intervenez pas seul : restez à distance et décrivez ce que vous voyez.', 'Do not approach or act alone: stay back and describe what you see.']],
        'A-DNG' => ['add' => ['people', 'armed'], 'note' => ['Considérez la personne comme armée et dangereuse : pas de photo, pas d’approche.', 'Treat the subject as armed and dangerous: no photo, no approach.'], 'drop' => ['photo']],
        'A-IED' => ['drop' => ['photo', 'vehicle'], 'add' => ['distance'], 'note' => ['Risque d’EEI : ne touchez à rien, restez loin et signalez le lieu exact.', 'IED threat: touch nothing, stay clear and give the exact location.']],
        'A-QRF' => ['urgent' => true, 'add' => ['people', 'armed'], 'require' => ['place'], 'note' => ['La force de réaction rapide est alertée : donnez le lieu précis, le nombre de personnes et l’armement.', 'The quick reaction force is alerted: give the exact place, the number of people and any weapons.']],
        'A-REP' => ['urgent' => true, 'note' => ['À rendre compte sans attendre : envoyez ce formulaire puis appelez le service si l’urgence l’exige.', 'Report without delay: send this form, then call the service if it is urgent.']],
        'A-LIV' => ['add' => ['people', 'armed', 'dir'], 'require' => ['dir'], 'drop' => ['photo'], 'note' => ['Capture vivante visée : ne tentez rien vous-même, donnez la position, la direction, le nombre et l’armement.', 'The aim is a live capture: do not try anything yourself, give position, heading, number and weapons.']],
        'A-ARR' => ['add' => ['people', 'armed', 'dir'], 'require' => ['dir'], 'note' => ['Appréhension sur ordre : seule l’équipe désignée intervient. Donnez position, direction et nombre.', 'Apprehension by order only: the designated team acts. Give position, heading and number.']],
        'A-DET' => ['add' => ['people', 'armed', 'dir'], 'note' => ['Ne retenez personne vous-même : donnez position, direction et nombre.', 'Do not hold anyone yourself: give position, heading and number.']],
        'A-NEU' => ['add' => ['people', 'armed', 'dir', 'distance'], 'drop' => ['photo'], 'require' => ['dir'], 'urgent' => true, 'note' => ['Ne tentez rien : donnez position, direction, nombre et armement, à distance.', 'Attempt nothing: give position, heading, number and weapons, from a distance.']],
        'A-CRD' => ['add' => ['dir', 'people'], 'note' => ['Zone à isoler : indiquez par où le sujet peut sortir.', 'Area to be cordoned: say where the subject could leave.']],
        'A-OBS' => ['add' => ['duration', 'behaviour', 'vehicle', 'dir'], 'note' => ['Observez et rendez compte : durée, comportement, véhicule, direction.', 'Observe and report: duration, behaviour, vehicle, heading.']],
        'A-PAT' => ['add' => ['duration', 'behaviour', 'dir'], 'note' => ['Schéma de vie : notez les heures, les habitudes et les lieux fréquentés.', 'Pattern of life: note times, habits and places visited.']],
        'A-TRK' => ['add' => ['duration', 'behaviour', 'dir', 'vehicle'], 'note' => ['Suivi discret : ne vous faites pas remarquer, notez la direction et le comportement.', 'Discreet tracking: stay unnoticed, note heading and behaviour.']],
        'A-PHO' => ['add' => ['photo', 'vehicle'], 'note' => ['Une photo de la personne et du véhicule est utile, si vous pouvez la prendre sans risque.', 'A photo of the subject and vehicle helps, if you can take it safely.']],
        'A-CHK' => ['add' => ['vehicle', 'dir']],
        'A-EXF' => ['add' => ['dir', 'vehicle']],
    ],

    // Selon le rang de l'alerte (1 à 4), s'ajoute aux règles des mesures.
    'levels' => [
        1 => [],
        2 => ['add' => ['dir']],
        3 => ['add' => ['people', 'dir']],
        4 => ['add' => ['people', 'armed', 'distance', 'dir'], 'urgent' => true, 'drop' => ['photo']],
    ],

    // Fiche déclarée « armé » : le formulaire demande toujours l'armement.
    'armed' => ['add' => ['armed']],
];
