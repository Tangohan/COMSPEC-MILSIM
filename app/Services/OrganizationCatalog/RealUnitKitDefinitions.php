<?php

declare(strict_types=1);

namespace App\Services\OrganizationCatalog;

use App\Support\JobCatalog\JobCatalog;

/**
 * Modèles d’organisation inspirés d’unités réelles (données publiques uniquement), regroupés par pays → armée →
 * commandement. Un seul arbre d’unités : chaque modèle en reprend une branche, avec la même adresse courte et le même
 * nom pour une unité présente dans plusieurs modèles (appliquer USSOCOM puis USASOC complète la même arborescence).
 *
 * Nom de l’unité = appellation officielle dans la langue du pays (libellé français par défaut, lang/fr/unit_labels.php) ;
 * traduction anglaise dans lang/en/unit_labels.php ; traduction française d’un nom étranger affichée en complément.
 * Chaque unité cite au moins une source publique (SOURCES) ; une unité sans source n’est pas ajoutée. Les unités dont
 * l’organisation détaillée n’est pas publiée (unités de mission du JSOC, forces internes du GIGN…) ne figurent pas.
 */
final class RealUnitKitDefinitions
{
    public const PREFIX = 'official.real.';

    /** Sources publiques : clé => [titre, adresse]. */
    private const SOURCES = [
        'socom-factbook' => ['USSOCOM Fact Book 2026 — socom.mil', 'https://www.socom.mil/FactBook/2026%20Fact%20Book.pdf'],
        'socom-socpac' => ['Special Operations Command Pacific — socom.mil', 'https://www.socom.mil/socpac'],
        'army-usasoc' => ['U.S. Army Special Operations Command — army.mil', 'https://www.army.mil/usasoc'],
        'army-ranger' => ['U.S. Army Rangers — army.mil', 'https://www.army.mil/ranger/'],
        'goarmy-sf' => ['Special Forces — goarmy.com', 'https://www.goarmy.com/careers-and-jobs/specialty-careers/special-ops/special-forces'],
        'goarmy-po' => ['Psychological Operations — goarmy.com', 'https://www.goarmy.com/careers-and-jobs/specialty-careers/special-ops/psychological-operations'],
        'goarmy-ca' => ['Civil Affairs — goarmy.com', 'https://www.goarmy.com/careers-and-jobs/specialty-careers/special-ops/civil-affairs'],
        'army-528' => ['528th Sustainment Brigade — army.mil', 'https://www.army.mil/article/29326/528th_sustainment_brigade_providing_for_the_force'],
        'arsof-112' => ['The 112th Signal Battalion — ARSOF History (USASOC)', 'https://arsof-history.org/articles/19dec_no_ordinary_signal_unit_page_1.html'],
        'arsof-soatb' => ['A History of Army Special Operations Aviation Training — ARSOF History (USASOC)', 'https://arsof-history.org/articles/19sept_history_soa_training_page_1.html'],
        'go160' => ['160th SOAR (Night Stalkers) — go160thsoar.com', 'https://go160thsoar.com/160th-soar-night-stalkers-service/'],
        'nsw' => ['Naval Special Warfare Command — nsw.navy.mil', 'https://www.nsw.navy.mil/'],
        'dvids-nswg8' => ['NSWC establishes Group Eight — DVIDS', 'https://www.dvidshub.net/news/403911/us-naval-special-warfare-command-establishes-group-eight-disestablishes-groups-three-and-ten'],
        'dvids-sbt12' => ['Special Boat Team 12 change of command — DVIDS', 'https://www.dvidshub.net/news/371676/special-boat-team-12-hosts-change-command-ceremony-sbt-12-ushers-new-era-with-virtual-ceremony'],
        'dvids-st1' => ['SEAL Team 1 — DVIDS', 'https://www.dvidshub.net/news/406101/seal-team-1-pays-tribute-its-first-commanding-officer'],
        'afsoc' => ['Air Force Special Operations Command — afsoc.af.mil', 'https://www.afsoc.af.mil/'],
        'dvids-4sos' => ['4th SOS AC-130J — DVIDS', 'https://www.dvidshub.net/image/7170182/4-sos-aircrew-performs-training-sortie-ac-130j'],
        'dvids-8sos' => ['8th SOS CV-22 — DVIDS', 'https://www.dvidshub.net/image/7736970/8th-sos-conducts-training-flight-cv-22-osprey'],
        'marsoc' => ['Marine Forces Special Operations Command — marsoc.marines.mil', 'https://www.marsoc.marines.mil/'],
        'legifrance-cos' => ['Arrêté du 5 janvier 2017 relatif au commandement des opérations spéciales — Légifrance', 'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000033843040/2024-11-15'],
        'def-cast' => ['Commandement des actions spéciales Terre — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre'],
        'def-cfst' => ['Commandement des forces spéciales Terre — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/notre-organisation/commandement-forces-speciales-terre/commandement-forces-speciales-terre'],
        'def-1rpima' => ['1er régiment de parachutistes d’infanterie de marine — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre/1er-regiment-parachutistes-dinfanterie'],
        'def-13rdp' => ['13e régiment de dragons parachutistes — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre/13e-regiment-dragons-parachutistes'],
        'def-4rhfs' => ['4e régiment d’hélicoptères des forces spéciales — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/armes/laviation-legere-larmee-terre/4e-regiment-dhelicopteres-forces-speciales'],
        'def-ciae' => ['Centre interarmées des actions sur l’environnement — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre/centre-interarmees-actions'],
        'mn-organisation' => ['Notre organisation — Marine nationale (defense.gouv.fr)', 'https://www.defense.gouv.fr/marine/mieux-nous-connaitre/notre-organisation'],
        'cesm-commandos' => ['Les commandos Marine : atout des opérations spéciales et spécialisées — CESM (defense.gouv.fr)', 'https://www.defense.gouv.fr/sites/default/files/cesm/bm-185-les-commandos-marine-atout-des-operations-speciales-et-specialisees.pdf'],
        'def-fsa' => ['Forces spéciales Air, défilé du 14 juillet 2026 — defense.gouv.fr', 'https://www.defense.gouv.fr/ministere/dossiers-evenementiels-thematiques/editions-du-14-juillet/defile-du-14-juillet-2026/unites-du-defile-117'],
        'def-cpa10' => ['Une nouvelle formation pour les commandos parachutistes des forces spéciales — defense.gouv.fr', 'https://www.defense.gouv.fr/air/actualites/nouvelle-formation-specifique-commandos-parachutistes-forces-speciales'],
        'def-cpa20' => ['Spectral Thunder : le CPA 20 s’entraîne au contrôle aérien tactique — defense.gouv.fr', 'https://www.defense.gouv.fr/air/actualites/spectral-thunder-cpa-20-sentraine-au-controle-aerien-tactique'],
        'def-cpa30' => ['BA 123 : entraînement conjoint entre le CPA 30 et l’EH 1/67 « Pyrénées » — defense.gouv.fr', 'https://www.defense.gouv.fr/air/actualites/ba-123-entrainement-conjoint-entre-cpa-30-leh-167-pyrenees'],
        'def-et361' => ['80 ans d’excellence opérationnelle pour le 3/61 « Poitou » — defense.gouv.fr', 'https://www.defense.gouv.fr/air/actualites/80-ans-dexcellence-operationnelle-361-poitou'],
        'senat-gign' => ['Rapport d’information n° 813 (2024-2025), unités d’élite de la gendarmerie et de la police — Sénat', 'https://www.senat.fr/rap/r24-813/r24-813_mono.html'],
        'def-11bp' => ['11e brigade parachutiste — defense.gouv.fr', 'https://www.defense.gouv.fr/en/node/1141'],
        'def-2rep' => ['2e régiment étranger de parachutistes — defense.gouv.fr', 'https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/11e-brigade-parachutiste-11e-bp/2e-regiment-etranger-parachutistes'],
        'def-27bim' => ['27e brigade d’infanterie de montagne — defense.gouv.fr', 'https://www.defense.gouv.fr/en/node/12301'],
        'terre-emhm' => ['Les missions de l’EMHM — armée de Terre', 'https://www.terre.defense.gouv.fr/emhm/nos-missions'],
        'bw-dsk' => ['Division Schnelle Kräfte — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte'],
        'bw-ksk' => ['Kommando Spezialkräfte — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-spezialkraefte'],
        'bw-ksk-ausb' => ['Ausbildungsstützpunkt Spezialkräfte Heer — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-spezialkraefte/ausbildungsstuetzpunkt-spezialkraefte-heer'],
        'bw-llbrig1' => ['Luftlandebrigade 1 — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/luftlandebrigade-1'],
        'bw-egb' => ['Erweiterte Grundbefähigung: die Spezialisierten Kräfte des Heeres — bundeswehr.de', 'https://www.bundeswehr.de/de/meldungen/spezialkraefte-bundeswehr/erweiterte-grundbefaehigung-spezialisierte-kraefte-heer'],
        'bw-fschspzzug' => ['Fallschirmspezialzug als Vorauskräfte einer Luftlandeoperation — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/aktuelles/fallschirmspezialzug-als-vorauskraefte-luftlandeoperation-5681694'],
        'bw-llpi' => ['Luftlandepioniere im Gefecht — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/aktuelles/luftlandepioniere-im-gefecht-5500596'],
        'bw-fspkp1' => ['Fernspähkompanie 1 aufgestellt — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/aktuelles/fernspaehkompanie-1-aufgestellt-5637528'],
        'bw-gebjgbrig23' => ['Gebirgsjägerbrigade 23 — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/gebirgsjaegerbrigade-23'],
        'bw-gebjgbtl231' => ['Gebirgsjägerbataillon 231 — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/gebirgsjaegerbrigade-23/gebirgsjaegerbataillon-231'],
        'bw-kdohubschr' => ['Kommando Hubschrauber — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-hubschrauber'],
        'bw-thr10' => ['Transporthubschrauberregiment 10 — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-hubschrauber/transporthubschrauberregiment-10'],
        'bw-khr36' => ['Kampfhubschrauberregiment 36 — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-hubschrauber/kampfhubschrauberregiment-36'],
        'bw-ihaz' => ['Internationales Hubschrauberausbildungszentrum — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-hubschrauber/internationales-hubschrauberausbildungszentrum'],
        'bw-ksm' => ['Kommando Spezialkräfte der Marine — bundeswehr.de', 'https://www.bundeswehr.de/de/organisation/marine/struktur/einsatzflottille-1/kommando-spezialkraefte-marine'],
        'bw-ksm-kampfschwimmer' => ['Kommando Spezialkräfte der Marine (Kampfschwimmer) — bundeswehr.de', 'https://www.bundeswehr.de/de/meldungen/spezialkraefte-bundeswehr/kommando-spezialkraefte-marine'],
        'bw-seebtl' => ['Jobporträt Marineinfanterist (Seebataillon) — bundeswehr.de', 'https://www.bundeswehr.de/de/menschen-karrieren/jobportraets-menschen-bundeswehr-berufe/marineinfanterist-6093398'],
        'bw-minentaucher' => ['Gefährlicher Auftrag: Minentauchereinsatzboote — bundeswehr.de', 'https://www.bundeswehr.de/de/meldungen/gefaehrlicher-auftrag-minentauchereinsatzboote-5409518'],
        'bw-hsg64' => ['4. Staffel des Hubschraubergeschwaders 64 — bundeswehr.de', 'https://www.bundeswehr.de/de/meldungen/spezialkraefte-bundeswehr/4-staffel-hubschraubergeschwader-64'],
        'bw-sofair' => ['Fliegende Rückendeckung: die Spezialkräfte der Luftwaffe — bundeswehr.de', 'https://www.bundeswehr.de/de/meldungen/spezialkraefte-bundeswehr/fliegende-rueckendeckung-spezialkraefte-luftwaffe-5222018'],
        'uk-mod' => ['Ministry of Defence — GOV.UK', 'https://www.gov.uk/government/organisations/ministry-of-defence'],
        'wiki-uksf' => ['United Kingdom Special Forces — Wikipedia (complément, aucune page officielle détaillée)', 'https://en.wikipedia.org/wiki/United_Kingdom_Special_Forces'],
        'army-16aa' => ['16 Air Assault Brigade Combat Team — army.mod.uk', 'https://www.army.mod.uk/learn-and-explore/about-the-army/formations-divisions-and-brigades/1st-united-kingdom-division/16-air-assault-brigade-combat-team'],
        'army-23pera' => ['23 Parachute Engineer Regiment — army.mod.uk', 'https://www.army.mod.uk/learn-and-explore/about-the-army/corps-regiments-and-units/engineering/corps-of-royal-engineers/23-parachute-engineer-regiment'],
        'army-16med' => ['16 Medical Regiment — army.mod.uk', 'https://www.army.mod.uk/learn-and-explore/about-the-army/corps-regiments-and-units/army-medical-services/16-medical-regiment'],
        'rn-ukcf' => ['3 Commando Brigade to be known officially as the UK Commando Force — royalnavy.mod.uk (juin 2026)', 'https://www.royalnavy.mod.uk/news/2026/june/09/20260609-commando-name-change'],
        'gov-jhc' => ['Joint Helicopter Command (devenu Joint Aviation Command en 2024) — GOV.UK', 'https://www.gov.uk/joint-helicopter-command'],
        'army-aac' => ['Army Air Corps — army.mod.uk', 'https://www.army.mod.uk/who-we-are/corps-regiments-and-units/army-air-corps'],
        'wiki-odiham' => ['RAF Odiham — Wikipedia (complément)', 'https://en.wikipedia.org/wiki/RAF_Odiham'],
        // Malaisie : sites de l’armée (army.mil.my) inaccessibles à la compilation, articles publics en anglais.
        'wiki-my-ggk' => ['Grup Gerak Khas — Wikipedia (en)', 'https://en.wikipedia.org/wiki/Grup_Gerak_Khas'],
        'wiki-my-10bpara' => ['10th Parachute Brigade (Malaysia) — Wikipedia (en)', 'https://en.wikipedia.org/wiki/10th_Parachute_Brigade_(Malaysia)'],
        'wiki-my-inf' => ['Infantry Corps (Malaysia) — Wikipedia (en)', 'https://en.wikipedia.org/wiki/Infantry_Corps_(Malaysia)'],
        'wiki-my-9ramd' => ['9th Battalion (Parachute), Royal Malay Regiment — Wikipedia (en)', 'https://en.wikipedia.org/wiki/9th_Battalion_(Parachute),_Royal_Malay_Regiment'],
        'wiki-section' => ['Section (military unit), section d’infanterie de modèle britannique — Wikipedia (en)', 'https://en.wikipedia.org/wiki/Section_(military_unit)'],
        'wiki-my-paskal' => ['PASKAL — Wikipedia (en)', 'https://en.wikipedia.org/wiki/PASKAL'],
        'wiki-my-paskau' => ['PASKAU — Wikipedia (en)', 'https://en.wikipedia.org/wiki/PASKAU'],
    ];

