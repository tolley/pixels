<?php
require_once 'jwt.php';
jwtClearCookie();
header('Location: login.php');
exit;
