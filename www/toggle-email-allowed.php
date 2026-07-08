<?php
require_once( 'functions.php' );
require_once( 'jwt.php' );
require_once( 'db.php' );

$user = jwtFromRequest();
if (!$user) {
    header('Location: login');
    exit;
}
$verified = isset( $_GET['verified'] );

$bIEA = isEmailAllowed( $user['username'] );

$ieaParam = ( $bIEA )? '0': '1';

// Update the user's email_allowed setting
$pdo = getDb();

$arr = [
    ':iea' => $ieaParam, 
    ':username' => $user['username']
];

$stmt = $pdo->prepare( 'UPDATE users set email_allowed = :iea WHERE username = :username' );
$stmt->execute( $arr );

$results = $stmt->fetchAll( PDO::FETCH_ASSOC );

echo $ieaParam;

