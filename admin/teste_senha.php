<?php
$hash = '$2y$10$Z1pEi7kl0vKMmNu838Kln.O2StMctBjpdWrFo7r0RBWbFIcAr.C/2';

echo password_verify("Caju2310%", $hash) ? "Senha correta" : "Senha incorreta";