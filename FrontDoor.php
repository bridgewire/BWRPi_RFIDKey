<?

require_once('RPiGPIO.class.php');
require_once('RFIDResource.php');
require_once('bwdb_rowdefs.php'); // 


class FrontDoorLock extends RPiGPIO
{
  protected $unlock_duration = 3; // seconds

  const GPIO_OPENDOOR = 1;
  const GPIO_LOCKDOOR = 0;

  protected $locked = true;
  protected $lock_EvTimer = null;

  public function __construct( $gpio_pin = 23 )
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

    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: unlocking door' );
    $this->write_value( self::GPIO_OPENDOOR );
    $this->locked = false;
    $this->lock_EvTimer = new EvTimer( $unlock_seconds_duration, 0, function($tmr) { $tmr->data->lock_door(); }, $this );
  }

  public function lock_door()
  {
    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: locking door' );
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

  protected function do_override( $ckr, &$do_unlock, &$reason )
  {
    $use_override         = false;
    $now_date             = new DateTime();
    $override_code        = null;
    $override_expire_date = null;

    $ckr->g('override', $override_code);
    $ckr->g('override_expires', $override_expire_date);

    // check override first. if override exists and isn't expired then then it is all that matters.
    if( $override_code !== null )
    {
      if( $override_expire_date !== null ) // null is treated as an expired override
      {
        $or_expire_date = new DateTime( $override_expire_date );

        if( $or_expire_date >= $now_date )
        {
          $use_override = true;   // override isn't expired. use the override
          $do_unlock = false;      // default is leave the door locked.

          switch( $override_code )
          {
          case 'u': // unlock the door
            $do_unlock = true;
            $reason = 'override';
            break;

          case 'l': // keep the door locked
            $reason = 'override';
            break;

          default:
            error_log('uknown override type: '.$override_code);

            $use_override = false;  // never mind.  don't use the override.
            break;
          }
        }
      }
    }

    return $use_override;
  }

  public function should_open( &$ckr, &$reason )
  {
    $do_unlock = false;
    $reason = 'unknown key';

    $k = $this->key->get_key();
    $ckr = new cardkey_row( $k );

    // if( ! $ckr->found_in_db() ) { $do_unlock = false; $reason = 'unknown key'; } else...

    if( $ckr->found_in_db() )
    {
      $expire_date = null;
      $ckr->g('expires', $expire_date);

      // before we do a proper check using expiration, look for an override.
      if( ! $this->do_override( $ckr, $do_unlock, $reason ) )
      {
        // override code didn't find the disposition of the RFID,
        // so now we find the disposition using the expiration date.

        if( $expire_date === null )
        {
          # XXX  is this the right thing?  this is too dangerous, I think.
          # we need to cleanup our RFID database.

          $reason = 'grandfathered';
          error_log("key ($k) in db but no expiration date. assuming access is granted.");
          $do_unlock = true;
        }
        else
        {
          $now_date   = new DateTime();
          $exp_date   = new DateTime( $expire_date );

          if( $exp_date >= $now_date )  // simple case: expire date is in the future.
          {
            $do_unlock = true;
            $reason = 'good standing';
          }
          else
          {
            // the rule, in general, is that dues must be paid by the second Thursday of the month.
            // so get the appropriate second-thursday relative to the expiration date.

            $second_thursday = $this->heuristic_nearby_second_thursday( $exp_date );

            // this is a date-time stamp and is set to the first moment of the correct day.
            // the real expiration happens when Thursday ends however, so add 24 hours.
            $second_thursday->add( new DateInterval("P1D") );

            #error_log( 'using second_thursday == '.$second_thursday->format( DateTime::RFC2822 ) );
            #error_log( '  and now_date        == '.$now_date->format( DateTime::RFC2822 ) );

            if( $second_thursday >= $now_date )
            {
              $do_unlock = true;
              $reason = 'grace period';
            }
            else
            {
              $do_unlock = false;
              $reason = 'expired: '.$exp_date->format('Y/m/d');
            }
          }
        }
      }
    }

    return $do_unlock;
  }

  public function heuristic_nearby_second_thursday( DateTime $expiration )
  {
    // we have a couple problems.

    // The first is that the expiration day may not be precisely on the first
    // day of the month. Since the grace period ends on the second Thursday we
    // need to make sure we have an appropriate year and month. The first day
    // of the month is a good starting point for the "second thursday"
    // calculation, but what is a valid and fair "nearest" 1st-day-of-the-month
    // expiration day?  We don't want to elevate past expirations too much.
    // i.e. if the stated expiration day is 15 or even 10 days before the first
    // of month, do we want to give that member 10 or 15 extra grace days
    // beyond the standard grace period?  I'll just say "no" to this question,
    // and choose, somewhat arbitrarily, the "middle ground" number of '5'.

    $exp = clone $expiration;                // $expiration is a shallow copy, or reference. protect original

                                             // make sure the expiration date is the first day of the month
    $exp->add( new DateInterval('P5D') );    // add 5 days.  5 is an arbitrary small number.
    $y    = $exp->format("Y");               // get a integer year.
    $m    = $exp->format("n");               // get a integer month.

    $secthurs="$y-$m-";                      // beginning our target, second-thursday

    $exp  = new DateTime( $secthurs.'1' );   // this is the functional account expiration date

    // Next problem. Once we have a good-enough 1st-day-of-the-month-expiration
    // date, then we want to find the second Thursday of the month with respect
    // to that. the algorithm below does this. get a calendar out and prove it
    // to yourself.


    $athurs = new DateTime('2014-03-06');   // any Thursday will do
    $thursn = (int)($athurs->format('N'));  // PHP's idea of Thursday's position in a week. (it's 4.)
    $edow   = (int)($exp->format('N'));     // get the expiration's day of week. like thursn

    if( $edow <= $thursn )
      $secthurs .= ( 8 + $thursn - $edow);       // $d = 'Y-M-01' + (interval: (7 + ($thursn - $edow)) days);
    else
      $secthurs .= (15 + $thursn - $edow);       // $d = 'Y-M-01' + (interval: (14 - ($edow - $thursn)) days);

    return new DateTime( $secthurs );            // return the second thursday as a DateTime object
  }
}

