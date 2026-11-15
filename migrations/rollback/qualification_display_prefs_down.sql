-- Retour arrière de migrations/2026_11_20_100_qualification_display_prefs.sql (étape qualification_display_prefs). À lancer à la
-- main : efface les choix d'affichage des qualifications (visible sur Arma, visible sur le profil). Toutes les qualifications
-- redeviennent visibles aux deux endroits ; les qualifications elles-mêmes et le journal d'audit ne sont pas touchés.
DROP TABLE IF EXISTS personnel_qualification_display;
