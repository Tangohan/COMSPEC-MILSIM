@echo off
REM Genere le dossier vendor pour upload sur le serveur.
REM Necessite : PHP et Composer dans le PATH (ou modifier les chemins ci-dessous).

cd /d "%~dp0"

if exist "composer.phar" (
    php composer.phar install --no-interaction
) else (
    composer install --no-interaction
)

if exist "vendor\autoload.php" (
    echo.
    echo [OK] vendor/ genere. Tu peux uploader tout le projet sur le serveur.
) else (
    echo.
    echo [ERREUR] composer install a echoue. Verifie que PHP et Composer sont installes et dans le PATH.
    pause
)
