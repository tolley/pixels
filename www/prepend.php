<?php

function debug() {
	$args = func_get_args();

	echo '<pre>'; print_r( $args ); echo '</pre>';

	$trace = debug_backtrace();
	$level = array_shift( $trace );

	foreach( $args as $name => $value ) {
		echo '<pre title="debug() called at ' . $level['file'] . ':' . $level['line'] . '">';
		print_r( $value );
		echo '</pre>';
	}
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


// Prints out the line number and dies 
function dine()
{
	$trace = debug_backtrace();
	$level = array_shift( $trace );
	die( 'died: ' . $level['file'] . ': ' . $level['line'] );
}
