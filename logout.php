<?php
session_start();
// Destruimos todas las variables de sesión creadas (el gafete digital)
session_destroy();
// Redirigimos al usuario de regreso a la pantalla de login
header("Location: login.php");
exit();
?>