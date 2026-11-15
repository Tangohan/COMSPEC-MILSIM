<?php
declare(strict_types=1);
/**
 * Cadre « CONFIDENTIEL ÉLEVÉ » : remplace l'image d'un membre que le lecteur n'a pas le droit de voir. Aucune image, aucun chemin, aucun
 * flou : seulement un cadre de même taille que la vignette (il se place dans le conteneur de l'image, qui garde son ratio).
 *
 * @var array<string, mixed> $lock voir SseMemberImageGate::lockInfo
 */
?>
<span class="fx-lk" role="img" aria-label="Image masquée : confidentiel élevé"><svg class="i" aria-hidden="true"><use href="#fx-lock"></use></svg><b>CONFIDENTIEL ÉLEVÉ</b></span>
