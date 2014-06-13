<?

require_once('FrontDoor.php');

$frontdoor = new FrontDoorContoller();
$frontdoor->run_control_loop();           // this never returns

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
