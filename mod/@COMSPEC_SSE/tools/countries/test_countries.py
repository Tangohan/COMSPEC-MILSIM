#!/usr/bin/env python3
"""Essais hors jeu du registre des pays et des numéros (sans Arma) : python -I tools/countries/test_countries.py

- chiffres de contrôle ICAO sur les spécimens publiés de la norme 9303 (passeport TD3 et carte TD1 « UTO ») ;
- clés des numéros nationaux sur des exemples publics connus (NIR, DNI, NIE, PESEL, BSN, AVS, SVNR, codice fiscale) ;
- 200 tirages par schéma : chaque numéro tiré passe son validateur ; MRZ TD1 (numéro long en débordement) et TD3 relues ;
- registre : countries.json valide, CfgComspecCountries.hpp et examples.json à jour.
"""
import os
import random
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import sse_numbers as N  # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")

FAILS = []


def ok(cond, what):
    if not cond:
        FAILS.append(what)


# --- ICAO 9303, spécimens publiés (partie 4 : TD3 ; partie 5 : TD1) ---------------------------------------------------
TD3 = ["P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<", "L898902C36UTO7408122F1204159ZE184226B<<<<<10"]
TD1 = ["I<UTOD231458907<<<<<<<<<<<<<<<", "7408122F1204159UTO<<<<<<<<<<<6", "ERIKSSON<<ANNA<MARIA<<<<<<<<<<"]
ok(N.icao("L898902C3") == 6 and N.icao("740812") == 2 and N.icao("120415") == 9, "ICAO : chiffres du spécimen TD3")
ok(N.mrz_ok(TD3), "ICAO : spécimen TD3 relu")
ok(N.mrz_ok(TD1), "ICAO : spécimen TD1 relu")
built = N.mrz_td3({"issuer": "UTO", "nat": "UTO", "last": "Eriksson", "first": "Anna Maria", "number": "L898902C3", "dob": "1974-08-12",
                   "sex": "F", "expires": "2012-04-15", "personal": "ZE184226B"})
ok(built == TD3, "ICAO : TD3 réécrit à l'identique du spécimen")
built1 = N.mrz_td1({"code": "I<", "issuer": "UTO", "nat": "UTO", "last": "Eriksson", "first": "Anna Maria", "number": "D23145890",
                    "dob": "1974-08-12", "sex": "F", "expires": "2012-04-15"})
ok(built1 == TD1, "ICAO : TD1 réécrit à l'identique du spécimen")
ok(N.mrz_td1({"code": "ID", "issuer": "D", "nat": "D", "last": "Müller", "first": "Jürgen", "number": "L01X00T47", "dob": "1964-08-12",
              "sex": "M", "expires": "2030-10-31"})[0].startswith("IDD<<L01X00T47"), "Allemagne : « IDD<< »")

# --- Exemples publics connus ------------------------------------------------------------------------------------------
ok(N.es_dni_ok("12345678Z"), "DNI 12345678Z")
ok(N.es_nie_ok("X1234567L"), "NIE X1234567L")
ok(N.pl_pesel_ok("44051401359"), "PESEL 44051401359")
ok(N.nl_bsn_ok("111222333"), "BSN 111222333")
ok(N.ch_ahv_ok("756.9217.0769.85"), "AVS 756.9217.0769.85")
ok(N.at_svnr_ok("1237 010180"), "SVNR 1237 010180")
ok(N.it_cf_ok("RSSMRA85T10A562S"), "codice fiscale RSSMRA85T10A562S")
ok(N.pl_idcard_ok("ABA300000"), "dowód ABA300000")
ok(N.vin_check("1M8GDM9AXKP042788") == "X", "VIN 1M8GDM9AXKP042788")
ok(not N.es_dni_ok("12345678A"), "DNI faux refusé")
ok(not N.nl_bsn_ok("111222334"), "BSN faux refusé")

# --- Tirages : chaque schéma passe son validateur ----------------------------------------------------------------------
rnd = random.Random(7)
for name, (gen, chk) in N.SCHEMES.items():
    for i in range(200):
        ctx = {"sex": "F" if i % 2 else "M", "dob": "%04d-%02d-%02d" % (rnd.randrange(1950, 2008), rnd.randrange(1, 13), rnd.randrange(1, 29)),
               "last": rnd.choice(["Dupont", "Müller", "O'Neil", "Ng", "Rossi", "Øster"]), "first": rnd.choice(["Anne", "Jürgen", "Li", "Maria"]),
               "pob": rnd.choice(["Roma", "Paris"]), "region": rnd.choice(["75", "2A", "", "13"])}
        v = gen(rnd, ctx)
        if not chk(v):
            FAILS.append(f"{name} : {v} refusé par son validateur")
            break
    else:
        continue
ok(all(N.fr_nir(rnd, {"sex": "M", "dob": "1985-05-17", "region": "75"}).split()[5] == "000" for _ in range(20)), "NIR : rang 000 (fictif)")
ok(all(N.us_ssn(rnd, {}).startswith("9") for _ in range(50)), "SSN : zone 9xx (jamais attribuée)")
for _ in range(200):
    num = N.pattern("############", rnd)
    lines = N.mrz_td1({"code": "ID", "issuer": "BEL", "nat": "BEL", "last": "Peeters", "first": "Lotte", "number": num, "dob": "1990-02-03",
                       "sex": "F", "expires": "2030-02-03", "optional": ""})
    if not N.mrz_ok(lines) or any(len(x) != 30 for x in lines):
        FAILS.append("TD1 numéro long : " + " / ".join(lines))
        break
    v = N.vin(rnd)
    if N.vin_check(v) != v[8]:
        FAILS.append("VIN " + v)
        break

# --- Registre à jour ---------------------------------------------------------------------------------------------------
r = subprocess.run([sys.executable, "-I", os.path.join(HERE, "gen_countries.py"), "--check"], capture_output=True, text=True, encoding="utf-8", errors="replace")
ok(r.returncode == 0, "registre : " + (r.stdout + r.stderr).strip())

if FAILS:
    print("ÉCHECS :\n  " + "\n  ".join(FAILS))
    sys.exit(1)
print("Pays et numéros : tout passe.")
