"""Lance les essais de logique (tests/Frontend/*.test.cjs) dans le navigateur, pour les postes sans Node."""
from _base import BASE, Run

r = Run('logique JS')
b = r.b
b.open(BASE + '/tests/Frontend/run-in-browser.html', 'document.title !== "essais…"')
title = r.js('document.title')
text = r.js('document.getElementById("out").textContent')
for line in text.split('\n'):
    if line.startswith('FAIL') or line.startswith('     ') or line.startswith('ERREUR'):
        print(' ', line)
r.check(title.startswith('OK '), 'essais de logique : ' + title)
raise SystemExit(r.done())
