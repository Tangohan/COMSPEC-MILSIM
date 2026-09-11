<section id="depannage" class="site-docs__section">
    <h2>Dépannage</h2>
    <p class="site-docs__lead">
        Symptômes fréquents et pistes de résolution — sans jargon technique.
    </p>

    <table class="site-docs__table">
        <thead>
            <tr><th>Symptôme</th><th>Pistes</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>« Hors liaison » permanent</td>
                <td>Nouveau code Appairer ; Steam sur le compte ; quitter Arma complètement ; pack à jour ; clé communauté générée côté admin</td>
            </tr>
            <tr>
                <td>Position absente sur la carte</td>
                <td>Canal poste ouvert (Athena / Entrer) ; bougez un peu ; attendre jusqu’à une minute ; indicatif renseigné</td>
            </tr>
            <tr>
                <td>Hub sans overlays réalisme</td>
                <td>Roleplay désactivé côté communauté — normal si OP « arcade »</td>
            </tr>
            <tr>
                <td>Marqueurs absents côté web</td>
                <td>Attendre ~30 s après spawn ; éviter marqueurs en 0,0</td>
            </tr>
            <tr>
                <td>Rapport non reçu</td>
                <td>Badge liaison ; écran endommagé (position seule) ; zone sans couverture</td>
            </tr>
            <tr>
                <td>Mod absent au lancement</td>
                <td>CBA avant Overwatch ; redémarrer le lanceur après mise à jour</td>
            </tr>
            <tr>
                <td>Crash puis indicatif perdu</td>
                <td>Reconnecter dans les ~10 min — reprise session portail</td>
            </tr>
        </tbody>
    </table>

    <h3>Escalade</h3>
    <p>
        Si le problème persiste après ces vérifications, transmettez à votre référent ATAK :
        version du pack, heure approximative, indicatif, carte jouée, et une capture du badge liaison si possible.
    </p>

    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Formation.</strong> Reprenez le
        <a href="<?= htmlspecialchars((string) ($atakFormationUrl ?? url('atak/mod/formation')), ENT_QUOTES, 'UTF-8') ?>">parcours de formation</a>
        module par module — la plupart des blocages viennent d’une étape de liaison ou d’indicatif non validée.
    </div>
</section>
