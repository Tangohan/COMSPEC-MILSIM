<?php
// Redirection vers l'application (évite 403 sur la racine)
header('Location: public/', true, 302);
exit;
