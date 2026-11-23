# Signer les DLL du mod ATAK natif

Les deux DLL d'extension (`COMSPECATAKNativeExtension_x64.dll`, `COMSPECExtension_x64.dll`) sont des binaires .NET compilés en natif. Non signées, elles sont une cause probable du signalement antivirus de l'item Workshop. Une signature Authenticode avec un certificat de signature de code reconnu est la mesure qui réduit le plus les faux positifs (et les avertissements SmartScreen). Un certificat **auto-signé** n'y change rien.

## Avant de commencer

- Un certificat de signature de code (OV ou EV), en fichier `.pfx` ou sur un jeton matériel / dans le magasin Windows.
- Le Kit de développement Windows (composant « Outils de signature ») pour `signtool.exe`.
- Le certificat et son mot de passe ne vont **jamais** dans le dépôt (public) ni dans une conversation.

## Étapes

1. Compiler les deux DLL (les sources de compilation ne sont pas dans ce dépôt).
2. Signer, depuis la racine du dépôt :

   ```powershell
   # Fichier PFX : le mot de passe passe par une variable d'environnement, jamais en argument.
   $env:COMSPEC_SIGN_PFX_PASSWORD = '…'        # saisi à la main, pour cette session de terminal seulement
   .\tools\sign-mod-dlls.ps1 -Certificate D:\secrets\comspec-codesign.pfx

   # Ou certificat du magasin Windows / jeton EV :
   .\tools\sign-mod-dlls.ps1 -Thumbprint 0123456789ABCDEF0123456789ABCDEF01234567
   ```

   Le script signe en SHA-256 avec horodatage (la signature reste valable après l'expiration du certificat), puis vérifie chaque signature et affiche l'empreinte SHA-256 finale.
3. Mettre à jour `docs/securite/empreintes-atak-natif.md` (la signature change l'empreinte des DLL).
4. Construire l'archive, la publier sur `/modpacks` : la page affiche l'empreinte de l'archive.
5. Demander la revue de l'item Workshop en indiquant que les extensions sont maintenant signées, et soumettre l'ancienne empreinte comme faux positif aux éditeurs qui la détectent (voir VirusTotal).

## À savoir

- Les `.bisign` des PBO (signatures Arma 3) sont une autre signature : elles ne remplacent pas Authenticode.
- Ne signez pas à la main un fichier déjà signé par erreur : relancez la compilation pour repartir d'une DLL propre.
- Avec un fichier PFX, `signtool` reçoit le mot de passe en argument (visible un instant dans la liste des processus de votre poste) : préférez importer le PFX dans le magasin Windows de votre compte, puis `-Thumbprint`.
- Si le certificat est sur un jeton matériel, `signtool` demande le code PIN à chaque exécution : c'est normal.

## Certificat auto-signé (essai, usage interne)

`New-SelfSignedCertificate -Type CodeSigningCert -Subject 'CN=COMSPEC' -CertStoreLocation Cert:\CurrentUser\My -NotAfter (Get-Date).AddYears(3)` crée en quelques secondes un certificat gratuit, puis `.	ools\sign-mod-dlls.ps1 -Thumbprint <empreinte> -AllowUntrusted` signe les DLL.

Ce que cela change : l'intégrité (toute modification du fichier invalide la signature) et l'identité affichée dans les propriétés du fichier. Ce que cela ne change **pas** : les antivirus et Steam ne traitent pas un certificat auto-signé comme un éditeur reconnu, donc il ne réduit pas les faux positifs ; Windows affiche « éditeur inconnu » ailleurs que sur le poste qui a créé le certificat, sauf à y installer la racine manuellement. Une signature ne survit pas à une recompilation : elle est à refaire à chaque build.

Pour retirer un certificat d'essai du magasin : `Remove-Item Cert:\CurrentUser\My\<empreinte>`.
