<?

require_once('RPiGPIO.class.php');
require_once('RFIDResource.php');
require_once('bwdb_rowdefs.php'); // 


class FrontDoorLock extends RPiGPIO
{
  protected $unlock_duration = 7; // seconds

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

abstract class ResourceLockCriteria {}

class FrontDoorLockCriteria extends ResourceLockCriteria
{
  protected $key = null;

  public function __construct( rfid_key $rfid )
  {
    $this->key = $rfid;
  }

  public function should_open( &$ckr )
  {
    error_log( "running should_open() with key: ".$this->key->get_key() );

    $do_unlock     = false;

    $ckr = new cardkey_row( $this->key->get_key() );
    print "cardkey_row: $ckr";

    if( $ckr->found_in_db() )
    {
      $use_override  = false;
      $now_date      = new DateTime();

      $exp    = $ckr->g('expires');
      $or     = $ckr->g('override');
      $or_exp = $ckr->g('override_expires');

      // check override first. if override exists and isn't expired then then it is all that matters.
      if( $or !== null )
      {
        if( $or_exp !== null ) // null is treated as an expired override
        {
          $or_expire_date = new DateTime( $or_exp );

          if( $or_expire_date >= $now_date )
          {
            // not expired.  use the override
            $use_override = true;
            switch( $or )
            {
            case 'u': // unlock the door
              $do_unlock = true;
              break;
            case 'l': // keep the door locked
              $do_unlock = false;
              break;
            default:
              error_log('uknown override type: '.$or);
              $do_unlock = false;
              break;
            }
          }
        }
      }

      if( ! $use_override )
      {
        $expire_date = new DateTime( $exp );           // if expire date is in the future unlock,
        $do_unlock   = ( $expire_date >= $now_date );  // otherwise let it remain locked.
      }
    }

    return $do_unlock;
  }
}

class FrontDoorLog
{
  public function __construct() {;}

  public function log_dooropen_event( rfid_key $e, cardkey_row $cr )
  {
    error_log('door unlocked');
    print "$e,\n\ncardkey_row $cr\n";

    $ccl = new cardkey_log_row();

    $v = array( 'RFID' => "$e", 'event' => 'unlocked' );
    try { $v['mmbr_id']           = $cr->g('mmbr_id');           } catch(Exception $e){}
    try { $v['mmbr_secondary_id'] = $cr->g('mmbr_secondary_id'); } catch(Exception $e){}
    try { $v['override']          = $cr->g('override');          } catch(Exception $e){}

    $ccl->set_col_values( $v );

    $ccl->do_insert();
  }

  public function log_doordeny_event( rfid_key $e, cardkey_row $cr )
  {
    error_log('unlock request denied');
    print "$e,\n\ncardkey_row $cr\n";

    $ccl = new cardkey_log_row();

    $v = array( 'RFID' => "$e", 'event' => 'denied' );
    try { $v['mmbr_id']           = $cr->g('mmbr_id');           } catch(Exception $e){}
    try { $v['mmbr_secondary_id'] = $cr->g('mmbr_secondary_id'); } catch(Exception $e){}
    try { $v['override']          = $cr->g('override');          } catch(Exception $e){}

    $ccl->set_col_values( $v );
    $ccl->do_insert();
  }
}

class FrontDoorContoller implements RFIDResourceController
{
  const CTRLSTATE_NOMINAL = 0;
  const CTRLSTATE_ADMIN   = 1; // allow key-override control through RFID reader. not implemented.

  protected $controller_state = self::CTRLSTATE_NOMINAL;
  protected $lock;

  public function __construct()
  {
    $this->fdlock = new FrontDoorLock();       // this is a GPIO
    $this->rfidrd = new RFID_Reader( $this );  // this contains a ttyS*-reading handler
    $this->fdlog  = new FrontDoorLog();
  }


  public function run_control_loop()
  {
    $this->rfidrd->add_RFID_read_handler();
    Ev::run();                                 // Ev::run() never returns.
  }


  // this is a call-back, that which implements the interface defined by
  // RFIDResourceController. RFID_Reader will call this whenever it receives an 
  // RFID from the reading device.
  //
  public function receive_rfid_event( rfid_key $e )
  {
    $fid = 'STUB: '.__CLASS__.'::'.__FUNCTION__;
	  if( $e->key_is_valid() ) // key is well-formed. next, check authorization
    {
      error_log($fid.' received well-formed key: '.$e);

      $c = new FrontDoorLockCriteria( $e );

      error_log("4");

      switch( $this->controller_state )
      {
      case self::CTRLSTATE_NOMINAL:

        error_log("3");
        $cardkey_row=null;

        if( $c->should_open( $cardkey_row ) )
        {
          error_log("1");
          $this->fdlock->unlock_door();
          $this->fdlog->log_dooropen_event( $e, $cardkey_row );
        }
        else
        {
          error_log("2");
          $this->fdlog->log_doordeny_event( $e, $cardkey_row );
        }

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
    else
    {
      error_log($fid.' received badly-formed key');
/*
                $crtra = new door_lock_criteria();
                $crtra->set_key_is_invalid( "invalid key: $key" );
                $crtra->log_event();

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
