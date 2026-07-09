<?php
/**
 * The end point for /emails_allowed.  Returns 1 if the user
 * has emails allowed, 0 otherwise
 */

require_once( 'jwt.php' );
require_once( 'functions.php' );

$user = jwtFromRequest();
if( !$user ) {
    header('Location: login');
    exit;
}

if( !$user ) {
    echo 0;
    return;
}

echo isEmailAllowed( $user['username'] );