class FrontDoorLog
{
  public function __construct() {;}

  public function log_door_event( rfid_key $e, $logtype, $reason, cardkey_row $cr=null, $note=null )
  {
    $ccl = new cardkey_log_row();

    # get the values for mmbr_id, mmbr_secondary_id, and override from $cr
    $v = array( 'mmbr_id' => null, 'mmbr_secondary_id' => null, 'override' => null );
    if( $cr != null )
      $cr->get_col_values( $v );

    # set the remaining values in $v for the log
    $v['rfid'] = "$e";
    $v['event'] = $logtype;
    $v['reason'] = $reason;
    if( $note !== null )
      $v['note'] = $note;

    # save the log entry
    $ccl->set_col_values( $v );
    $ccl->do_insert();
  }

  public function log_dooropen_event( rfid_key $e, $rsn, cardkey_row $cr=null, $note=null ) { $this->log_door_event( $e, 'unlocked', $rsn, $cr, $note ); }
  public function log_doordeny_event( rfid_key $e, $rsn, cardkey_row $cr=null, $note=null )
  {
    $this->log_door_event( $e, 'denied', $rsn, $cr, $note );   
    if( $rsn == 'unknown key' ) { error_log('denied unknown key: '.$e); }
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
    static $most_recent_result = null;
    static $rcvd_badlyformed_intandem_count = 0;

	  if( $e->key_is_valid() ) // key is well-formed. next, check authorization
    {
      $c = new FrontDoorLockCriteria( $e );

      switch( $this->controller_state )
      {
      case self::CTRLSTATE_NOMINAL:
        $cardkey_row=null;
        $reason=null;

        if( $c->should_open( $cardkey_row, $reason ) )
        {
          $this->fdlock->unlock_door();
          $this->fdlog->log_dooropen_event( $e, $reason, $cardkey_row );
          $most_recent_result = RFIDResourceController::CTRLRFIDRSLT_GRANT;
        }
        else
        {
          $this->fdlog->log_doordeny_event( $e, $reason, $cardkey_row );
          $most_recent_result = RFIDResourceController::CTRLRFIDRSLT_DENY;
        }

        break;
      case self::CTRLSTATE_ADMIN:  // this is worth while?  too much trouble?
        $most_recent_result = RFIDResourceController::CTRLRFIDRSLT_ADMIN;
        break;
      default:
        $most_recent_result = RFIDResourceController::CTRLRFIDRSLT_UNKON;
        $this->fdlog->log_doordeny_event( $e, $reason );
        break;

      }
    }
    else
    {
      if( $most_recent_result === RFIDResourceController::CTRLRFIDRSLT_BADKY )
        $rcvd_badlyformed_intandem_count++;
      else
        $rcvd_badlyformed_intandem_count = 0;

      $most_recent_result = RFIDResourceController::CTRLRFIDRSLT_BADKY;

      $msg = 'received badly-formed key. parsed:"'.$e->get_key().'"  raw:"'.$e->get_rawkey().'"';
      error_log(__CLASS__.'::'.__FUNCTION__." $msg");
      $this->fdlog->log_doordeny_event( $e, 'malformed', null, $msg );

      if( $rcvd_badlyformed_intandem_count > 2 )
      {
        error_log(__CLASS__.'::'.__FUNCTION__.' received more than one malformed key event. attempting server reset.');
        exit(0);
      }
    }

    return $most_recent_result;
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
