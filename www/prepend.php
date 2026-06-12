<?php

function debug( $var ) {
    echo '<pre>';
    print_r( $var );
    echo '</pre>';
}

function dd( $var ) {
    debug( $var );
    die( 'died in dd()' );
}

/**
 * Prints out the file name and line number on which this function was called
 */
function here()
{
	$trace = debug_backtrace();
	$level = array_shift( $trace );
	debug( 'Here: ' . $level['file'] . ': ' . $level['line'] );
}
