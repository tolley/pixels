<?php
require_once( 'functions.php' );
require_once( 'jwt.php' );
require_once( 'db.php' );

$user = jwtFromRequest();
if( !$user ) {
    header('Location: login');
    exit;
}

$bIEA = isEmailAllowed( $user['username'] );

// 0 to 1, 1 to 0
$ieaParam = ( $bIEA )? 0: 1;

// Update the user's email_allowed setting
$pdo = getDb();

$arr = [
    ':iea' => $ieaParam, 
    ':username' => $user['username']
];

$stmt = $pdo->prepare( 'UPDATE users set email_allowed = :iea WHERE username = :username' );
$stmt->execute( $arr );

echo $ieaParam;