    /**
     * Arbre des unités : clé (= adresse courte) => [parent, nom officiel, code court, échelon, traduction française,
     * traduction anglaise, sources]. Traduction nulle : identique au nom officiel. Ordre = ordre d’affichage.
     */
    private const NODES = [
        // --- États-Unis : USSOCOM ---
        'us-ussocom' => [null, 'United States Special Operations Command', 'USSOCOM', 'command', 'Commandement des opérations spéciales des États-Unis', null, ['socom-factbook']],
        'us-jsoc' => ['us-ussocom', 'Joint Special Operations Command', 'JSOC', 'command', 'Commandement interarmées des opérations spéciales', null, ['socom-factbook']],
        'us-tsoc' => ['us-ussocom', 'Theater Special Operations Commands', 'TSOC', 'grouping', 'Commandements d’opérations spéciales de théâtre', null, ['socom-factbook']],
        'us-socafrica' => ['us-tsoc', 'Special Operations Command Africa', 'SOCAFRICA', 'command', 'Commandement des opérations spéciales pour l’Afrique', null, ['socom-factbook']],
        'us-soccent' => ['us-tsoc', 'Special Operations Command Central', 'SOCCENT', 'command', 'Commandement des opérations spéciales Centre (Moyen-Orient et Asie centrale)', null, ['socom-factbook']],
        'us-soceur' => ['us-tsoc', 'Special Operations Command Europe', 'SOCEUR', 'command', 'Commandement des opérations spéciales pour l’Europe', null, ['socom-factbook']],
        'us-sockor' => ['us-tsoc', 'Special Operations Command Korea', 'SOCKOR', 'command', 'Commandement des opérations spéciales en Corée', null, ['socom-factbook']],
        'us-socnorth' => ['us-tsoc', 'Special Operations Command North', 'SOCNORTH', 'command', 'Commandement des opérations spéciales Nord (Amérique du Nord)', null, ['socom-factbook']],
        'us-socpac' => ['us-tsoc', 'Special Operations Command Pacific', 'SOCPAC', 'command', 'Commandement des opérations spéciales pour le Pacifique', null, ['socom-factbook', 'socom-socpac']],
        'us-socsouth' => ['us-tsoc', 'Special Operations Command South', 'SOCSOUTH', 'command', 'Commandement des opérations spéciales Sud (Amérique centrale et du Sud)', null, ['socom-factbook']],

        // --- États-Unis : USASOC ---
        'us-usasoc' => ['us-ussocom', 'United States Army Special Operations Command', 'USASOC', 'command', 'Commandement des opérations spéciales de l’US Army', null, ['socom-factbook', 'army-usasoc']],
        'us-1sfc' => ['us-usasoc', '1st Special Forces Command (Airborne)', '1st SFC(A)', 'command', '1er commandement des forces spéciales (aéroporté)', null, ['army-usasoc']],
        'us-1sfg' => ['us-1sfc', '1st Special Forces Group (Airborne)', '1st SFG(A)', 'group', '1er groupe de forces spéciales (aéroporté)', null, ['army-usasoc', 'goarmy-sf']],
        'us-3sfg' => ['us-1sfc', '3rd Special Forces Group (Airborne)', '3rd SFG(A)', 'group', '3e groupe de forces spéciales (aéroporté)', null, ['army-usasoc', 'goarmy-sf']],
        'us-5sfg' => ['us-1sfc', '5th Special Forces Group (Airborne)', '5th SFG(A)', 'group', '5e groupe de forces spéciales (aéroporté)', null, ['army-usasoc', 'goarmy-sf']],
        'us-7sfg' => ['us-1sfc', '7th Special Forces Group (Airborne)', '7th SFG(A)', 'group', '7e groupe de forces spéciales (aéroporté)', null, ['army-usasoc', 'goarmy-sf']],
        'us-10sfg' => ['us-1sfc', '10th Special Forces Group (Airborne)', '10th SFG(A)', 'group', '10e groupe de forces spéciales (aéroporté)', null, ['army-usasoc', 'goarmy-sf']],
        'us-19sfg' => ['us-1sfc', '19th Special Forces Group (Airborne)', '19th SFG(A)', 'group', '19e groupe de forces spéciales (aéroporté), Garde nationale', null, ['army-usasoc', 'goarmy-sf']],
        'us-20sfg' => ['us-1sfc', '20th Special Forces Group (Airborne)', '20th SFG(A)', 'group', '20e groupe de forces spéciales (aéroporté), Garde nationale', null, ['army-usasoc', 'goarmy-sf']],
        'us-4pog' => ['us-1sfc', '4th Psychological Operations Group (Airborne)', '4th POG(A)', 'group', '4e groupe d’opérations psychologiques (aéroporté)', null, ['army-usasoc', 'goarmy-po']],
        'us-8pog' => ['us-1sfc', '8th Psychological Operations Group (Airborne)', '8th POG(A)', 'group', '8e groupe d’opérations psychologiques (aéroporté)', null, ['army-usasoc', 'goarmy-po']],
        'us-95cab' => ['us-1sfc', '95th Civil Affairs Brigade (Special Operations) (Airborne)', '95th CA BDE', 'brigade', '95e brigade d’affaires civiles (opérations spéciales) (aéroportée)', null, ['army-usasoc', 'goarmy-ca']],
        'us-528sb' => ['us-1sfc', '528th Sustainment Brigade (Special Operations) (Airborne)', '528th SB(SO)(A)', 'brigade', '528e brigade de soutien (opérations spéciales) (aéroportée)', null, ['army-usasoc', 'army-528']],
        'us-112sosb' => ['us-528sb', '112th Special Operations Signal Battalion (Airborne)', '112th SOSB(A)', 'battalion', '112e bataillon de transmissions des opérations spéciales (aéroporté)', null, ['arsof-112']],
        'us-389mib' => ['us-1sfc', '389th Military Intelligence Battalion (Airborne)', '389th MIB(A)', 'battalion', '389e bataillon de renseignement militaire (aéroporté)', null, ['army-usasoc']],
        'us-75rr' => ['us-usasoc', '75th Ranger Regiment', '75th RGR', 'regiment', '75e régiment de Rangers', null, ['army-ranger', 'army-usasoc']],
        'us-75rr-1' => ['us-75rr', '1st Battalion, 75th Ranger Regiment', '1/75', 'battalion', '1er bataillon du 75e régiment de Rangers', null, ['army-ranger']],
        'us-75rr-2' => ['us-75rr', '2nd Battalion, 75th Ranger Regiment', '2/75', 'battalion', '2e bataillon du 75e régiment de Rangers', null, ['army-ranger']],
        'us-75rr-3' => ['us-75rr', '3rd Battalion, 75th Ranger Regiment', '3/75', 'battalion', '3e bataillon du 75e régiment de Rangers', null, ['army-ranger']],
        'us-75rr-rstb' => ['us-75rr', 'Regimental Special Troops Battalion, 75th Ranger Regiment', 'RSTB', 'battalion', 'Bataillon des troupes spéciales du 75e régiment de Rangers', null, ['army-ranger']],
        'us-75rr-rrc' => ['us-75rr-rstb', 'Ranger Reconnaissance Company', 'RRC', 'company', 'Compagnie de reconnaissance Ranger', null, ['army-ranger']],
        'us-75rr-rmib' => ['us-75rr', 'Regimental Military Intelligence Battalion, 75th Ranger Regiment', 'RMIB', 'battalion', 'Bataillon de renseignement militaire du 75e régiment de Rangers', null, ['army-ranger']],
        'us-usasoac' => ['us-usasoc', 'United States Army Special Operations Aviation Command (Airborne)', 'USASOAC', 'command', 'Commandement de l’aviation des opérations spéciales de l’US Army (aéroporté)', null, ['army-usasoc', 'go160']],
        'us-160soar' => ['us-usasoac', '160th Special Operations Aviation Regiment (Airborne)', '160th SOAR(A)', 'regiment', '160e régiment d’aviation des opérations spéciales (aéroporté), « Night Stalkers »', null, ['go160']],
        'us-160soar-1' => ['us-160soar', '1st Battalion, 160th SOAR(A)', '1/160', 'battalion', '1er bataillon du 160th SOAR(A)', null, ['go160']],
        'us-160soar-2' => ['us-160soar', '2nd Battalion, 160th SOAR(A)', '2/160', 'battalion', '2e bataillon du 160th SOAR(A)', null, ['go160']],
        'us-160soar-3' => ['us-160soar', '3rd Battalion, 160th SOAR(A)', '3/160', 'battalion', '3e bataillon du 160th SOAR(A)', null, ['go160']],
        'us-160soar-4' => ['us-160soar', '4th Battalion, 160th SOAR(A)', '4/160', 'battalion', '4e bataillon du 160th SOAR(A)', null, ['go160']],
        'us-soatb' => ['us-usasoac', 'Special Operations Aviation Training Battalion', 'SOATB', 'school', 'Bataillon d’instruction de l’aviation des opérations spéciales', null, ['arsof-soatb']],
        'us-swcs' => ['us-usasoc', 'U.S. Army John F. Kennedy Special Warfare Center and School', 'USAJFKSWCS', 'school', 'Centre et école de guerre spéciale John F. Kennedy de l’US Army', null, ['army-usasoc']],

        // --- États-Unis : NSWC ---
        'us-nswc' => ['us-ussocom', 'Naval Special Warfare Command', 'NSWC', 'command', 'Commandement de la guerre spéciale navale', null, ['socom-factbook', 'nsw']],
        'us-nswg1' => ['us-nswc', 'Naval Special Warfare Group 1', 'NSWG-1', 'group', 'Groupe de guerre spéciale navale 1', null, ['nsw']],
        'us-seal-1' => ['us-nswg1', 'SEAL Team 1', 'ST-1', 'unit', 'Équipe SEAL 1', null, ['nsw', 'dvids-st1']],
        'us-seal-3' => ['us-nswg1', 'SEAL Team 3', 'ST-3', 'unit', 'Équipe SEAL 3', null, ['nsw']],
        'us-seal-5' => ['us-nswg1', 'SEAL Team 5', 'ST-5', 'unit', 'Équipe SEAL 5', null, ['nsw']],
        'us-seal-7' => ['us-nswg1', 'SEAL Team 7', 'ST-7', 'unit', 'Équipe SEAL 7', null, ['nsw']],
        'us-nswg2' => ['us-nswc', 'Naval Special Warfare Group 2', 'NSWG-2', 'group', 'Groupe de guerre spéciale navale 2', null, ['nsw']],
        'us-seal-2' => ['us-nswg2', 'SEAL Team 2', 'ST-2', 'unit', 'Équipe SEAL 2', null, ['nsw']],
        'us-seal-4' => ['us-nswg2', 'SEAL Team 4', 'ST-4', 'unit', 'Équipe SEAL 4', null, ['nsw']],
        'us-seal-8' => ['us-nswg2', 'SEAL Team 8', 'ST-8', 'unit', 'Équipe SEAL 8', null, ['nsw']],
        'us-seal-10' => ['us-nswg2', 'SEAL Team 10', 'ST-10', 'unit', 'Équipe SEAL 10', null, ['nsw']],
        'us-nswg4' => ['us-nswc', 'Naval Special Warfare Group 4', 'NSWG-4', 'group', 'Groupe de guerre spéciale navale 4 (embarcations spéciales)', null, ['nsw', 'dvids-sbt12']],
        'us-sbt-12' => ['us-nswg4', 'Special Boat Team 12', 'SBT-12', 'unit', 'Équipe d’embarcations spéciales 12', null, ['nsw', 'dvids-sbt12']],
        'us-sbt-20' => ['us-nswg4', 'Special Boat Team 20', 'SBT-20', 'unit', 'Équipe d’embarcations spéciales 20', null, ['nsw']],
        'us-sbt-22' => ['us-nswg4', 'Special Boat Team 22', 'SBT-22', 'unit', 'Équipe d’embarcations spéciales 22', null, ['nsw']],
        'us-nswg8' => ['us-nswc', 'Naval Special Warfare Group 8', 'NSWG-8', 'group', 'Groupe de guerre spéciale navale 8 (propulseurs sous-marins et reconnaissance)', null, ['nsw', 'dvids-nswg8']],
        'us-sdvt-1' => ['us-nswg8', 'SEAL Delivery Vehicle Team 1', 'SDVT-1', 'unit', 'Équipe de propulseurs sous-marins SEAL 1', null, ['dvids-nswg8']],
        'us-sdvt-2' => ['us-nswg8', 'SEAL Delivery Vehicle Team 2', 'SDVT-2', 'unit', 'Équipe de propulseurs sous-marins SEAL 2', null, ['dvids-nswg8']],
        'us-srt-1' => ['us-nswg8', 'Special Reconnaissance Team 1', 'SRT-1', 'unit', 'Équipe de reconnaissance spéciale 1', null, ['dvids-nswg8']],
        'us-srt-2' => ['us-nswg8', 'Special Reconnaissance Team 2', 'SRT-2', 'unit', 'Équipe de reconnaissance spéciale 2', null, ['dvids-nswg8']],
        'us-nswg11' => ['us-nswc', 'Naval Special Warfare Group 11', 'NSWG-11', 'group', 'Groupe de guerre spéciale navale 11 (réserve)', null, ['nsw']],
        'us-seal-17' => ['us-nswg11', 'SEAL Team 17', 'ST-17', 'unit', 'Équipe SEAL 17 (réserve)', null, ['nsw']],
        'us-seal-18' => ['us-nswg11', 'SEAL Team 18', 'ST-18', 'unit', 'Équipe SEAL 18 (réserve)', null, ['nsw']],
        'us-nswcen' => ['us-nswc', 'Naval Special Warfare Center', 'NSWCEN', 'school', 'Centre de formation de la guerre spéciale navale', null, ['nsw']],

        // --- États-Unis : AFSOC ---
        'us-afsoc' => ['us-ussocom', 'Air Force Special Operations Command', 'AFSOC', 'command', 'Commandement des opérations spéciales de l’US Air Force', null, ['socom-factbook', 'afsoc']],
        'us-1sow' => ['us-afsoc', '1st Special Operations Wing', '1st SOW', 'wing', '1re escadre d’opérations spéciales', null, ['afsoc']],
        'us-4sos' => ['us-1sow', '4th Special Operations Squadron', '4th SOS', 'squadron', '4e escadron d’opérations spéciales (AC-130J)', null, ['afsoc', 'dvids-4sos']],
        'us-8sos' => ['us-1sow', '8th Special Operations Squadron', '8th SOS', 'squadron', '8e escadron d’opérations spéciales (CV-22)', null, ['afsoc', 'dvids-8sos']],
        'us-319sos' => ['us-1sow', '319th Special Operations Squadron', '319th SOS', 'squadron', '319e escadron d’opérations spéciales (U-28A)', null, ['afsoc']],
        'us-24sow' => ['us-afsoc', '24th Special Operations Wing', '24th SOW', 'wing', '24e escadre d’opérations spéciales (Special Tactics)', null, ['afsoc']],
        'us-720stg' => ['us-24sow', '720th Special Tactics Group', '720th STG', 'group', '720e groupe de tactiques spéciales', null, ['afsoc']],
        'us-21sts' => ['us-720stg', '21st Special Tactics Squadron', '21st STS', 'squadron', '21e escadron de tactiques spéciales', null, ['afsoc']],
        'us-22sts' => ['us-720stg', '22nd Special Tactics Squadron', '22nd STS', 'squadron', '22e escadron de tactiques spéciales', null, ['afsoc']],
        'us-23sts' => ['us-720stg', '23rd Special Tactics Squadron', '23rd STS', 'squadron', '23e escadron de tactiques spéciales', null, ['afsoc']],
        'us-26sts' => ['us-720stg', '26th Special Tactics Squadron', '26th STS', 'squadron', '26e escadron de tactiques spéciales', null, ['afsoc']],
        'us-724stg' => ['us-24sow', '724th Special Tactics Group', '724th STG', 'group', '724e groupe de tactiques spéciales', null, ['afsoc']],
        'us-24sts' => ['us-724stg', '24th Special Tactics Squadron', '24th STS', 'squadron', '24e escadron de tactiques spéciales', null, ['afsoc']],
        'us-27sow' => ['us-afsoc', '27th Special Operations Wing', '27th SOW', 'wing', '27e escadre d’opérations spéciales', null, ['afsoc']],
        'us-20sos' => ['us-27sow', '20th Special Operations Squadron', '20th SOS', 'squadron', '20e escadron d’opérations spéciales (CV-22)', null, ['afsoc']],
        'us-318sos' => ['us-27sow', '318th Special Operations Squadron', '318th SOS', 'squadron', '318e escadron d’opérations spéciales (U-28A)', null, ['afsoc']],
        'us-352sow' => ['us-afsoc', '352nd Special Operations Wing', '352nd SOW', 'wing', '352e escadre d’opérations spéciales (Europe)', null, ['afsoc']],
        'us-67sos' => ['us-352sow', '67th Special Operations Squadron', '67th SOS', 'squadron', '67e escadron d’opérations spéciales (MC-130J)', null, ['afsoc']],
        'us-321sts' => ['us-352sow', '321st Special Tactics Squadron', '321st STS', 'squadron', '321e escadron de tactiques spéciales', null, ['afsoc']],
        'us-353sow' => ['us-afsoc', '353rd Special Operations Wing', '353rd SOW', 'wing', '353e escadre d’opérations spéciales (Pacifique)', null, ['afsoc']],
        'us-21sos' => ['us-353sow', '21st Special Operations Squadron', '21st SOS', 'squadron', '21e escadron d’opérations spéciales (CV-22)', null, ['afsoc']],
        'us-320sts' => ['us-353sow', '320th Special Tactics Squadron', '320th STS', 'squadron', '320e escadron de tactiques spéciales', null, ['afsoc']],
        'us-492sow' => ['us-afsoc', '492nd Special Operations Wing', '492nd SOW', 'wing', '492e escadre d’opérations spéciales (formation et entraînement)', null, ['afsoc']],
        'us-919sow' => ['us-afsoc', '919th Special Operations Wing', '919th SOW', 'wing', '919e escadre d’opérations spéciales (réserve de l’Air Force)', null, ['afsoc']],
        'us-5sos' => ['us-919sow', '5th Special Operations Squadron', '5th SOS', 'squadron', '5e escadron d’opérations spéciales (instruction)', null, ['afsoc']],
        'us-137sow' => ['us-afsoc', '137th Special Operations Wing', '137th SOW', 'wing', '137e escadre d’opérations spéciales (Garde nationale aérienne, Oklahoma)', null, ['afsoc']],
        'us-193sow' => ['us-afsoc', '193rd Special Operations Wing', '193rd SOW', 'wing', '193e escadre d’opérations spéciales (Garde nationale aérienne, Pennsylvanie)', null, ['afsoc']],

        // --- États-Unis : MARSOC ---
        'us-marsoc' => ['us-ussocom', 'Marine Forces Special Operations Command', 'MARSOC', 'command', 'Commandement des opérations spéciales du Corps des Marines', null, ['socom-factbook', 'marsoc']],
        'us-mrr' => ['us-marsoc', 'Marine Raider Regiment', 'MRR', 'regiment', 'Régiment de Marine Raiders', null, ['marsoc']],
        'us-1mrb' => ['us-mrr', '1st Marine Raider Battalion', '1st MRB', 'battalion', '1er bataillon de Marine Raiders', null, ['marsoc']],
        'us-msoc' => ['us-1mrb', 'Marine Special Operations Company', 'MSOC', 'company', 'Compagnie d’opérations spéciales des Marines (compagnie type)', null, ['marsoc']],
        'us-msot' => ['us-msoc', 'Marine Special Operations Team', 'MSOT', 'team', 'Équipe d’opérations spéciales des Marines (équipe type)', null, ['marsoc']],
        'us-2mrb' => ['us-mrr', '2nd Marine Raider Battalion', '2nd MRB', 'battalion', '2e bataillon de Marine Raiders', null, ['marsoc']],
        'us-3mrb' => ['us-mrr', '3rd Marine Raider Battalion', '3rd MRB', 'battalion', '3e bataillon de Marine Raiders', null, ['marsoc']],
        'us-mrsg' => ['us-marsoc', 'Marine Raider Support Group', 'MRSG', 'group', 'Groupe de soutien des Marine Raiders', null, ['marsoc']],
        'us-1mrsb' => ['us-mrsg', '1st Marine Raider Support Battalion', '1st MRSB', 'battalion', '1er bataillon de soutien des Marine Raiders', null, ['marsoc']],
        'us-2mrsb' => ['us-mrsg', '2nd Marine Raider Support Battalion', '2nd MRSB', 'battalion', '2e bataillon de soutien des Marine Raiders', null, ['marsoc']],
        'us-3mrsb' => ['us-mrsg', '3rd Marine Raider Support Battalion', '3rd MRSB', 'battalion', '3e bataillon de soutien des Marine Raiders', null, ['marsoc']],
        'us-mrtc' => ['us-marsoc', 'Marine Raider Training Center', 'MRTC', 'school', 'Centre de formation des Marine Raiders', null, ['marsoc']],

        // --- France : COS ---
        'fr-cos' => [null, 'Commandement des opérations spéciales', 'COS', 'command', null, 'Special Operations Command (COS)', ['legifrance-cos']],
        'fr-cast' => ['fr-cos', 'Commandement des actions spéciales Terre', 'CAST', 'command', null, 'Army Special Actions Command (CAST)', ['def-cast']],
        'fr-1rpima' => ['fr-cast', '1er régiment de parachutistes d’infanterie de marine', '1er RPIMa', 'regiment', null, '1st Marine Infantry Parachute Regiment (1er RPIMa)', ['def-1rpima']],
        'fr-1rpima-ccl' => ['fr-1rpima', 'Compagnie de commandement et de logistique', 'CCL', 'company', null, 'Command and logistics company', ['def-1rpima']],
        'fr-1rpima-1' => ['fr-1rpima', '1re compagnie SAS', '1re Cie', 'company', null, '1st SAS company', ['def-1rpima']],
        'fr-1rpima-2' => ['fr-1rpima', '2e compagnie SAS', '2e Cie', 'company', null, '2nd SAS company', ['def-1rpima']],
        'fr-1rpima-3' => ['fr-1rpima', '3e compagnie SAS', '3e Cie', 'company', null, '3rd SAS company', ['def-1rpima']],
        'fr-1rpima-4' => ['fr-1rpima', '4e compagnie SAS', '4e Cie', 'company', null, '4th SAS company', ['def-1rpima']],
        'fr-1rpima-ci' => ['fr-1rpima', 'Compagnie d’instruction', 'CI', 'company', null, 'Training company', ['def-1rpima']],
        'fr-13rdp' => ['fr-cast', '13e régiment de dragons parachutistes', '13e RDP', 'regiment', null, '13th Parachute Dragoon Regiment (13e RDP)', ['def-13rdp']],
        'fr-4rhfs' => ['fr-cast', '4e régiment d’hélicoptères des forces spéciales', '4e RHFS', 'regiment', null, '4th Special Forces Helicopter Regiment (4e RHFS)', ['def-4rhfs']],
        'fr-cctfs' => ['fr-cast', 'Compagnie de commandement et de transmissions des forces spéciales', 'CCT-FS', 'company', null, 'Special forces command and signal company (CCT-FS)', ['def-cfst']],
        'fr-ciae' => ['fr-cast', 'Centre interarmées des actions sur l’environnement', 'CIAE', 'center', null, 'Joint Centre for Environment Actions (CIAE)', ['def-ciae']],
        'fr-ciae-gon' => ['fr-ciae', 'Groupement des opérations numériques', 'GON', 'group', null, 'Digital operations group', ['def-ciae']],
        'fr-ciae-gaao' => ['fr-ciae', 'Groupement d’analyse et d’appui aux opérations', 'GAAO', 'group', null, 'Analysis and operations support group', ['def-ciae']],
        'fr-cpmo' => ['fr-cast', 'Centre terre pour le partenariat militaire opérationnel', 'CPMO', 'center', null, 'Army Centre for Operational Military Partnership (CPMO)', ['def-cast']],
        'fr-forfusco' => ['fr-cos', 'Force maritime des fusiliers marins et commandos', 'FORFUSCO', 'command', null, 'Marine Fusiliers and Commandos Force (FORFUSCO)', ['mn-organisation', 'cesm-commandos']],
        'fr-cdo-hubert' => ['fr-forfusco', 'Commando Hubert', 'Cdo Hubert', 'commando', null, 'Commando Hubert (combat divers)', ['cesm-commandos']],
        'fr-cdo-jaubert' => ['fr-forfusco', 'Commando Jaubert', 'Cdo Jaubert', 'commando', null, 'Commando Jaubert (assault)', ['cesm-commandos']],
        'fr-cdo-trepel' => ['fr-forfusco', 'Commando Trépel', 'Cdo Trépel', 'commando', null, 'Commando Trépel (assault)', ['cesm-commandos']],
        'fr-cdo-penfentenyo' => ['fr-forfusco', 'Commando de Penfentenyo', 'Cdo Penfentenyo', 'commando', null, 'Commando de Penfentenyo (reconnaissance)', ['cesm-commandos']],
        'fr-cdo-montfort' => ['fr-forfusco', 'Commando de Montfort', 'Cdo Montfort', 'commando', null, 'Commando de Montfort (fire support)', ['cesm-commandos']],
        'fr-cdo-kieffer' => ['fr-forfusco', 'Commando Kieffer', 'Cdo Kieffer', 'commando', null, 'Commando Kieffer (support)', ['cesm-commandos']],
        'fr-cdo-ponchardier' => ['fr-forfusco', 'Commando Ponchardier', 'Cdo Ponchardier', 'commando', null, 'Commando Ponchardier (special operations support)', ['cesm-commandos']],
        'fr-bfsa' => ['fr-cos', 'Brigade des forces spéciales Air', 'BFSA', 'brigade', null, 'Air Special Forces Brigade (BFSA)', ['def-fsa']],
        'fr-cpa10' => ['fr-bfsa', 'Commando parachutiste de l’air n° 10', 'CPA 10', 'commando', null, 'Air Parachute Commando No. 10 (CPA 10)', ['def-fsa', 'def-cpa10']],
        'fr-cpa20' => ['fr-bfsa', 'Commando parachutiste de l’air n° 20', 'CPA 20', 'commando', null, 'Air Parachute Commando No. 20 (CPA 20)', ['def-fsa', 'def-cpa20']],
        'fr-cpa30' => ['fr-bfsa', 'Commando parachutiste de l’air n° 30', 'CPA 30', 'commando', null, 'Air Parachute Commando No. 30 (CPA 30)', ['def-fsa', 'def-cpa30']],
        'fr-eh167' => ['fr-bfsa', 'Escadron d’hélicoptères 1/67 « Pyrénées »', 'EH 1/67', 'squadron', null, 'Helicopter Squadron 1/67 “Pyrénées”', ['def-fsa', 'def-cpa30']],
        'fr-et361' => ['fr-bfsa', 'Escadron de transport 3/61 « Poitou »', 'ET 3/61', 'squadron', null, 'Transport Squadron 3/61 “Poitou”', ['def-fsa', 'def-et361']],

        // --- France : GIGN ---
        'fr-gign' => [null, 'Groupe d’intervention de la Gendarmerie nationale', 'GIGN', 'group', null, 'National Gendarmerie Intervention Group (GIGN)', ['senat-gign']],
        'fr-gign-cnn' => ['fr-gign', 'Cellule nationale de négociation', 'CNN', 'team', null, 'National negotiation cell', ['senat-gign']],
        'fr-gign-dt' => ['fr-gign', 'Division technique', 'DT', 'unit', null, 'Technical division', ['senat-gign']],
        'fr-gign-antennes' => ['fr-gign', 'Antennes du GIGN', 'AGIGN', 'grouping', null, 'GIGN regional branches', ['senat-gign']],

        // --- France : 11e BP ---
        'fr-11bp' => [null, '11e brigade parachutiste', '11e BP', 'brigade', null, '11th Parachute Brigade (11e BP)', ['def-11bp']],
        'fr-11bp-gcp' => ['fr-11bp', 'Groupement des commandos parachutistes', 'GCP', 'grouping', null, 'Parachute commando group (GCP)', ['def-11bp']],
        'fr-11cctp' => ['fr-11bp', '11e compagnie de commandement et de transmissions parachutiste', '11e CCTP', 'company', null, '11th Parachute Command and Signal Company', ['def-11bp']],
        'fr-1rcp' => ['fr-11bp', '1er régiment de chasseurs parachutistes', '1er RCP', 'regiment', null, '1st Parachute Chasseur Regiment (1er RCP)', ['def-11bp']],
        'fr-2rep' => ['fr-11bp', '2e régiment étranger de parachutistes', '2e REP', 'regiment', null, '2nd Foreign Parachute Regiment (2e REP)', ['def-11bp', 'def-2rep']],
        'fr-3rpima' => ['fr-11bp', '3e régiment de parachutistes d’infanterie de marine', '3e RPIMa', 'regiment', null, '3rd Marine Infantry Parachute Regiment (3e RPIMa)', ['def-11bp']],
        'fr-8rpima' => ['fr-11bp', '8e régiment de parachutistes d’infanterie de marine', '8e RPIMa', 'regiment', null, '8th Marine Infantry Parachute Regiment (8e RPIMa)', ['def-11bp']],
        'fr-1rhp' => ['fr-11bp', '1er régiment de hussards parachutistes', '1er RHP', 'regiment', null, '1st Parachute Hussar Regiment (1er RHP)', ['def-11bp']],
        'fr-35rap' => ['fr-11bp', '35e régiment d’artillerie parachutiste', '35e RAP', 'regiment', null, '35th Parachute Artillery Regiment (35e RAP)', ['def-11bp']],
        'fr-17rgp' => ['fr-11bp', '17e régiment du génie parachutiste', '17e RGP', 'regiment', null, '17th Parachute Engineer Regiment (17e RGP)', ['def-11bp']],
        'fr-1rtp' => ['fr-11bp', '1er régiment du train parachutiste', '1er RTP', 'regiment', null, '1st Parachute Logistics Regiment (1er RTP)', ['def-11bp']],
        'fr-etap' => ['fr-11bp', 'École des troupes aéroportées', 'ETAP', 'school', null, 'Airborne Troops School (ETAP)', ['def-11bp']],

        // --- France : 27e BIM ---
        'fr-27bim' => [null, '27e brigade d’infanterie de montagne', '27e BIM', 'brigade', null, '27th Mountain Infantry Brigade (27e BIM)', ['def-27bim']],
        'fr-27bim-gcm' => ['fr-27bim', 'Groupement de commandos montagne', 'GCM', 'grouping', null, 'Mountain commando group (GCM)', ['def-27bim']],
        'fr-7bca' => ['fr-27bim', '7e bataillon de chasseurs alpins', '7e BCA', 'battalion', null, '7th Alpine Chasseur Battalion (7e BCA)', ['def-27bim']],
        'fr-13bca' => ['fr-27bim', '13e bataillon de chasseurs alpins', '13e BCA', 'battalion', null, '13th Alpine Chasseur Battalion (13e BCA)', ['def-27bim']],
        'fr-27bca' => ['fr-27bim', '27e bataillon de chasseurs alpins', '27e BCA', 'battalion', null, '27th Alpine Chasseur Battalion (27e BCA)', ['def-27bim']],
        'fr-4rch' => ['fr-27bim', '4e régiment de chasseurs', '4e RCh', 'regiment', null, '4th Chasseur Regiment (4e RCh)', ['def-27bim']],
        'fr-93ram' => ['fr-27bim', '93e régiment d’artillerie de montagne', '93e RAM', 'regiment', null, '93rd Mountain Artillery Regiment (93e RAM)', ['def-27bim']],
        'fr-2reg' => ['fr-27bim', '2e régiment étranger de génie', '2e REG', 'regiment', null, '2nd Foreign Engineer Regiment (2e REG)', ['def-27bim']],
        'fr-emhm' => ['fr-27bim', 'École militaire de haute montagne', 'EMHM', 'school', null, 'High Mountain Military School (EMHM)', ['def-27bim', 'terre-emhm']],

        // --- Allemagne : Division Schnelle Kräfte ---
        'de-dsk' => [null, 'Division Schnelle Kräfte', 'DSK', 'division', 'Division des forces rapides', 'Rapid Forces Division (DSK)', ['bw-dsk']],
        'de-ksk' => ['de-dsk', 'Kommando Spezialkräfte', 'KSK', 'command', 'Commandement des forces spéciales', 'Special Forces Command (KSK)', ['bw-ksk']],
        'de-ksk-stab' => ['de-ksk', 'Stab Kommando Spezialkräfte', 'Stab KSK', 'staff', 'État-major du KSK', 'KSK headquarters', ['bw-ksk']],
        'de-ksk-kdokr' => ['de-ksk', 'Kommandokräfte', 'KdoKr', 'grouping', 'Forces commando', 'Commando forces', ['bw-ksk']],
        'de-ksk-kdokp' => ['de-ksk-kdokr', 'Kommandokompanie', 'KdoKp', 'company', 'Compagnie commando (compagnie type)', 'Commando company (template)', ['bw-ksk']],
        'de-ksk-vertikalzug' => ['de-ksk-kdokp', 'Vertikalzug', 'VertZg', 'platoon', 'Section verticale (chuteurs opérationnels)', 'Vertical platoon (free fall)', ['bw-ksk']],
        'de-ksk-amphibzug' => ['de-ksk-kdokp', 'Amphibischer Zug', 'AmphZg', 'platoon', 'Section amphibie', 'Amphibious platoon', ['bw-ksk']],
        'de-ksk-gebzug' => ['de-ksk-kdokp', 'Gebirgs-/Arktiszug', 'GebArkZg', 'platoon', 'Section montagne et arctique', 'Mountain and Arctic platoon', ['bw-ksk']],
        'de-ksk-spezkdokp' => ['de-ksk', 'Spezialkommandokompanie', 'SpezKdoKp', 'company', 'Compagnie de commandos spéciaux', 'Special commando company', ['bw-ksk']],
        'de-ksk-spezhundezug' => ['de-ksk-spezkdokp', 'Spezialhundezug', 'SpezHdZg', 'platoon', 'Section cynophile spéciale', 'Special dog platoon', ['bw-ksk']],
        'de-ksk-spezaufklkp' => ['de-ksk', 'Spezialaufklärungskompanie', 'SpezAufklKp', 'company', 'Compagnie de reconnaissance spéciale', 'Special reconnaissance company', ['bw-ksk']],
        'de-ksk-ustgkr' => ['de-ksk', 'Unterstützungskräfte KSK', 'UstgKr KSK', 'grouping', 'Forces de soutien du KSK', 'KSK support forces', ['bw-ksk']],
        'de-ksk-fmkp' => ['de-ksk-ustgkr', 'Fernmeldekompanie KSK', 'FmKp KSK', 'company', 'Compagnie de transmissions du KSK', 'KSK signal company', ['bw-ksk']],
        'de-ksk-sanspezzug' => ['de-ksk-ustgkr', 'Sanitätsspezialzug', 'SanSpezZg', 'platoon', 'Section sanitaire spéciale', 'Special medical platoon', ['bw-ksk']],
        'de-ksk-pispezzug' => ['de-ksk-ustgkr', 'Pionierspezialzug', 'PiSpezZg', 'platoon', 'Section spéciale du génie', 'Special engineer platoon', ['bw-ksk']],
        'de-ksk-ausbstp' => ['de-ksk', 'Ausbildungsstützpunkt Spezialkräfte Heer', 'AusbStpSpezKrH', 'school', 'Centre de formation des forces spéciales de l’armée de terre', 'Army special forces training base', ['bw-ksk-ausb']],
        'de-llbrig1' => ['de-dsk', 'Luftlandebrigade 1', 'LLBrig 1', 'brigade', '1re brigade aéroportée', '1st Airborne Brigade (LLBrig 1)', ['bw-llbrig1']],
        'de-llbrig1-stfmkp' => ['de-llbrig1', 'Stabs- und Fernmeldekompanie Luftlandebrigade 1', 'StFmKp LLBrig 1', 'company', 'Compagnie de commandement et de transmissions', 'HQ and signal company', ['bw-llbrig1']],
        'de-fschjgrgt26' => ['de-llbrig1', 'Fallschirmjägerregiment 26', 'FschJgRgt 26', 'regiment', '26e régiment de chasseurs parachutistes', '26th Paratrooper Regiment (FschJgRgt 26)', ['bw-llbrig1']],
        'de-fschjgrgt26-egb' => ['de-fschjgrgt26', 'EGB-Kompanie Fallschirmjägerregiment 26', 'EGB FschJgRgt 26', 'company', 'Compagnie EGB (capacités de base étendues)', 'EGB company (extended basic capability)', ['bw-egb']],
        'de-fschjgrgt26-7' => ['de-fschjgrgt26', '7. (schwere) Kompanie Fallschirmjägerregiment 26', '7./FschJgRgt 26', 'company', '7e compagnie (lourde)', '7th (heavy) company', ['bw-llbrig1']],
        'de-fschjgrgt31' => ['de-llbrig1', 'Fallschirmjägerregiment 31', 'FschJgRgt 31', 'regiment', '31e régiment de chasseurs parachutistes', '31st Paratrooper Regiment (FschJgRgt 31)', ['bw-llbrig1']],
        'de-fschjgrgt31-1' => ['de-fschjgrgt31', '1. Kompanie Fallschirmjägerregiment 31', '1./FschJgRgt 31', 'company', '1re compagnie', '1st company', ['bw-fschspzzug']],
        'de-fschspzzug' => ['de-fschjgrgt31-1', 'Fallschirmspezialzug', 'FschSpezZg', 'platoon', 'Section parachutiste spéciale', 'Parachute special platoon', ['bw-fschspzzug']],
        'de-fschjgrgt31-2' => ['de-fschjgrgt31', '2. Kompanie (EGB) Fallschirmjägerregiment 31', '2./FschJgRgt 31', 'company', '2e compagnie (EGB)', '2nd company (EGB)', ['bw-egb']],
        'de-fschjgrgt31-7' => ['de-fschjgrgt31', '7. (schwere) Kompanie Fallschirmjägerregiment 31', '7./FschJgRgt 31', 'company', '7e compagnie (lourde)', '7th (heavy) company', ['bw-llbrig1']],
        'de-llpikp260' => ['de-llbrig1', 'Luftlandepionierkompanie 260', 'LlPiKp 260', 'company', '260e compagnie du génie aéroporté', '260th Airborne Engineer Company', ['bw-llpi']],
        'de-llpikp270' => ['de-llbrig1', 'Luftlandepionierkompanie 270', 'LlPiKp 270', 'company', '270e compagnie du génie aéroporté', '270th Airborne Engineer Company', ['bw-llpi']],
        'de-gebjgbrig23' => ['de-dsk', 'Gebirgsjägerbrigade 23', 'GebJgBrig 23', 'brigade', '23e brigade de chasseurs de montagne', '23rd Mountain Infantry Brigade (GebJgBrig 23)', ['bw-gebjgbrig23']],
        'de-gebjgbtl231' => ['de-gebjgbrig23', 'Gebirgsjägerbataillon 231', 'GebJgBtl 231', 'battalion', '231e bataillon de chasseurs de montagne', '231st Mountain Infantry Battalion', ['bw-gebjgbtl231']],
        'de-hgebjgzug' => ['de-gebjgbtl231', 'Hochgebirgsjägerzug', 'HGebJgZg', 'platoon', 'Section de chasseurs de haute montagne', 'High-mountain infantry platoon', ['bw-gebjgbtl231']],
        'de-gebjgbtl232' => ['de-gebjgbrig23', 'Gebirgsjägerbataillon 232', 'GebJgBtl 232', 'battalion', '232e bataillon de chasseurs de montagne', '232nd Mountain Infantry Battalion', ['bw-gebjgbrig23']],
        'de-gebjgbtl233' => ['de-gebjgbrig23', 'Gebirgsjägerbataillon 233', 'GebJgBtl 233', 'battalion', '233e bataillon de chasseurs de montagne', '233rd Mountain Infantry Battalion', ['bw-gebjgbrig23']],
        'de-gebaufklkp23' => ['de-gebjgbrig23', 'Gebirgsaufklärungskompanie 23', 'GebAufklKp 23', 'company', '23e compagnie de reconnaissance de montagne', '23rd Mountain Reconnaissance Company', ['bw-gebjgbrig23']],
        'de-tragtier230' => ['de-gebjgbrig23', 'Einsatz- und Ausbildungszentrum für Tragtierwesen 230', 'EAZ Tragtier 230', 'center', 'Centre d’emploi et de formation des animaux de bât 230', 'Pack Animal Operations and Training Centre 230', ['bw-gebjgbrig23']],
        'de-kdohubschr' => ['de-dsk', 'Kommando Hubschrauber', 'Kdo Hubschr', 'command', 'Commandement des hélicoptères', 'Helicopter Command', ['bw-kdohubschr']],
        'de-thr10' => ['de-kdohubschr', 'Transporthubschrauberregiment 10', 'THR 10', 'regiment', '10e régiment d’hélicoptères de transport', '10th Transport Helicopter Regiment (THR 10)', ['bw-thr10']],
        'de-thr30' => ['de-kdohubschr', 'Transporthubschrauberregiment 30', 'THR 30', 'regiment', '30e régiment d’hélicoptères de transport', '30th Transport Helicopter Regiment (THR 30)', ['bw-kdohubschr']],
        'de-khr36' => ['de-kdohubschr', 'Kampfhubschrauberregiment 36', 'KHR 36', 'regiment', '36e régiment d’hélicoptères de combat', '36th Attack Helicopter Regiment (KHR 36)', ['bw-khr36']],
        'de-ihaz' => ['de-kdohubschr', 'Internationales Hubschrauberausbildungszentrum', 'IntHubschrAusbZ', 'school', 'Centre international de formation hélicoptère', 'International Helicopter Training Centre', ['bw-ihaz']],
        'de-fspkp1' => ['de-dsk', 'Fernspähkompanie 1', 'FSpKp 1', 'company', '1re compagnie de reconnaissance à longue distance', '1st Long-Range Reconnaissance Company', ['bw-fspkp1']],

        // --- Allemagne : Deutsche Marine ---
        'de-ksm' => [null, 'Kommando Spezialkräfte der Marine', 'KSM', 'command', 'Commandement des forces spéciales de la marine', 'Naval Special Forces Command (KSM)', ['bw-ksm', 'bw-ksm-kampfschwimmer']],
        'de-ksm-stab' => ['de-ksm', 'Stab Kommando Spezialkräfte der Marine', 'Stab KSM', 'staff', 'État-major du KSM', 'KSM headquarters', ['bw-ksm']],
        'de-ksm-kschwkp' => ['de-ksm', 'Kampfschwimmerkompanie', 'KSchwKp', 'company', 'Compagnie de nageurs de combat', 'Combat swimmer company', ['bw-ksm', 'bw-ksm-kampfschwimmer']],
        'de-ksm-sbk' => ['de-ksm', 'Spezialoperationen-Bootskompanie', 'SBK', 'company', 'Compagnie d’embarcations des opérations spéciales', 'Special operations boat company', ['bw-ksm']],
        'de-ksm-ustgkp' => ['de-ksm', 'Unterstützungskompanie KSM', 'UstgKp KSM', 'company', 'Compagnie de soutien du KSM', 'KSM support company', ['bw-ksm']],
        'de-ksm-ausbz' => ['de-ksm', 'Ausbildungszentrum Spezialkräfte der Marine', 'AusbZ SpezKrM', 'school', 'Centre de formation des forces spéciales de la marine', 'Naval special forces training centre', ['bw-ksm']],
        'de-ksm-somst' => ['de-ksm', 'Sanitätsgruppe Spezialeinsatz', 'SOMST', 'team', 'Groupe médical d’intervention spéciale (SOMST)', 'Special operations medical support team (SOMST)', ['bw-ksm']],
        'de-seebtl' => [null, 'Seebataillon', 'SeeBtl', 'battalion', 'Bataillon d’infanterie de marine (Seebataillon)', 'Naval infantry battalion (Seebataillon)', ['bw-seebtl']],
        'de-seebtl-bek' => ['de-seebtl', 'Bordeinsatzkompanie', 'BEK', 'company', 'Compagnie d’intervention embarquée', 'Boarding company', ['bw-seebtl']],
        'de-seebtl-kek' => ['de-seebtl', 'Küsteneinsatzkompanie', 'KEK', 'company', 'Compagnie d’intervention côtière', 'Coastal operations company', ['bw-seebtl']],
        'de-seebtl-mitakp' => ['de-seebtl', 'Minentaucherkompanie', 'MiTaKp', 'company', 'Compagnie de plongeurs démineurs', 'Mine clearance diver company', ['bw-seebtl', 'bw-minentaucher']],
        'de-seebtl-aufklkp' => ['de-seebtl', 'Aufklärungskompanie Seebataillon', 'AufklKp SeeBtl', 'company', 'Compagnie de reconnaissance', 'Reconnaissance company', ['bw-seebtl']],
        'de-seebtl-sszug' => ['de-seebtl-aufklkp', 'Scharfschützenzug', 'SSZg', 'platoon', 'Section de tireurs d’élite', 'Sniper platoon', ['bw-seebtl']],
        'de-seebtl-drohnenzug' => ['de-seebtl-aufklkp', 'Drohnenzug', 'DrZg', 'platoon', 'Section drones', 'Drone platoon', ['bw-seebtl']],
        'de-seebtl-ustgkp' => ['de-seebtl', 'Unterstützungskompanie Seebataillon', 'UstgKp SeeBtl', 'company', 'Compagnie de soutien', 'Support company', ['bw-seebtl']],
        'de-seebtl-ausbkp' => ['de-seebtl', 'Ausbildungskompanie Seebataillon', 'AusbKp SeeBtl', 'company', 'Compagnie d’instruction', 'Training company', ['bw-seebtl']],

        // --- Allemagne : Luftwaffe ---
        'de-hsg64' => [null, 'Hubschraubergeschwader 64', 'HSG 64', 'wing', '64e escadre d’hélicoptères', '64th Helicopter Wing (HSG 64)', ['bw-hsg64', 'bw-sofair']],
        'de-hsg64-flg' => ['de-hsg64', 'Fliegende Gruppe Hubschraubergeschwader 64', 'FlgGrp HSG 64', 'group', 'Groupe navigant', 'Flying group', ['bw-sofair']],
        'de-hsg64-tech' => ['de-hsg64', 'Technische Gruppe Hubschraubergeschwader 64', 'TechGrp HSG 64', 'group', 'Groupe technique', 'Technical group', ['bw-sofair']],
        'de-hsg64-sofair' => ['de-hsg64', 'Einsatzgruppe SOF Air', 'EinsGrp SOF Air', 'group', 'Groupe d’intervention SOF Air (hélicoptères des forces spéciales)', 'SOF Air operations group', ['bw-hsg64', 'bw-sofair']],

        // --- Royaume-Uni (données publiques ; UKSF : aucune organisation officielle détaillée publiée) ---
        'gb-uksf' => [null, 'United Kingdom Special Forces', 'UKSF', 'command', 'Forces spéciales du Royaume-Uni (sous le Director Special Forces)', null, ['uk-mod', 'wiki-uksf']],
        'gb-22sas' => ['gb-uksf', '22 Special Air Service Regiment', '22 SAS', 'regiment', '22e régiment du Special Air Service', null, ['wiki-uksf']],
        'gb-21sas' => ['gb-uksf', '21 Special Air Service Regiment (Artists) (Reserve)', '21 SAS(R)', 'regiment', '21e régiment SAS (réserve)', null, ['wiki-uksf']],
        'gb-23sas' => ['gb-uksf', '23 Special Air Service Regiment (Reserve)', '23 SAS(R)', 'regiment', '23e régiment SAS (réserve)', null, ['wiki-uksf']],
        'gb-sbs' => ['gb-uksf', 'Special Boat Service', 'SBS', 'unit', 'Special Boat Service (forces spéciales navales)', null, ['wiki-uksf']],
        'gb-srr' => ['gb-uksf', 'Special Reconnaissance Regiment', 'SRR', 'regiment', 'Régiment de reconnaissance spéciale', null, ['wiki-uksf']],
        'gb-18sigs' => ['gb-uksf', '18 (UKSF) Signal Regiment', '18 (UKSF) Sigs', 'regiment', '18e régiment de transmissions des forces spéciales', null, ['wiki-uksf']],
        'gb-sfsg' => ['gb-uksf', 'Special Forces Support Group', 'SFSG', 'unit', 'Groupe d’appui des forces spéciales', null, ['wiki-uksf']],
        'gb-jsfaw' => ['gb-uksf', 'Joint Special Forces Aviation Wing', 'JSFAW', 'wing', 'Escadre interarmées d’aviation des forces spéciales', null, ['wiki-uksf']],
        'gb-7sqn' => ['gb-jsfaw', 'No. 7 Squadron RAF', '7 Sqn', 'squadron', '7e escadron de la RAF (Chinook)', null, ['wiki-uksf', 'wiki-odiham']],
        'gb-658sqn' => ['gb-jsfaw', '658 Squadron Army Air Corps', '658 Sqn AAC', 'squadron', '658e escadron de l’Army Air Corps', null, ['wiki-uksf']],
        'gb-16aa' => [null, '16 Air Assault Brigade Combat Team', '16 AA BCT', 'brigade', '16e brigade d’assaut aérien', null, ['army-16aa']],
        'gb-2para' => ['gb-16aa', '2nd Battalion, The Parachute Regiment', '2 PARA', 'battalion', '2e bataillon du Parachute Regiment', null, ['army-16aa']],
        'gb-3para' => ['gb-16aa', '3rd Battalion, The Parachute Regiment', '3 PARA', 'battalion', '3e bataillon du Parachute Regiment', null, ['army-16aa']],
        'gb-7rha' => ['gb-16aa', '7th Parachute Regiment Royal Horse Artillery', '7 RHA', 'regiment', '7e régiment parachutiste de la Royal Horse Artillery', null, ['army-16aa']],
        'gb-23pera' => ['gb-16aa', '23 Parachute Engineer Regiment', '23 Para Engr Regt', 'regiment', '23e régiment du génie parachutiste', null, ['army-23pera']],
        'gb-13aasr' => ['gb-16aa', '13 Air Assault Support Regiment RLC', '13 AASR', 'regiment', '13e régiment de soutien d’assaut aérien (Royal Logistic Corps)', null, ['army-16aa']],
        'gb-16med' => ['gb-16aa', '16 Medical Regiment', '16 Med Regt', 'regiment', '16e régiment médical', null, ['army-16med']],
        'gb-216sigs' => ['gb-16aa', '216 (Parachute) Signal Squadron', '216 Sig Sqn', 'squadron', '216e escadron de transmissions parachutiste', null, ['army-16aa']],
        'gb-ukcf' => [null, 'UK Commando Force', 'UKCF', 'brigade', 'Force commando du Royaume-Uni (ex-3 Commando Brigade, Royal Marines)', 'UK Commando Force (formerly 3 Commando Brigade, Royal Marines)', ['rn-ukcf']],
        'gb-40cdo' => ['gb-ukcf', '40 Commando Royal Marines', '40 Cdo', 'commando', '40 Commando des Royal Marines', null, ['rn-ukcf']],
        'gb-42cdo' => ['gb-ukcf', '42 Commando Royal Marines', '42 Cdo', 'commando', '42 Commando des Royal Marines', null, ['rn-ukcf']],
        'gb-43cdo' => ['gb-ukcf', '43 Commando Fleet Protection Group Royal Marines', '43 Cdo FP Gp', 'commando', '43 Commando, groupe de protection de la flotte', null, ['rn-ukcf']],
        'gb-45cdo' => ['gb-ukcf', '45 Commando Royal Marines', '45 Cdo', 'commando', '45 Commando des Royal Marines', null, ['rn-ukcf']],
        'gb-jac' => [null, 'Joint Aviation Command', 'JAC', 'command', 'Commandement interarmées de l’aviation (ex-Joint Helicopter Command)', 'Joint Aviation Command (formerly Joint Helicopter Command)', ['gov-jhc']],
        'gb-aac' => ['gb-jac', 'Army Air Corps', 'AAC', 'grouping', 'Aviation de l’armée de terre britannique', null, ['army-aac']],
        'gb-1rgtaac' => ['gb-aac', '1 Regiment Army Air Corps', '1 Regt AAC', 'regiment', '1er régiment de l’Army Air Corps (Wildcat)', null, ['army-aac']],
        'gb-3rgtaac' => ['gb-aac', '3 Regiment Army Air Corps', '3 Regt AAC', 'regiment', '3e régiment de l’Army Air Corps (Apache)', null, ['army-aac']],
        'gb-4rgtaac' => ['gb-aac', '4 Regiment Army Air Corps', '4 Regt AAC', 'regiment', '4e régiment de l’Army Air Corps (Apache)', null, ['army-aac']],
        'gb-odiham' => ['gb-jac', 'Chinook Force, RAF Odiham', 'RAF Odiham', 'grouping', 'Force Chinook de la RAF (Odiham)', null, ['gov-jhc', 'wiki-odiham']],
        'gb-18sqn' => ['gb-odiham', 'No. 18 (B) Squadron RAF', '18(B) Sqn', 'squadron', '18e escadron de la RAF (Chinook)', null, ['wiki-odiham']],
        'gb-27sqn' => ['gb-odiham', 'No. 27 Squadron RAF', '27 Sqn', 'squadron', '27e escadron de la RAF (Chinook)', null, ['wiki-odiham']],
        // --- Malaisie : Angkatan Tentera Malaysia (noms officiels en malais) ---
        'my-21ggk' => [null, '21 Gerup Gerak Khas', '21 GGK', 'command', '21e groupe des forces spéciales (Grup Gerak Khas)', '21st Special Service Group (21 GGK)', ['wiki-my-ggk']],
        'my-21ggk-mk' => ['my-21ggk', 'Markas Garison 21 Gerup Gerak Khas', 'MK Garison', 'staff', 'Quartier général de garnison', 'Garrison headquarters', ['wiki-my-ggk']],
        'my-11rgk' => ['my-21ggk', 'Rejimen 11 Gerak Khas', '11 RGK', 'regiment', '11e régiment des forces spéciales (contre-terrorisme)', '11th Special Service Regiment (counter-terrorism)', ['wiki-my-ggk']],
        'my-21cdo' => ['my-21ggk', 'Rejimen 21 Komando', '21 Komando', 'regiment', '21e régiment commando', '21st Commando Regiment', ['wiki-my-ggk']],
        'my-22cdo' => ['my-21ggk', 'Rejimen 22 Komando', '22 Komando', 'regiment', '22e régiment commando (amphibie)', '22nd Commando Regiment (amphibious)', ['wiki-my-ggk']],
        'my-21klog' => ['my-21ggk', 'Kumpulan Logistik 21', '21 Kump Log', 'group', '21e groupe logistique', '21st Logistic Group', ['wiki-my-ggk']],
        'my-21ssd' => ['my-21ggk', 'Skuadron Semboyan Gerak Khas 21', '21 SSD', 'squadron', '21e escadron de transmissions des forces spéciales', '21st Special Forces Signal Squadron', ['wiki-my-ggk']],
        'my-21medik' => ['my-21ggk', 'Skuadron Medik Gerak Khas 21', '21 Medik', 'squadron', '21e escadron médical des forces spéciales', '21st Special Forces Medical Squadron', ['wiki-my-ggk']],
        'my-21kptd' => ['my-21ggk', 'Kompeni Polis Tentera Diraja 21', '21 KPTD', 'company', '21e compagnie de police militaire', '21st Royal Military Police Company', ['wiki-my-ggk']],
        'my-21risik' => ['my-21ggk', 'Sel Risik 21 GGK', 'Sel Risik', 'unit', 'Cellule de renseignement', 'Intelligence cell', ['wiki-my-ggk']],
        'my-10bpara' => [null, 'Briged Ke-10 Payung Terjun', '10 Briged Para', 'brigade', '10e brigade parachutiste (Pasukan Aturgerak Cepat)', '10th Parachute Brigade (Rapid Deployment Force)', ['wiki-my-10bpara']],
        'my-8rrd' => ['my-10bpara', 'Batalion Ke-8 Rejimen Renjer Diraja (Payung Terjun)', '8 RRD (Para)', 'battalion', '8e bataillon parachutiste du Royal Ranger Regiment', '8th Battalion (Parachute), Royal Ranger Regiment', ['wiki-my-10bpara']],
        'my-9ramd' => ['my-10bpara', 'Batalion Ke-9 Rejimen Askar Melayu Diraja (Payung Terjun)', '9 RAMD (Para)', 'battalion', '9e bataillon parachutiste du Royal Malay Regiment', '9th Battalion (Parachute), Royal Malay Regiment', ['wiki-my-10bpara', 'wiki-my-9ramd', 'wiki-my-inf']],
        'my-9ramd-mk' => ['my-9ramd', 'Markas Batalion 9 RAMD (Para)', 'MK 9 RAMD', 'staff', 'État-major du bataillon', 'Battalion headquarters', ['wiki-my-9ramd']],
        'my-9ramd-kpi-a' => ['my-9ramd', 'Kompeni A', 'Kpi A', 'company', 'Compagnie A (fusiliers parachutistes)', 'A Company (airborne infantry)', ['wiki-my-9ramd']],
        'my-9ramd-kpi-a-mk' => ['my-9ramd-kpi-a', 'Markas Kompeni A', 'MK Kpi A', 'staff', 'Commandement de la compagnie', 'Company headquarters', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl1' => ['my-9ramd-kpi-a', 'Platun 1', 'Pl 1', 'platoon', 'Section 1 (platun : un officier, un Sarjan, trois seksyen)', 'Platoon 1', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl1-sk1' => ['my-9ramd-a-pl1', 'Seksyen 1', 'Sk 1-1', 'section', 'Groupe 1 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 1', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl1-sk2' => ['my-9ramd-a-pl1', 'Seksyen 2', 'Sk 1-2', 'section', 'Groupe 2 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 2', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl1-sk3' => ['my-9ramd-a-pl1', 'Seksyen 3', 'Sk 1-3', 'section', 'Groupe 3 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 3', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl2' => ['my-9ramd-kpi-a', 'Platun 2', 'Pl 2', 'platoon', 'Section 2 (platun : un officier, un Sarjan, trois seksyen)', 'Platoon 2', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl2-sk1' => ['my-9ramd-a-pl2', 'Seksyen 1', 'Sk 2-1', 'section', 'Groupe 1 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 1', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl2-sk2' => ['my-9ramd-a-pl2', 'Seksyen 2', 'Sk 2-2', 'section', 'Groupe 2 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 2', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl2-sk3' => ['my-9ramd-a-pl2', 'Seksyen 3', 'Sk 2-3', 'section', 'Groupe 3 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 3', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl3' => ['my-9ramd-kpi-a', 'Platun 3', 'Pl 3', 'platoon', 'Section 3 (platun : un officier, un Sarjan, trois seksyen)', 'Platoon 3', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl3-sk1' => ['my-9ramd-a-pl3', 'Seksyen 1', 'Sk 3-1', 'section', 'Groupe 1 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 1', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl3-sk2' => ['my-9ramd-a-pl3', 'Seksyen 2', 'Sk 3-2', 'section', 'Groupe 2 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 2', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-a-pl3-sk3' => ['my-9ramd-a-pl3', 'Seksyen 3', 'Sk 3-3', 'section', 'Groupe 3 (seksyen : un Koperal, un Lans Koperal et leurs soldats)', 'Section 3', ['wiki-my-9ramd', 'wiki-section']],
        'my-9ramd-kpi-b' => ['my-9ramd', 'Kompeni B', 'Kpi B', 'company', 'Compagnie B (fusiliers parachutistes)', 'B Company (airborne infantry)', ['wiki-my-9ramd']],
        'my-9ramd-kpi-c' => ['my-9ramd', 'Kompeni C', 'Kpi C', 'company', 'Compagnie C (fusiliers parachutistes)', 'C Company (airborne infantry)', ['wiki-my-9ramd']],
        'my-9ramd-kpi-d' => ['my-9ramd', 'Kompeni D', 'Kpi D', 'company', 'Compagnie D (fusiliers parachutistes)', 'D Company (airborne infantry)', ['wiki-my-9ramd']],
        'my-9ramd-kpi-bantuan' => ['my-9ramd', 'Kompeni Bantuan', 'Kpi Bantuan', 'company', 'Compagnie d’appui (mortiers, mitrailleuses lourdes, antichar, transmissions)', 'Support Company (mortars, heavy machine guns, anti-tank, signals)', ['wiki-my-9ramd']],
        'my-17ramd' => ['my-10bpara', 'Batalion Ke-17 Rejimen Askar Melayu Diraja (Payung Terjun)', '17 RAMD (Para)', 'battalion', '17e bataillon parachutiste du Royal Malay Regiment', '17th Battalion (Parachute), Royal Malay Regiment', ['wiki-my-10bpara']],
        'my-18ramd' => ['my-10bpara', 'Batalion Ke-18 Rejimen Askar Melayu Diraja (Payung Terjun)', '18 RAMD (Para)', 'battalion', '18e bataillon parachutiste du Royal Malay Regiment', '18th Battalion (Parachute), Royal Malay Regiment', ['wiki-my-10bpara']],
        'my-10bpara-pandura' => ['my-10bpara', 'Kompeni Pandura (Para)', 'Pandura', 'company', 'Compagnie d’éclaireurs parachutistes (Pathfinder)', 'Pathfinder Company (Parachute)', ['wiki-my-10bpara']],
        'my-10bpara-amfibi' => ['my-10bpara', 'Kompeni Bot Amfibi', 'Kpi Bot Amfibi', 'company', 'Compagnie d’embarcations amphibies', 'Amphibious Boat Company', ['wiki-my-10bpara']],
        'my-paskal' => [null, 'Pasukan Khas Laut', 'PASKAL', 'command', 'Forces spéciales de la marine royale malaisienne', 'Royal Malaysian Navy special forces (PASKAL)', ['wiki-my-paskal']],
        'my-paskal-ptc' => ['my-paskal', 'KD Panglima Hitam', 'KD Panglima Hitam', 'staff', 'Commandement des équipes PASKAL', 'PASKAL Team Command', ['wiki-my-paskal']],
        'my-paskal-u1' => ['my-paskal', 'PASKAL Unit Satu', 'PASKAL U1', 'unit', 'Unité 1 (base navale de Lumut, Perak)', 'PASKAL Unit 1 (Lumut Naval Base)', ['wiki-my-paskal']],
        'my-paskal-u2' => ['my-paskal', 'PASKAL Unit Dua', 'PASKAL U2', 'unit', 'Unité 2 (base navale de Semporna, Sabah)', 'PASKAL Unit 2 (Semporna Naval Base)', ['wiki-my-paskal']],
        'my-paskal-trg' => ['my-paskal', 'Tim Risik Gempur', 'TRG', 'team', 'Équipe de renseignement de combat (une par escadron)', 'Combat Intelligence Team', ['wiki-my-paskal']],
        'my-paskau' => [null, 'Pasukan Khas TUDM', 'PASKAU', 'command', 'Forces spéciales de l’armée de l’air royale malaisienne', 'Royal Malaysian Air Force special forces (PASKAU)', ['wiki-my-paskau']],
        'my-paskau-sptu' => ['my-paskau', 'Skuadron Penyelamat Tempur Udara', 'SPTU', 'squadron', 'Escadron de sauvetage au combat (recherche et sauvetage)', 'Combat Air Rescue Squadron', ['wiki-my-paskau']],
        'my-paskau-skm' => ['my-paskau', 'Skuadron Kawalan Medan', 'SKM', 'squadron', 'Escadron de protection des forces (contre-terrorisme, libération d’otages)', 'Force Protection Squadron', ['wiki-my-paskau']],
    ];

    /**
     * Modèles : [suffixe du code, racine, unités gardées sans leurs subordonnées (null = toute la branche), titre, résumé, pays, armée,
     * commandement, système de grades, filtre du catalogue d’emplois].
     */
    private const KITS = [
        ['us_ussocom', 'us-ussocom', ['us-usasoc', 'us-nswc', 'us-afsoc', 'us-marsoc'], 'USSOCOM et commandements de théâtre (TSOC)', 'Le commandement des opérations spéciales des États-Unis : JSOC, les sept commandements d’opérations spéciales de théâtre (SOCAFRICA, SOCCENT, SOCEUR, SOCKOR, SOCNORTH, SOCPAC, SOCSOUTH) et les quatre commandements de composante, avec les grades américains.', 'US', 'joint', 'USSOCOM', 'US_CLASSIC', ['commands' => ['USSOCOM']]],
        ['us_usasoc', 'us-usasoc', null, 'USASOC : opérations spéciales de l’US Army', 'Le 1st Special Forces Command (Airborne) et ses sept groupes de forces spéciales, les opérations psychologiques, les affaires civiles et le soutien, le 75th Ranger Regiment, l’aviation des opérations spéciales (160th SOAR) et l’école de guerre spéciale, avec les grades américains.', 'US', 'army', 'USASOC', 'US_CLASSIC', ['commands' => ['USASOC']]],
        ['us_1sfc', 'us-1sfc', null, '1st Special Forces Command (Airborne)', 'Les groupes de forces spéciales d’active et de la Garde nationale, les groupes d’opérations psychologiques, la brigade d’affaires civiles, la brigade de soutien et le bataillon de renseignement, avec les grades américains.', 'US', 'army', 'USASOC', 'US_CLASSIC', ['commands' => ['USASOC'], 'unit_contains' => ['Special Forces Group', 'Psychological Operations', 'Civil Affairs', '528th', '112th']]],
        ['us_75rr', 'us-75rr', null, '75th Ranger Regiment', 'Les trois bataillons de Rangers, le bataillon des troupes spéciales et sa compagnie de reconnaissance, et le bataillon de renseignement militaire, avec les grades américains.', 'US', 'army', 'USASOC', 'US_CLASSIC', ['commands' => ['USASOC'], 'unit_contains' => ['75th Ranger Regiment']]],
        ['us_160soar', 'us-usasoac', null, '160th SOAR et aviation des opérations spéciales', 'Le commandement de l’aviation des opérations spéciales de l’US Army, le 160th Special Operations Aviation Regiment (Airborne) et ses quatre bataillons, et le bataillon d’instruction, avec les grades américains.', 'US', 'army', 'USASOC', 'US_CLASSIC', ['commands' => ['USASOC'], 'unit_contains' => ['160th', 'SOATB']]],
        ['us_nswc', 'us-nswc', null, 'NSWC : guerre spéciale navale (SEAL)', 'Le Naval Special Warfare Command : groupes 1, 2, 4, 8 et 11, SEAL Teams, SEAL Delivery Vehicle Teams, équipes de reconnaissance spéciale, Special Boat Teams (SWCC) et centre de formation, avec les grades américains.', 'US', 'navy', 'NSWC', 'US_CLASSIC', ['commands' => ['NSWC']]],
        ['us_afsoc', 'us-afsoc', null, 'AFSOC : opérations spéciales de l’US Air Force', 'L’Air Force Special Operations Command : escadres d’opérations spéciales d’active, de réserve et de la Garde nationale, groupes et escadrons Special Tactics, et escadrons navigants, avec les grades américains.', 'US', 'air_force', 'AFSOC', 'US_CLASSIC', ['commands' => ['AFSOC']]],
        ['us_marsoc', 'us-marsoc', null, 'MARSOC : Marine Raiders', 'Le Marine Forces Special Operations Command : Marine Raider Regiment et ses trois bataillons, Marine Raider Support Group et ses bataillons de soutien, centre de formation, et une compagnie et une équipe types (MSOC, MSOT), avec les grades américains.', 'US', 'marine_corps', 'MARSOC', 'US_CLASSIC', ['commands' => ['MARSOC']]],
        ['fr_cos', 'fr-cos', null, 'COS : forces spéciales françaises', 'Le Commandement des opérations spéciales et ses trois composantes : actions spéciales Terre (CAST), commandos Marine (FORFUSCO) et forces spéciales Air (BFSA), avec leurs régiments, commandos et escadrons, et les grades français.', 'FR', 'joint', 'COS', 'FR_CLASSIC', ['commands' => ['COS', 'CAST', 'FORFUSCO', 'BFSA']]],
        ['fr_cast', 'fr-cast', null, 'CAST : actions spéciales Terre', 'Le Commandement des actions spéciales Terre : 1er RPIMa et ses compagnies, 13e RDP, 4e RHFS, compagnie de commandement et de transmissions, CIAE et CPMO, avec les grades français.', 'FR', 'army', 'CAST', 'FR_CLASSIC', ['commands' => ['CAST']]],
        ['fr_1rpima', 'fr-1rpima', null, '1er RPIMa', 'Le 1er régiment de parachutistes d’infanterie de marine : compagnie de commandement et de logistique, quatre compagnies SAS et compagnie d’instruction, avec les grades français.', 'FR', 'army', 'CAST', 'FR_CLASSIC', ['commands' => ['CAST'], 'formations' => ['1er RPIMa']]],
        ['fr_forfusco', 'fr-forfusco', null, 'FORFUSCO : commandos Marine', 'La Force maritime des fusiliers marins et commandos et ses sept commandos : Hubert, Jaubert, Trépel, de Penfentenyo, de Montfort, Kieffer et Ponchardier, avec les grades français.', 'FR', 'navy', 'FORFUSCO', 'FR_CLASSIC', ['commands' => ['FORFUSCO']]],
        ['fr_bfsa', 'fr-bfsa', null, 'BFSA : forces spéciales Air', 'La Brigade des forces spéciales Air : commandos parachutistes de l’air n° 10, 20 et 30, escadron d’hélicoptères 1/67 « Pyrénées » et escadron de transport 3/61 « Poitou », avec les grades français.', 'FR', 'air_force', 'BFSA', 'FR_CLASSIC', ['commands' => ['BFSA']]],
        ['fr_gign', 'fr-gign', null, 'GIGN', 'Le Groupe d’intervention de la Gendarmerie nationale : cellule nationale de négociation, division technique et antennes régionales, avec les grades français.', 'FR', 'gendarmerie', 'DGGN', 'FR_CLASSIC', ['commands' => ['DGGN']]],
        ['fr_11bp', 'fr-11bp', null, '11e brigade parachutiste', 'Le groupement des commandos parachutistes, les huit régiments parachutistes, la compagnie de commandement et de transmissions et l’École des troupes aéroportées, avec les grades français.', 'FR', 'army', '11e BP', 'FR_CLASSIC', ['commands' => ['11e BP']]],
        ['fr_27bim', 'fr-27bim', null, '27e brigade d’infanterie de montagne', 'Le groupement de commandos montagne, les bataillons de chasseurs alpins, les régiments de chasseurs, d’artillerie et de génie de montagne, et l’École militaire de haute montagne, avec les grades français.', 'FR', 'army', '27e BIM', 'FR_CLASSIC', ['commands' => ['27e BIM']]],
        ['de_dsk', 'de-dsk', null, 'Division Schnelle Kräfte (DSK)', 'La division des forces rapides de l’armée de terre allemande : KSK, Luftlandebrigade 1, Gebirgsjägerbrigade 23, Kommando Hubschrauber et Fernspähkompanie 1, avec les grades allemands.', 'DE', 'army', 'DSK', 'DE_HEER', ['commands' => ['DSK', 'KSK']]],
        ['de_ksk', 'de-ksk', null, 'KSK : Kommando Spezialkräfte', 'Les forces spéciales de l’armée de terre allemande : état-major, forces commando et compagnie commando type, compagnies de commandos spéciaux et de reconnaissance spéciale, forces de soutien et centre de formation, avec les grades allemands.', 'DE', 'army', 'KSK', 'DE_HEER', ['commands' => ['KSK']]],
        ['de_llbrig1', 'de-llbrig1', null, 'Luftlandebrigade 1', 'La brigade aéroportée allemande : Fallschirmjägerregiment 26 et 31 avec leurs compagnies EGB et lourdes, Fallschirmspezialzug, compagnies du génie aéroporté et compagnie de commandement, avec les grades allemands.', 'DE', 'army', 'DSK', 'DE_HEER', ['commands' => ['DSK'], 'formations' => ['LLBrig 1', 'FschJgRgt 26', 'FschJgRgt 31']]],
        ['de_ksm', 'de-ksm', null, 'KSM : Kommando Spezialkräfte der Marine', 'Les nageurs de combat de la marine allemande : état-major, compagnie de nageurs de combat, compagnie d’embarcations, compagnie de soutien, centre de formation et équipe médicale, avec les grades de la marine allemande.', 'DE', 'navy', 'EinsFltl 1', 'DE_MARINE', ['commands' => ['EinsFltl 1'], 'formations' => ['KSM']]],
        ['de_seebtl', 'de-seebtl', null, 'Seebataillon', 'Le bataillon d’infanterie de marine allemand : compagnies d’intervention embarquée et côtière, plongeurs démineurs, reconnaissance (tireurs d’élite, drones), soutien et instruction, avec les grades de la marine allemande.', 'DE', 'navy', 'EinsFltl 1', 'DE_MARINE', ['commands' => ['EinsFltl 1'], 'formations' => ['Seebataillon']]],
        ['gb_uksf', 'gb-uksf', null, 'UKSF : forces spéciales britanniques', 'Les forces spéciales du Royaume-Uni : 22 SAS et les régiments SAS de réserve, Special Boat Service, Special Reconnaissance Regiment, 18 (UKSF) Signal Regiment, Special Forces Support Group et escadre d’aviation (7 Sqn RAF, 658 Sqn AAC), avec les grades britanniques. Organisation détaillée non publiée : unités principales seulement.', 'GB', 'joint', 'UKSF', 'GB_ARMY', ['commands' => ['UKSF']]],
        ['gb_16aa', 'gb-16aa', null, '16 Air Assault Brigade Combat Team', 'La brigade d’assaut aérien britannique : 2 PARA et 3 PARA, 7 RHA, 23 Parachute Engineer Regiment, 13 Air Assault Support Regiment, 16 Medical Regiment et 216 (Parachute) Signal Squadron, avec les grades britanniques.', 'GB', 'army', '16 Air Assault Bde', 'GB_ARMY', ['commands' => ['16 Air Assault Bde']]],
        ['gb_ukcf', 'gb-ukcf', null, 'UK Commando Force (ex-3 Commando Brigade)', 'La force commando des Royal Marines, nommée UK Commando Force depuis juin 2026 : 40, 42, 43 et 45 Commando, avec les grades britanniques (British Army et Royal Marines).', 'GB', 'marine_corps', 'UK Commando Force', 'GB_ARMY', ['commands' => ['UK Commando Force']]],
        ['gb_jac', 'gb-jac', null, 'Joint Aviation Command (ex-Joint Helicopter Command)', 'Le commandement interarmées des hélicoptères de combat britanniques : Army Air Corps (1 Regiment sur Wildcat, 3 et 4 Regiment sur Apache) et force Chinook de la RAF à Odiham (18 (B) et 27 Squadron), avec les grades britanniques.', 'GB', 'joint', 'JAC', 'GB_ARMY', ['commands' => ['JAC']]],
        ['de_hsg64', 'de-hsg64', null, 'Hubschraubergeschwader 64', 'L’escadre d’hélicoptères de la Luftwaffe : groupe navigant, groupe technique et groupe SOF Air au profit des forces spéciales, avec les grades de la Luftwaffe.', 'DE', 'air_force', 'Luftwaffe', 'DE_LW', ['commands' => ['Luftwaffe'], 'formations' => ['HSG 64']]],
        ['my_21ggk', 'my-21ggk', null, '21 Gerup Gerak Khas (GGK)', 'Les forces spéciales de l’armée de terre malaisienne : quartier général de garnison, 11e régiment des forces spéciales (contre-terrorisme), 21e et 22e régiments commando, soutien logistique, transmissions, santé, police militaire et renseignement, avec les grades malaisiens.', 'MY', 'army', 'GGK', 'MY_CLASSIC', ['commands' => ['GGK']]],
        ['my_10bpara', 'my-10bpara', ['my-9ramd'], 'Briged Ke-10 Payung Terjun (10 Briged Para)', 'La brigade parachutiste de la force de réaction rapide malaisienne (Pasukan Aturgerak Cepat) : quatre bataillons parachutistes du Royal Ranger Regiment et du Royal Malay Regiment, compagnie d’éclaireurs Pandura et compagnie d’embarcations amphibies, avec les grades malaisiens.', 'MY', 'army', '10 Briged Para', 'MY_CLASSIC', ['commands' => ['10 Briged Para']]],
        ['my_9ramd', 'my-9ramd', null, 'Batalion Askar Melayu : 9 RAMD (Para)', 'Un bataillon type du Royal Malay Regiment (9e bataillon parachutiste) : état-major, quatre compagnies de fusiliers et une compagnie d’appui ; la compagnie A est détaillée en trois platun de trois seksyen. Seksyen de huit à dix sous un Koperal secondé par un Lans Koperal, platun sous un Leftenan et un Sarjan, kompeni sous un Mejar, bataillon sous un Leftenan Kolonel, avec les grades malaisiens.', 'MY', 'army', '10 Briged Para', 'MY_CLASSIC', ['commands' => ['10 Briged Para', 'TDM']]],
        ['my_paskal', 'my-paskal', null, 'PASKAL : Pasukan Khas Laut', 'Les forces spéciales de la marine royale malaisienne : commandement des équipes (KD Panglima Hitam), unités 1 (Lumut) et 2 (Semporna) et équipes de renseignement de combat, avec les grades de la marine malaisienne.', 'MY', 'navy', 'TLDM', 'MY_TLDM', ['commands' => ['TLDM']]],
        ['my_paskau', 'my-paskau', null, 'PASKAU : Pasukan Khas TUDM', 'Les forces spéciales de l’armée de l’air royale malaisienne : escadron de sauvetage au combat et escadron de protection des forces (contre-terrorisme, libération d’otages), avec les grades de l’armée de l’air malaisienne.', 'MY', 'air_force', 'TUDM', 'MY_TUDM', ['commands' => ['TUDM']]],
    ];

    /** Échelons affichés dans le contenu d’un modèle (langue du lecteur). */
    private const ECHELONS = [
        'command' => ['fr' => 'Commandement', 'en' => 'Command'],
        'staff' => ['fr' => 'État-major', 'en' => 'Headquarters'],
        'grouping' => ['fr' => 'Regroupement', 'en' => 'Grouping'],
        'division' => ['fr' => 'Division', 'en' => 'Division'],
        'brigade' => ['fr' => 'Brigade', 'en' => 'Brigade'],
        'regiment' => ['fr' => 'Régiment', 'en' => 'Regiment'],
        'wing' => ['fr' => 'Escadre', 'en' => 'Wing'],
        'group' => ['fr' => 'Groupe', 'en' => 'Group'],
        'battalion' => ['fr' => 'Bataillon', 'en' => 'Battalion'],
        'unit' => ['fr' => 'Unité', 'en' => 'Unit'],
        'squadron' => ['fr' => 'Escadron', 'en' => 'Squadron'],
        'company' => ['fr' => 'Compagnie', 'en' => 'Company'],
        'commando' => ['fr' => 'Commando', 'en' => 'Commando'],
        'platoon' => ['fr' => 'Section', 'en' => 'Platoon'],
        'section' => ['fr' => 'Groupe (section de modèle britannique)', 'en' => 'Section'],
        'team' => ['fr' => 'Équipe', 'en' => 'Team'],
        'school' => ['fr' => 'Formation', 'en' => 'Training'],
        'center' => ['fr' => 'Centre', 'en' => 'Centre'],
    ];

    /** Type d’unité de l’organigramme pour chaque échelon (app/Config/units.php). */
    private const TYPES = [
        'battalion' => 'unit',
        'unit' => 'unit',
        'company' => 'unit',
        'commando' => 'unit',
        'squadron' => 'squadron',
        'platoon' => 'section',
        'section' => 'squad',
        'team' => 'team',
    ];

    /** @var list<array<string, mixed>>|null */
    private static ?array $kits = null;

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (array $k): string => self::PREFIX . $k[0], self::KITS);
    }

    public static function isRealUnitCode(string $code): bool
    {
        return str_starts_with($code, self::PREFIX);
    }

    /**
     * Modèles au format du catalogue (OrganizationKitDefinitions), plus l’origine, les sources et le filtre du
     * catalogue d’emplois.
     *
     * @return list<array<string, mixed>>
     */
    public static function kits(): array
    {
        if (self::$kits !== null) {
            return self::$kits;
        }
        $out = [];
        foreach (self::KITS as [$suffix, $root, $leaves, $title, $summary, $country, $branch, $command, $grade, $jobs]) {
            $units = [];
            $sourceKeys = [];
            $order = 0;
            foreach (self::branch($root, $leaves) as [$key]) {
                [$parent, $name, $code, $echelon, , , $sources] = self::NODES[$key];
                $order += 10;
                $units[] = [
                    'key' => $key,
                    'parent_key' => $key === $root ? null : $parent,
                    'name' => $name,
                    'slug' => $key,
                    'type' => self::TYPES[$echelon] ?? 'group',
                    'code' => $code,
                    'display_order' => $order,
                    'echelon' => $echelon,
                    'sources' => $sources,
                ];
                foreach ($sources as $s) {
                    $sourceKeys[$s] = true;
                }
            }
            $sources = [];
            foreach (array_keys($sourceKeys) as $s) {
                $sources[] = ['key' => $s, 'title' => self::SOURCES[$s][0], 'url' => self::SOURCES[$s][1]];
            }
            $out[] = [
                'code' => self::PREFIX . $suffix,
                'title' => $title,
                'summary' => $summary,
                'version' => 1,
                'grade_system_code' => $grade,
                'real_units' => true,
                'origin' => ['country' => $country, 'branch' => $branch, 'command' => $command],
                'job_catalog' => ['country' => $country] + $jobs,
                'sources' => $sources,
                'units' => $units,
                'job_role_categories' => [],
                'job_roles' => [],
                'roles' => [],
            ];
        }

        return self::$kits = $out;
    }

    /** @return array<string, array{0: string, 1: string}> clé => [titre, adresse] */
    public static function sources(): array
    {
        return self::SOURCES;
    }

    /** @return list<string> adresses courtes de toutes les unités */
    public static function slugs(): array
    {
        return array_keys(self::NODES);
    }

    /** Nom officiel d’une unité de l’arbre. */
    public static function officialName(string $slug): ?string
    {
        return self::NODES[$slug][1] ?? null;
    }

    /** Traduction affichée en complément du nom officiel (null : le nom se lit tel quel dans cette langue). */
    public static function translation(string $slug, string $lang): ?string
    {
        $node = self::NODES[$slug] ?? null;
        if ($node === null) {
            return null;
        }
        $text = $lang === 'en' ? $node[5] : $node[4];

        return is_string($text) && $text !== '' && $text !== $node[1] ? $text : null;
    }

    /** Libellé anglais d’une unité (lang/en/unit_labels.php). */
    public static function englishLabel(string $slug): ?string
    {
        $node = self::NODES[$slug] ?? null;

        return $node === null ? null : ($node[5] ?? $node[1]);
    }

    /**
     * Unité réelle de l’arbre correspondant à une unité de l’ORBAT : adresse courte reprise d’un modèle, sinon nom
     * officiel ou code court identique (« 24th STS », « 24 STS », « 24th Special Tactics Squadron »). null : aucune.
     */
    public static function findNode(string $slug, string $name, string $code = ''): ?string
    {
        if (isset(self::NODES[$slug])) {
            return $slug;
        }
        $norm = static fn (string $s): string => trim((string) preg_replace(['/(\d+)\s*(st|nd|rd|th|e|er|re|eme)\b/u', '/[^\p{L}\p{N}]+/u'], ['$1', ' '], mb_strtolower($s, 'UTF-8')));
        $wanted = array_filter([$norm($name), $norm($code)], static fn (string $s): bool => mb_strlen($s) >= 4);
        if ($wanted === []) {
            return null;
        }
        foreach (self::NODES as $key => $node) {
            if (in_array($norm($node[1]), $wanted, true) || in_array($norm($node[2]), $wanted, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Noms officiels et codes de l’unité puis de ses unités parentes, de la plus proche à la plus haute
     * (« 24th Special Tactics Squadron », « 724th Special Tactics Group », « 24th Special Operations Wing »…).
     *
     * @return list<string>
     */
    public static function lineage(string $slug): array
    {
        $out = [];
        $seen = [];
        while (isset(self::NODES[$slug]) && !isset($seen[$slug])) {
            $seen[$slug] = true;
            $node = self::NODES[$slug];
            $out[] = $node[1] . ' (' . $node[2] . ')' . (is_string($node[4] ?? null) ? ' ' . $node[4] : '');
            $slug = (string) ($node[0] ?? '');
        }

        return $out;
    }

    /** Pays d’une unité de l’arbre (préfixe de son adresse courte : « us-24sts » → US). */
    public static function countryOf(string $slug): string
    {
        return isset(self::NODES[$slug]) && preg_match('/^([a-z]{2})-/', $slug, $m) === 1 ? strtoupper($m[1]) : '';
    }

    public static function echelonLabel(string $echelon, string $lang): string
    {
        return self::ECHELONS[$echelon][$lang === 'en' ? 'en' : 'fr'] ?? '';
    }

    /** @return list<string> */
    public static function echelons(): array
    {
        return array_keys(self::ECHELONS);
    }

    /**
     * Emplois sourcés du catalogue d’emplois correspondant au commandement du modèle.
     *
     * @param array<string, mixed> $kit
     */
    public static function jobCatalogCount(array $kit): int
    {
        $filter = is_array($kit['job_catalog'] ?? null) ? $kit['job_catalog'] : [];
        $country = (string) ($filter['country'] ?? '');
        if ($country === '') {
            return 0;
        }
        $commands = array_map('strval', (array) ($filter['commands'] ?? []));
        $formations = array_map('strval', (array) ($filter['formations'] ?? []));
        $contains = array_map('strval', (array) ($filter['unit_contains'] ?? []));
        $n = 0;
        foreach (JobCatalog::rawEntries() as $entry) {
            if ((string) ($entry['country'] ?? '') !== $country) {
                continue;
            }
            if ($commands !== [] && !in_array((string) ($entry['command'] ?? ''), $commands, true)) {
                continue;
            }
            $formation = is_array($entry['formation'] ?? null) ? (string) ($entry['formation']['fr'] ?? '') : trim((string) ($entry['formation'] ?? ''));
            if ($formations !== [] && !in_array($formation, $formations, true)) {
                continue;
            }
            if ($contains !== []) {
                $unit = is_array($entry['unit'] ?? null) ? (string) ($entry['unit']['en'] ?? '') : (string) ($entry['unit'] ?? '');
                $hit = false;
                foreach ($contains as $needle) {
                    if ($needle !== '' && str_contains($unit, $needle)) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            $n++;
        }

        return $n;
    }

    /**
     * Unités d’une branche, parent avant enfants, dans l’ordre de NODES.
     *
     * @param list<string>|null $leaves unités reprises sans leurs subordonnées
     * @return list<array{0: string, 1: int}> [clé, niveau]
     */
    private static function branch(string $root, ?array $leaves): array
    {
        $children = [];
        foreach (self::NODES as $key => $node) {
            if ($node[0] !== null) {
                $children[$node[0]][] = $key;
            }
        }
        $out = [];
        $walk = static function (string $key, int $level) use (&$walk, &$out, $children, $leaves): void {
            $out[] = [$key, $level];
            if ($leaves !== null && in_array($key, $leaves, true)) {
                return;
            }
            foreach ($children[$key] ?? [] as $child) {
                $walk($child, $level + 1);
            }
        };
        $walk($root, 0);

        return $out;
    }
}
