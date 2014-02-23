<?

require_once('FrontDoor.php');

if( preg_grep( '/^(--daemonize|-d)$/', $argv ) )
  error_log('debug: theoretically we daemonize here.');


$frontdoor = new FrontDoorContoller();
$frontdoor->run_contol_loop();           // this never returns

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
