"""Lance tous les scénarios de l'éditeur de plans l'un après l'autre et rend le bilan.

    php -d extension=pdo_sqlite -S 127.0.0.1:8790 -t . tests/Frontend/fixtures/plan-backend.php    (dans un autre terminal)
    python tests/Frontend/scenarios/tout.py [dossier des captures]

Les scénarios partagent la base du banc : ils ne se lancent jamais en parallèle sur le même port.
"""
import os
import subprocess
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
NAMES = ['logique', 'edition', 'disposition', 'marqueurs', 'temps_3d', 'fichiers_acces', 'tutoriel']
out = sys.argv[1:2]
env = dict(os.environ, PYTHONIOENCODING='utf-8')
bad = 0
for name in NAMES:
    t = time.time()
    p = subprocess.run([sys.executable, '-u', os.path.join(HERE, name + '.py')] + out, capture_output=True, text=True, encoding='utf-8', errors='replace', env=env)
    lines = [l for l in (p.stdout + p.stderr).strip().split('\n') if l.strip()]
    last = lines[-1] if lines else '(aucune sortie)'
    for l in lines:
        if 'ÉCHEC' in l or 'ANOMALIE' in l or 'Error' in l:
            print('   ', l.strip()[:300])
    print('%-16s %s  (%.0f s, code %d)' % (name, last.strip()[:200], time.time() - t, p.returncode), flush=True)
    bad += 1 if p.returncode else 0
print('BILAN : %d scénario(s) sur %d sans échec' % (len(NAMES) - bad, len(NAMES)))
sys.exit(1 if bad else 0)
