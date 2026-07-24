/*
    Affiche un "captcha" troll dans l'ATAK Enhanced.
    Le joueur doit résoudre des défis absurdes pour continuer.
*/

if (!hasInterface) exitWith {};

// Types de captcha troll
private _captchaTypes = [
    "age_verification",
    "robot_check",
    "math_impossible",
    "click_traffic_lights",
    "terms_conditions",
    "security_questions",
    "slide_puzzle"
];

private _selectedType = selectRandom _captchaTypes;
private _trollData = createHashMapFromArray [["type", _selectedType]];

switch (_selectedType) do {
    case "age_verification": {
        private _year = 2026 - (random [18, 35, 80] floor);
        private _month = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"] select (floor random 12);
        private _day = floor random 28 + 1;
        
        _trollData set ["title", "Vérification de l'âge requise"];
        _trollData set ["message", format ["Pour des raisons de sécurité, veuillez confirmer votre date de naissance :<br/><br/>Né(e) le : <t color='#5a9e88'>%1 %2 %3</t><br/><br/>Cette information sera utilisée conformément à notre politique de confidentialité (2847 pages).", _day, _month, _year]];
        _trollData set ["buttons", ["Oui, c'est exact", "Non, ce n'est pas ma date de naissance", "Je ne me souviens plus"]];
        _trollData set ["correct", 0];
    };
    
    case "robot_check": {
        private _questions = [
            ["Êtes-vous un robot ?", ["Oui", "Non", "Peut-être", "Je ne suis pas sûr"], 1],
            ["Cochez TOUTES les cases qui ne contiennent PAS de véhicule militaire", ["☐ Tank", "☐ Hélicoptère", "☐ Bouteille d'eau", "☐ APC"], 2],
            ["Combien font 2+2 en base 10 ?", ["4", "22", "Poisson", "La réponse D"], 0],
            ["Tapez le mot 'ATAK' sans faute", ["ATAK", "ATAC", "AATK", "Je ne sais pas"], 0]
        ];
        
        private _selected = selectRandom _questions;
        _trollData set ["title", "⚠ Test Anti-Robot Obligatoire ⚠"];
        _trollData set ["message", format ["<t align='center'>%1</t>", _selected select 0]];
        _trollData set ["buttons", _selected select 1];
        _trollData set ["correct", _selected select 2];
    };
    
    case "math_impossible": {
        private _problems = [
            ["Résolvez cette équation pour continuer :<br/><br/><t font='EtelkaMonospacePro' size='0.7'>∫₀^∞ e^(-x²) dx = ?</t>", ["√π/2", "π", "e", "42"], 0],
            ["Calculez rapidement :<br/><br/><t size='1.2'>log₂(1024) × sin(90°) = ?</t>", ["10", "1024", "0", "∞"], 0],
            ["Question simple :<br/><br/>Si un train roule à 80 km/h et qu'il est 14h37, de quelle couleur est le cheval blanc d'Henri IV ?", ["Blanc", "Noir", "Gris", "Henri IV n'avait pas de cheval"], 0]
        ];
        
        private _selected = selectRandom _problems;
        _trollData set ["title", "Vérification Cognitive Avancée"];
        _trollData set ["message", _selected select 0];
        _trollData set ["buttons", _selected select 1];
        _trollData set ["correct", _selected select 2];
    };
    
    case "click_traffic_lights": {
        _trollData set ["title", "Sélection d'Images de Sécurité"];
        _trollData set ["message", "<t align='center'>Cliquez sur TOUTES les cases contenant un<br/><t size='1.2' color='#5a9e88'>FEU TRICOLORE</t><br/><br/>[🚗] [🚦] [🏠]<br/>[🌳] [🚦] [☁️]<br/>[🚙] [🏢] [🚦]</t><br/><br/><t size='0.6' color='#888888'>Si aucune case ne correspond, cliquez sur 'Passer'</t>"];
        _trollData set ["buttons", ["Case 1", "Case 2", "Case 3", "Case 4", "Case 5", "Case 6", "Case 7", "Case 8", "Case 9", "Passer"]];
        _trollData set ["correct", 1]; // 2, 5, 9
    };
    
    case "terms_conditions": {
        _trollData set ["title", "Conditions Générales d'Utilisation"];
        _trollData set ["message", "<t size='0.55'>Article 1.2.3.b - L'utilisateur reconnaît avoir lu et accepté l'intégralité des 847 pages de conditions générales, incluant mais ne se limitant pas aux clauses 12.4.7.a concernant la collecte de données biométriques, la revente de son âme à des tiers non spécifiés, et l'utilisation de ses données de géolocalisation à des fins publicitaires ciblées. En cochant cette case, l'utilisateur renonce définitivement à tout recours juridique et accepte de recevoir 450 emails promotionnels par jour. Clause additionnelle 89.2.1 : en cas de litige, la juridiction compétente sera celle de l'île de Pâques.</t><br/><br/><t align='center' size='0.8'>Avez-vous lu INTÉGRALEMENT les conditions ci-dessus ?</t>"];
        _trollData set ["buttons", ["J'ai tout lu et j'accepte", "Je n'ai pas lu mais j'accepte quand même", "Défiler jusqu'en bas puis accepter"]];
        _trollData set ["correct", 1];
    };
    
    case "security_questions": {
        private _questions = [
            ["Question de sécurité #1 :<br/><br/>Quel est le nom de jeune fille de la mère de votre arrière-grand-père paternel ?", ["Schmidt", "Je ne sais pas", "Question suivante", "Passer"], 1],
            ["Question de sécurité #2 :<br/><br/>Combien de fenêtres aviez-vous dans votre maison quand vous aviez 7 ans ?", ["Entre 5 et 10", "Entre 10 et 20", "Je n'ai jamais eu 7 ans", "42"], 0],
            ["Question de sécurité #3 :<br/><br/>Quelle est la couleur préférée de votre animal de compagnie imaginaire ?", ["Bleu", "Rouge", "Il n'existe pas", "Transparent"], 2]
        ];
        
        private _selected = selectRandom _questions;
        _trollData set ["title", "Questions de Sécurité Obligatoires"];
        _trollData set ["message", _selected select 0];
        _trollData set ["buttons", _selected select 1];
        _trollData set ["correct", _selected select 2];
    };
    
    case "slide_puzzle": {
        _trollData set ["title", "Puzzle de Vérification"];
        _trollData set ["message", "<t align='center'>Faites glisser les pièces pour reconstituer l'image<br/><br/><t font='EtelkaMonospacePro' size='1.5'>[3][1][2]<br/>[6][4][5]<br/>[8][7][_]</t><br/><br/><t size='0.6' color='#888888'>Astuce : la case vide doit être en bas à droite</t></t>"];
        _trollData set ["buttons", ["Déplacer 7", "Déplacer 8", "Réinitialiser", "C'est bon !"]];
        _trollData set ["correct", 3];
    };
};

// Stocker les données du captcha actuel
missionNamespace setVariable ["COMSPEC_TrollCaptchaData", _trollData];

// Fermer le Hub temporairement
closeDialog 9969;

// Attendre un frame puis ouvrir le dialog captcha
[{
    createDialog "COMSPEC_TrollCaptcha_Dialog";
}] call CBA_fnc_execNextFrame;

_trollData
