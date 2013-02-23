<?php
gc_enable(); //Garbage Collector an

function __autoload($class) {
	global $dir;

	$class = strtolower( $class ); //Ist schneller
	
	if (is_readable('inc/class.'. $class .'.php')) {
		include_once 'inc/class.'. $class .'.php';
	}
	elseif (is_readable('inc/interfaces/interface.'. $class .'.php')) {
		include_once 'inc/interfaces/interface.'. $class .'.php';
	}
	elseif (is_readable('inc/io/class.'. $class .'.php')) {
		include_once 'inc/io/class.'. $class .'.php';
	}
	elseif (is_readable('inc/env/class.'. $class .'.php')) {
		include_once 'inc/env/class.'. $class .'.php';
	}
	elseif (is_readable('inc/pcm/class.'. $class .'.php')) {
		include_once 'inc/pcm/class.'. $class .'.php';
	} 
	elseif (is_readable('inc/helper/class.'. $class .'.php')) {
		include_once 'inc/helper/class.'. $class .'.php';
	}

	else {
		Log::fatal( "Class ". $class ." not found" );
	}
}

include 'config.php';


/** Errorhanler starten **/
$handler = new ErrorHandler;

/*
 * Eigentliche Arbeit
 */
while (true) {
	try {
		/*
		 * Laden der Umgebung
		 */
		if (file_exists("inc/env/class.env".strtolower(PHP_OS).".php")) {
			include_once 'inc/env/class.env'.strtolower(PHP_OS).'.php';
			ENV::init();
		}
		else {
			Log::fatal("Environment not found");
		}
		/*
		 * Laden des Managers
		 */
		Manager::init();
		Log::info("Manager loaded successfully");
		
		while(true) {
		    Manager::tick();
		    usleep(0.5*1000000);
		}
	}
	
	catch (Exception $e) {
		switch( true ) {
			case ( $e instanceof FatalException ): 
				echo $e->getMessage();
				sleep( 120 ); //Wenn ein Fataler Fehler auftritt wartet er 2 Minuten und probiert es nochmals
				break;
			default:
				echo $e->getMessage();
				sleep(60);
		}
	}
}

?>