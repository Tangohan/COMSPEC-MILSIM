@echo off
setlocal
:: Pack secondaire @COMSPEC_ATAK_Photo : fonds PHOTO / SAT du telephone, a charger en plus de @COMSPEC_ATAK_Native
:: (sans lui le telephone garde la carte d'Arma). A lancer apres une capture de la carte en jeu (module Zeus), Arma ferme.
::   1. regroupe les cmap_* de TOUS les dossiers Screenshots d'Arma (profil par defaut, autres profils, OneDrive) dans
::      la bibliotheque, en WebP sans perte (pixels verifies), avec les lignes CMAP des journaux, puis vide Screenshots ;
::   2. assemble le fond a niveaux de zoom (raccords fondus, exposition egalisee, voile retire, nettete) ;
::   3. fabrique le pack (une PBO par carte ; les cartes deja dans le pack sont gardees).
:: Dossier de travail : %USERPROFILE%\Documents\COMSPEC_cartes (ou variable COMSPEC_CARTES).
:: Options passees telles quelles a tools\map_stitch.py, ex. :
::   build_photo_pack.bat --world Altis
::   build_photo_pack.bat --addon-max-mb 600
::   build_photo_pack.bat --upload https://athena-comspec.eu/public --key <X_COMSPEC_KEY>   (envoie aussi le fond au site)
set "ROOT=%~dp0"
set "BASE=%USERPROFILE%\Documents\COMSPEC_cartes"
if defined COMSPEC_CARTES set "BASE=%COMSPEC_CARTES%"
set "PY="
py -3 -c "import sys" >nul 2>nul && set "PY=py -3"
if not defined PY python -c "import sys; sys.exit(sys.version_info[0] < 3)" >nul 2>nul && set "PY=python"
if not defined PY (echo [ERREUR] Python 3 introuvable. & exit /b 1)
%PY% -c "import PIL, numpy" >nul 2>nul || (echo [ERREUR] Il manque Pillow ou numpy : %PY% -m pip install pillow numpy & exit /b 1)
:: PAA (DXT1/DXT5) avec HEMTT 1.15 ou plus (PATH ou variable HEMTT), sinon tuiles JPG.
%PY% "%ROOT%tools\map_stitch.py" auto "%BASE%\fonds" --collect "%BASE%\captures" --move --pyramid --no-mosaic --addon "%BASE%" %*
if errorlevel 1 exit /b 1
echo [OK] Pack : %BASE%\@COMSPEC_ATAK_Photo (contenu dans cartes.txt)
echo      Captures regroupees : %BASE%\captures
endlocal
