<?php
// register.php ist deaktiviert (Lastenheft §2.2: Patienten werden von Hand angelegt).
// Direkte URL-Zugriffe werden zur Login-Seite weitergeleitet.
header("Location: login.php");
exit();
