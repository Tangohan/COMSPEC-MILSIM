/*
    GÉNÉRÉ par tools/gen_id_real_countries.php depuis app/Support/SseIdRealCountryCatalog.php (site) : ne pas modifier
    à la main.
    Pays réels STYLISÉS, réservés à un univers « réaliste — simulation » : réglage CBA comspec_sse_id_realSim, ou pays
    importé par la communauté sur Athena (paquet, real_sim). Gabarit générique par zone de style (europe : bordeaux,
    americas : bleu, other : vert ou noir), mention SIMULATION — DOCUMENT DE JEU, aucun vrai modèle ni emblème copié ;
    numéros au format générique du jeu ; code MRZ = code OACI (Allemagne « D », imprimé « D<< »).
    [] call comspec_sse_fnc_idRealCountries -> HashMap code -> HashMap pays (mêmes clés que fn_idCountries, plus
    realSim, zone, nameNative, validity HashMap type -> années, lastFSuffix : nom au féminin, ex. Ivanov -> Ivanova)
*/
private _m = missionNamespace getVariable "comspec_sse_idRealBase";
if (!isNil "_m") exitWith { _m };
_m = createHashMap;
{
    _x params ["_code", "_mrz", "_name", "_nameEn", "_nameNative", "_fmt", "_auth", "_zone", "_langs", "_validity", "_colors",
        "_first", "_firstF", "_last", "_cities", "_fSuffix"];
    _m set [_code, createHashMapFromArray [
        ["code", _code], ["name", _name], ["nameEn", _nameEn], ["nameNative", _nameNative], ["mrz", _mrz],
        ["number_format", _fmt], ["authority", _auth], ["validity", createHashMapFromArray _validity], ["cities", _cities],
        ["chip", true], ["fictional", false], ["realSim", true], ["zone", _zone], ["theme", "realistic_sim"],
        ["languages", _langs], ["docTypes", _validity apply { _x select 0 }], ["colors", _colors], ["active", true],
        ["first", _first], ["firstF", _firstF], ["last", _last], ["lastFSuffix", _fSuffix]
    ]];
} forEach [
        ["GBR", "GBR", "Royaume-Uni", "United Kingdom", "United Kingdom", "GB@######", "United Kingdom — Home Office", "europe", ["en"], [["passport", 10], ["driving_license", 10]], ["#1C2541", "#C8102E"],
            ["Oliver", "George", "Harry", "Jack", "Thomas", "James", "William", "Charlie", "Daniel", "Samuel", "Joseph", "Matthew"],
            ["Olivia", "Amelia", "Emily", "Isla", "Sophie", "Charlotte", "Grace", "Lucy", "Hannah", "Ellie", "Chloe", "Jessica"],
            ["Smith", "Jones", "Taylor", "Brown", "Williams", "Wilson", "Johnson", "Davies", "Robinson", "Wright", "Thompson", "Evans", "Walker", "White", "Roberts", "Hughes"],
            ["London", "Manchester", "Birmingham", "Leeds", "Glasgow", "Liverpool", "Bristol", "Cardiff", "Edinburgh", "Belfast", "Sheffield", "Newcastle"], ""],
        ["GRC", "GRC", "Grèce", "Greece", "Ελλάδα", "GR@######", "Hellenic Republic — Ministry of Interior", "europe", ["el", "en"], [["id_card", 10], ["passport", 5], ["driving_license", 15]], ["#6D1A2B", "#0D5EAF"],
            ["Nikos", "Giorgos", "Dimitris", "Kostas", "Yannis", "Christos", "Panagiotis", "Vasilis", "Stavros", "Andreas", "Michalis", "Petros"],
            ["Maria", "Eleni", "Katerina", "Sofia", "Anna", "Georgia", "Despina", "Ioanna", "Dimitra", "Vasiliki", "Christina", "Angeliki"],
            ["Papadopoulos", "Georgiou", "Nikolaidis", "Konstantinou", "Dimitriou", "Papadakis", "Vlachos", "Karagiannis", "Oikonomou", "Makris", "Antoniou", "Stavrou", "Pappas", "Kyriakou", "Theodorou", "Mavridis"],
            ["Athina", "Thessaloniki", "Patra", "Irakleio", "Larisa", "Volos", "Ioannina", "Kavala", "Chania", "Kalamata", "Rodos", "Serres"], ""],
        ["USA", "USA", "États-Unis", "United States", "United States of America", "US@######", "United States — Department of State", "americas", ["en"], [["id_card", 8], ["passport", 10], ["driving_license", 8]], ["#1B2A5C", "#B22234"],
            ["Michael", "Christopher", "Joshua", "Matthew", "David", "Andrew", "Ryan", "Tyler", "Brandon", "Justin", "Kevin", "Jason"],
            ["Jessica", "Ashley", "Jennifer", "Amanda", "Sarah", "Megan", "Emily", "Lauren", "Rachel", "Nicole", "Samantha", "Brittany"],
            ["Smith", "Johnson", "Williams", "Brown", "Jones", "Miller", "Davis", "Garcia", "Rodriguez", "Wilson", "Martinez", "Anderson", "Taylor", "Thomas", "Moore", "Jackson"],
            ["New York", "Los Angeles", "Chicago", "Houston", "Phoenix", "San Antonio", "San Diego", "Dallas", "Denver", "Seattle", "Boston", "Atlanta"], ""],
        ["DEU", "D", "Allemagne", "Germany", "Deutschland", "DE@######", "Bundesrepublik Deutschland", "europe", ["de", "en", "fr"], [["id_card", 10], ["passport", 10], ["driving_license", 15]], ["#6D1A2B", "#FFCE00"],
            ["Lukas", "Leon", "Maximilian", "Felix", "Jonas", "Paul", "Tobias", "Jan", "Florian", "Sebastian", "Niklas", "Stefan"],
            ["Anna", "Laura", "Lena", "Julia", "Sarah", "Lisa", "Katharina", "Hannah", "Sophie", "Lea", "Johanna", "Miriam"],
            ["Müller", "Schmidt", "Schneider", "Fischer", "Weber", "Meyer", "Wagner", "Becker", "Schulz", "Hoffmann", "Koch", "Richter", "Klein", "Wolf", "Schröder", "Neumann"],
            ["Berlin", "Hamburg", "München", "Köln", "Frankfurt am Main", "Stuttgart", "Düsseldorf", "Leipzig", "Dortmund", "Bremen", "Dresden", "Hannover"], ""],
        ["AUT", "AUT", "Autriche", "Austria", "Österreich", "AT@######", "Republik Österreich", "europe", ["de", "en", "fr"], [["id_card", 10], ["passport", 10], ["driving_license", 15]], ["#6D1A2B", "#C8102E"],
            ["Lukas", "Florian", "David", "Simon", "Elias", "Fabian", "Matthias", "Markus", "Michael", "Alexander", "Johannes", "Andreas"],
            ["Anna", "Lena", "Sarah", "Katharina", "Julia", "Theresa", "Magdalena", "Elisabeth", "Sophie", "Verena", "Christina", "Barbara"],
            ["Gruber", "Huber", "Bauer", "Wagner", "Müller", "Pichler", "Steiner", "Moser", "Mayer", "Hofer", "Leitner", "Berger", "Fuchs", "Eder", "Fischer", "Schmid"],
            ["Wien", "Graz", "Linz", "Salzburg", "Innsbruck", "Klagenfurt", "Villach", "Wels", "Sankt Pölten", "Dornbirn", "Steyr", "Bregenz"], ""],
        ["COL", "COL", "Colombie", "Colombia", "Colombia", "CO@######", "República de Colombia — Ministerio del Interior", "americas", ["es", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 10]], ["#1B3A6B", "#FCD116"],
            ["Santiago", "Sebastián", "Andrés", "Carlos", "Juan", "Luis", "Jorge", "Diego", "Alejandro", "Camilo", "Felipe", "Mateo"],
            ["Valentina", "Daniela", "Camila", "María", "Laura", "Natalia", "Paola", "Carolina", "Andrea", "Juliana", "Sofía", "Catalina"],
            ["Rodríguez", "Gómez", "González", "Martínez", "García", "López", "Hernández", "Sánchez", "Ramírez", "Pérez", "Díaz", "Muñoz", "Rojas", "Moreno", "Jiménez", "Castro"],
            ["Bogotá", "Medellín", "Cali", "Barranquilla", "Cartagena", "Cúcuta", "Bucaramanga", "Pereira", "Santa Marta", "Ibagué", "Pasto", "Villavicencio"], ""],
        ["FRA", "FRA", "France", "France", "France", "FR@######", "République française — Ministère de l’Intérieur", "europe", ["fr", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 15]], ["#6D1A2B", "#0055A4"],
            ["Jean", "Pierre", "Louis", "Antoine", "Mathieu", "Nicolas", "Julien", "Olivier", "Thomas", "Laurent", "Étienne", "Rémi"],
            ["Marie", "Claire", "Camille", "Julie", "Élodie", "Sophie", "Anne", "Céline", "Manon", "Hélène", "Léa", "Chloé"],
            ["Martin", "Bernard", "Moreau", "Laurent", "Lefèvre", "Girard", "Rousseau", "Fournier", "Morel", "Guérin", "Mercier", "Blanc", "Faure", "André", "Chevalier", "Leroy"],
            ["Paris", "Lyon", "Marseille", "Toulouse", "Lille", "Bordeaux", "Nantes", "Strasbourg", "Rennes", "Montpellier", "Nice", "Dijon"], ""],
        ["BEL", "BEL", "Belgique", "Belgium", "België · Belgique · Belgien", "BE@######", "Royaume de Belgique — SPF Intérieur", "europe", ["fr", "nl", "de", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 10]], ["#6D1A2B", "#FDDA24"],
            ["Lucas", "Louis", "Noah", "Arthur", "Thomas", "Maxime", "Pieter", "Jens", "Wout", "Antoine", "Simon", "Kevin"],
            ["Emma", "Louise", "Julie", "Marie", "Laura", "Lotte", "Elise", "Charlotte", "Sarah", "Camille", "Lien", "Manon"],
            ["Peeters", "Janssens", "Maes", "Jacobs", "Mertens", "Willems", "Claes", "Goossens", "Wouters", "De Smet", "Dubois", "Lambert", "Dupont", "Martin", "Leroy", "Hermans"],
            ["Bruxelles", "Antwerpen", "Gent", "Charleroi", "Liège", "Brugge", "Namur", "Leuven", "Mons", "Aalst", "Mechelen", "Hasselt"], ""],
        ["ESP", "ESP", "Espagne", "Spain", "España", "ES@######", "Reino de España — Ministerio del Interior", "europe", ["es", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 10]], ["#6D1A2B", "#F1BF00"],
            ["Antonio", "Manuel", "José", "Francisco", "David", "Javier", "Daniel", "Carlos", "Alejandro", "Pablo", "Sergio", "Jorge"],
            ["María", "Carmen", "Lucía", "Laura", "Marta", "Ana", "Cristina", "Elena", "Sara", "Paula", "Isabel", "Raquel"],
            ["García", "Fernández", "González", "Rodríguez", "López", "Martínez", "Sánchez", "Pérez", "Gómez", "Martín", "Jiménez", "Ruiz", "Hernández", "Díaz", "Moreno", "Álvarez"],
            ["Madrid", "Barcelona", "Valencia", "Sevilla", "Zaragoza", "Málaga", "Murcia", "Palma", "Bilbao", "Alicante", "Córdoba", "Valladolid"], ""],
        ["POL", "POL", "Pologne", "Poland", "Polska", "PL@######", "Rzeczpospolita Polska — Ministry of Interior", "europe", ["pl", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 15]], ["#6D1A2B", "#DC143C"],
            ["Piotr", "Tomasz", "Krzysztof", "Paweł", "Michał", "Marek", "Jakub", "Andrzej", "Grzegorz", "Łukasz", "Wojciech", "Kamil"],
            ["Anna", "Katarzyna", "Magdalena", "Agnieszka", "Joanna", "Monika", "Ewa", "Zofia", "Małgorzata", "Natalia", "Aleksandra", "Karolina"],
            ["Nowak", "Wójcik", "Woźniak", "Mazur", "Krawczyk", "Kaczmarek", "Zając", "Król", "Wieczorek", "Wróbel", "Adamczyk", "Dudek", "Pawlak", "Walczak", "Michalak", "Sikora"],
            ["Warszawa", "Kraków", "Łódź", "Wrocław", "Poznań", "Gdańsk", "Szczecin", "Lublin", "Białystok", "Katowice", "Rzeszów", "Toruń"], ""],
        ["UKR", "UKR", "Ukraine", "Ukraine", "Україна", "UA@######", "Ukraine — Ministry of Interior", "other", ["uk", "en"], [["id_card", 10], ["passport", 10], ["driving_license", 10]], ["#1F4D3A", "#FFD700"],
            ["Oleksandr", "Andriy", "Dmytro", "Serhiy", "Mykola", "Volodymyr", "Yuriy", "Taras", "Bohdan", "Ihor", "Vasyl", "Maksym"],
            ["Olena", "Oksana", "Nataliya", "Iryna", "Tetiana", "Yuliya", "Kateryna", "Svitlana", "Halyna", "Mariya", "Anastasiya", "Viktoriya"],
            ["Melnyk", "Shevchenko", "Boyko", "Kovalenko", "Bondarenko", "Tkachenko", "Kovalchuk", "Kravchenko", "Oliynyk", "Shevchuk", "Polishchuk", "Lysenko", "Tkachuk", "Savchenko", "Rudenko", "Marchenko"],
            ["Kyiv", "Kharkiv", "Odesa", "Dnipro", "Lviv", "Zaporizhzhia", "Mykolaiv", "Vinnytsia", "Poltava", "Chernihiv", "Sumy", "Rivne"], ""],
        ["RUS", "RUS", "Russie", "Russia", "Россия", "RU@######", "Russian Federation — Ministry of Interior", "other", ["ru", "en"], [["id_card", 20], ["passport", 10], ["driving_license", 10]], ["#1B1B1B", "#D52B1E"],
            ["Ivan", "Dmitri", "Sergei", "Andrei", "Pavel", "Alexei", "Nikolai", "Viktor", "Mikhail", "Yuri", "Oleg", "Roman"],
            ["Anna", "Elena", "Olga", "Natalia", "Irina", "Maria", "Tatiana", "Svetlana", "Ekaterina", "Yulia", "Daria", "Ksenia"],
            ["Ivanov", "Petrov", "Smirnov", "Kuznetsov", "Popov", "Vasiliev", "Sokolov", "Mikhailov", "Novikov", "Fedorov", "Morozov", "Volkov", "Lebedev", "Kozlov", "Orlov", "Makarov"],
            ["Moskva", "Sankt-Peterburg", "Novosibirsk", "Yekaterinburg", "Kazan", "Nizhny Novgorod", "Samara", "Omsk", "Rostov-na-Donu", "Ufa", "Krasnoyarsk", "Volgograd"], "a"],
        ["IRQ", "IRQ", "Irak", "Iraq", "العراق", "IQ@######", "Republic of Iraq — Ministry of Interior", "other", ["ar", "en"], [["id_card", 10], ["passport", 8], ["driving_license", 10]], ["#1F4D3A", "#CE1126"],
            ["Ahmed", "Mohammed", "Ali", "Hussein", "Hassan", "Omar", "Mustafa", "Haider", "Abbas", "Karim", "Saad", "Yasir"],
            ["Fatima", "Zainab", "Maryam", "Noor", "Sara", "Huda", "Aisha", "Rana", "Shaima", "Iman", "Duaa", "Layla"],
            ["Al-Jubouri", "Al-Obaidi", "Al-Dulaimi", "Al-Tamimi", "Al-Khafaji", "Al-Saadi", "Al-Rubaie", "Al-Zubaidi", "Al-Hashimi", "Al-Shammari", "Al-Bayati", "Al-Ani", "Al-Kubaisi", "Al-Mousawi", "Al-Hamdani", "Al-Azzawi"],
            ["Baghdad", "Basra", "Mosul", "Erbil", "Kirkuk", "Najaf", "Karbala", "Sulaymaniyah", "Ramadi", "Fallujah", "Tikrit", "Samarra"], ""],
        ["SYR", "SYR", "Syrie", "Syria", "سوريا", "SY@######", "Syrian Arab Republic — Ministry of Interior", "other", ["ar", "en"], [["id_card", 10], ["passport", 6], ["driving_license", 10]], ["#1B1B1B", "#007A3D"],
            ["Mohammad", "Ahmad", "Khaled", "Omar", "Bilal", "Rami", "Fadi", "Bassam", "Ghassan", "Hassan", "Tarek", "Ziad"],
            ["Rania", "Lina", "Hala", "Reem", "Dima", "Nour", "Yara", "Rasha", "Salma", "Ghada", "Maha", "Joud"],
            ["Haddad", "Khoury", "Nasser", "Saleh", "Darwish", "Hamdan", "Jaber", "Najjar", "Sabbagh", "Halabi", "Shami", "Hourani", "Kassab", "Attar", "Masri", "Zein"],
            ["Dimashq", "Halab", "Homs", "Hama", "Latakia", "Tartus", "Deir ez-Zor", "Raqqa", "Idlib", "Daraa", "Al-Hasakah", "Qamishli"], ""],
        ["AFG", "AFG", "Afghanistan", "Afghanistan", "افغانستان", "AF@######", "Afghanistan — Ministry of Interior", "other", ["fa", "ps", "en"], [["id_card", 10], ["passport", 5], ["driving_license", 10]], ["#1B1B1B", "#007A36"],
            ["Ahmad", "Mohammad", "Abdul", "Najib", "Farid", "Hamid", "Rahim", "Karim", "Wahid", "Jawad", "Sami", "Nasir"],
            ["Fatima", "Mariam", "Zahra", "Fereshta", "Laila", "Nargis", "Shabnam", "Parwana", "Sima", "Roya", "Habiba", "Malalai"],
            ["Ahmadzai", "Popal", "Barakzai", "Zazai", "Stanikzai", "Noorzai", "Wardak", "Safi", "Mohammadi", "Rahimi", "Hakimi", "Sultani", "Nazari", "Haidari", "Amiri", "Qaderi"],
            ["Kabul", "Kandahar", "Herat", "Mazar-i-Sharif", "Jalalabad", "Kunduz", "Ghazni", "Lashkar Gah", "Bamyan", "Khost", "Gardez", "Taloqan"], ""]
];
missionNamespace setVariable ["comspec_sse_idRealBase", _m];
_m
