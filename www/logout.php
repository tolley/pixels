<?php
require_once 'jwt.php';

jwtClearCookie();

header('Location: login');
exit;
