-- Retour arrière de bootstrap/sse_member_image_grants_migration.php (étape sse_member_image_grants). À lancer à la main :
-- efface les demandes d'accès et dérogations aux images confidentielles des membres. Les images, identités, papiers et le
-- journal d'audit SSE ne sont pas touchés ; les images des membres restent masquées pour qui n'est ni titulaire, ni supérieur,
-- ni détenteur du droit « Identités de service ».
DROP TABLE IF EXISTS sse_member_image_grants;
