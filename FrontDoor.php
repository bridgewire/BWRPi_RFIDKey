<?

require_once('RPiGPIO.class.php');
//require_once('rfid_key.class.php');
require_once('RFIDResource.php');
//require_once('php_serial.class.php');

class FrontDoorLock extends RPiGPIO
{
  protected $unlock_duration = 10; // seconds

  const GPIO_OPENDOOR = 1;
  const GPIO_LOCKDOOR = 0;

  protected $locked = true;
  protected $lock_EvTimer = null;

  public function __construct( $gpio_pin = 2 )
  {
    parent::__construct(  $gpio_pin, "out"  );
    $this->export();
    $this->lock_door();

  }

  public function is_locked() { return $this->locked; }

  public function unlock_door( $unlock_seconds_duration = null )
  {
    if( $unlock_seconds_duration === null )
      $unlock_seconds_duration = $this->unlock_duration;

    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: running unlock door' );
    $this->write_value( self::GPIO_OPENDOOR );
    $this->locked = false;
    $this->lock_EvTimer = new EvTimer( $unlock_seconds_duration, 0, function($tmr) { $tmr->data->lock_door(); }, $this );
  }

  public function lock_door()
  {
    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: running lock door' );
    $this->write_value( self::GPIO_LOCKDOOR );
    $this->locked = true;
  }
}

class FrontDoorContoller implements RFIDResourceController
{
  const CTRLSTATE_NOMINAL = 0;
  const CTRLSTATE_ADMIN   = 1; // allow key-override control through RFID reader.

  protected $controller_state = self::CTRLSTATE_NOMINAL;
  protected $lock;

  public function __construct()
  {
    $this->fdlock = new FrontDoorLock();       // this is a GPIO
    $this->rfidrd = new RFID_Reader( $this );  // this contains a ttyS*-reading handler
  }


  public function run_contol_loop()
  {
    $this->rfidrd->add_RFID_read_handler();
    Ev::run();                                 // Ev::run() never returns.
  }


  public function receive_rfid_event( RFIDEvent $e )
  {
    $fid = 'STUB: '.__CLASS__.'::'.__FUNCTION__;
	  if( ! $e->key_is_valid() ) // this means the key is useable, not that it's known.

    {
      error_log($fid.' received badly-formed key');
/*
                $crtra = new door_lock_criteria();
                $crtra->set_key_is_invalid( "invalid key: $key" );
                $crtra->log_event();

                $key = null;
*/
    }
    else
    {
      error_log($fid.' received well-formed key');

      switch( $this->controller_state )
      {
      case self::CTRLSTATE_NOMINAL:
        $this->fdlock->unlock_door();
        break;
      case self::CTRLSTATE_ADMIN:  // this is worth while?  too much trouble?
        break;
      }

/*
      $found = $k->lookup_rfid();

      $m = new bw_member( $k );

      // '$io->data' is 'this', an instance of 'RFID_Reader'
      $io->data->open_door_if_member_allowed( $m ); // this function logs the event

      $key = null;
*/
    }
  }

/*
  public function open_door_if_member_allowed( $mmbr )
  {
    $crtra = new door_lock_criteria();
    $crtra->check_members_access( $mmbr );

    if( $crtra->passes_unlock_criteria() )
      $this->unlock_door();

    $crtra->log_event();
  }
*/

}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
