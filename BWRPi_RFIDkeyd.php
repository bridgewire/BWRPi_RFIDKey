<?

require_once("php_serial.class.php");
require_once("RPiGPIO.class.php");
require_once("rfid_key.class.php");


class RFID_BadKey_Exception extends Exception {};

abstract class bw_db_table_row
{
  protected $primarykey_value = null;
  protected $primarykey_name = null;
  protected $table_name = null;

  protected $nonkey_colnames;
  protected $colon_colnames;
  protected $nonkey_colvalues;

  protected $refresh_sql = '';
  protected $insert_sql = '';
  protected $update_sql = '';

  public function __construct()
  {
    $this->colon_colnames = preg_replace('/^/', ':', $this->nonkey_colnames ); // prepend all colnames with a colon.

    $cnstr = implode( ', ', $this->nonkey_colnames );
    $colcol_str = implode( ', ', $this->colon_colnames );

    $this->refresh_sql = 'select '.$cnstr.' from '.$this->table_name.' where '.$this->primarykey_name.' = ? ';
    $this->insert_sql = 'insert into '.$this->table_name.' ( '.$cnstr.' ) values ( '.$colcol_str.' ) ';
    $this->update_sql = 'update '.$this->table_name.' set ';
    for( $i=0; $i < count( $this->nonkey_colnames ); $i++ )
    {
      if( $i > 0 )
        $this->update_sql .= ', ';
      $this->update_sql .= $this->nonkey_colnames[$i].' = '.$this->colon_colnames[$i];
    }
    $this->update_sql .= ' where '.$this->primarykey_name = ':'.$this->primarykey_name;
  }


  public function refresh_from_db()
  {
    if( $this->primarykey_value === null )
      throw new Exception( __CLASS__.'::refresh_from_db() requires a non-null primary key value to fetch a row' );

    $dbh = bw_db_connection::instance();

    $stmt = $dbh->prepare( $this->refresh_sql );

    $stmt->execute( array( $this->primarykey_value ) );

    $this->nonkey_colvalues = $stmt->fetch( PDO::FETCH_NUM );  // fetch "into" $this
    $stmt->closeCursor();
  }

  public function insert_into_db()
  {
    $dbh = bw_db_connection::instance();

    $stmt = $dbh->prepare( $this->insert_sql );

    for( $i=0; $i < count( $this->colon_colnames ); $i++ )
      $stmt->bindParam( $this->colon_colnames[$i], $this->nonkey_colvalues[$i] );

    $stmt->execute();

    $this->primarykey_value = $dbh->lastInsertId();
  }

  public function replace_into_db()
  {
    $dbh = bw_db_connection::instance();

    $stmt = $dbh->prepare( $this->insert_sql );

    for( $i=0; $i < count( $this->colon_colnames ); $i++ )
      $stmt->bindParam( $this->colon_colnames[$i], $this->nonkey_colvalues[$i] );

    $stmt->bindParam( $this->primarykey_name, $this->primarykey_value );

    $stmt->execute( array( $this->primarykey_value ) );
  }
}

class cardkey_log_entry extends bw_db_table_row
{
  // make these public so that we can do: $stmt->setFetchMode( PDO::FETCH_INTO, $this );
/*
  public $cardkey_log_id;
  public $RFID;
  public $mmbr_id;
  public $mmbr_secondary_id;
  public $stamp;
  public $event; // | enum('current','preauth','grace0','grace1','grace2','nograce','unknown','invalid')
  public $note;
*/

  //protected $primarykey_value = null;
  //protected $colon_colnames;
  //protected $nonkey_colvalues;

  protected $primarykey_name = 'cardkey_log_id';
  protected $table_name = 'cardkey';

  protected $nonkey_colnames = array( 'RFID', 'mmbr_id', 'mmbr_secondary_id', 'stamp', 'event', 'note' );

  // if($cklog_id != null) use refresh_from_db() to fetch.

  public function __construct( $cklog_id = null ) 
  { 

    $this->refresh_sql = 'select cardkey_log_id, RFID, mmbr_id, mmbr_secondary_id, stamp, event, note '.
                          ' from cardkey where cardkey_log_id = ?'

    $this->colon_colnames = preg_replace('/^/', ':', $this->nonkey_colnames ); // prepend all colnames with a colon.

    $update_cols = preg_replace();
    $nonkey_colnames

    $cnstr = implode( ', ', $this->nonkey_colnames );
    $colcol_str = implode( ', ', $this->colon_colnames );

    $this->refresh_sql = 'select '.$cnstr.' from '.$this->table_name.' where '.$this->primarykey_name.' = ? '
    $this->insert_sql = 'insert into '.$this->table_name.' ( '.$cnstr.' ) values ( '.$colcol_str.' ) ';
    $this->update_sql = 'update '.$this->table_name.' set ';
    for( $i=0; $i < count( $this->nonkey_colnames ); $i++ )
    {
      if( $i > 0 )
        $this->update_sql .= ', ';
      $this->update_sql .= $this->nonkey_colnames[$i].' = '.$this->colon_colnames[$i];
    }
    $this->update_sql .= ' where '.$this->primarykey_name = ':'.$this->primarykey_name;



    $this->primarykey_value = $cklog_id;
    $this->primarykey_name = 'cardkey_log_id';
    $this->table_name = 'cardkey_log';

    // $this->cardkey_log_id = $cklog_id; 
  }

  public function set_values( $rfid, $mmbr_id, $mmbr_secondary_id, $event, $note )
  {
    $this->RFID = $rfid;
    $this->mmbr_id = $mmbr_id;
    $this->mmbr_secondary_id = $mmbr_secondary_id;
    $this->event = $event;
    $this->note = $note;
  }

  public function create_new_log_entry( $rfid, $mmbr_id, $mmbr_secondary_id, $event, $note=null )
  {
    $this->set_values( $rfid, $mmbr_id, $mmbr_secondary_id, $event, $note );

    $dbh = bw_db_connection::instance();

    $sql = 'insert into cardkey_log ( RFID, mmbr_id, mmbr_secondary_id, event, note ) '.
           'values ( :rfid, :mmbr_id, :mmbr_scdry_id, :event, :note )';

    $stmt = $dbh->prepare( $sql );

    $stmt->bindParam( ':rfid', $rfid );
    $stmt->bindParam( ':mmbr_id', $mmbr_id );
    $stmt->bindParam( ':mmbr_scdry_id', $mmbr_secondary_id );
    $stmt->bindParam( ':event', $event );
    $stmt->bindParam( ':note', $note );

    $stmt->execute();

    $this->primarykey_value = $dbh->lastInsertId();

    $this->refresh_from_db();
  }
/*
  public function refresh_from_db()
  {
    // error_log( $this->cardkey_log_id );

    if( $this->cardkey_log_id === null )
      return false;  // or throw

    $dbh = bw_db_connection::instance();

    $sql = 'select cardkey_log_id, RFID, mmbr_id, mmbr_secondary_id, stamp, event, note from cardkey_log where cardkey_log_id = ?';
    $stmt = $dbh->prepare( $sql );
    $stmt->setFetchMode( PDO::FETCH_INTO, $this );

    // error_log( $this->cardkey_log_id );
    $stmt->execute( array( $this->cardkey_log_id ) );

    $stmt->fetch( PDO::FETCH_INTO );  // fetch "into" $this
    $stmt->closeCursor();
  }
*/
}

class door_lock_criteria
{
  protected $may_open_door = false;
  protected $event_type = 'invalid'; // | enum('current','preauth','grace0','grace1','grace2','nograce','unknown','invalid')
  protected $note = null;
  protected $mmbr = null;

  public function __construct(){}

  public function set_key_is_invalid( $key )
  {
    $this->may_open_door = false;
    $this->event_type = 'invalid';
    $this->note = "$key";
  }

  public function check_members_access( bw_member $m )
  {
    // if we have a bw_member then the key is valid.  new default is 'nograce'
    $this->may_open_door = false;
    $this->event_type = 'nograce';
    $this->mmbr = $m;

    $dbh = bw_db_connection::instance();

    if( ! $m->get_rfid_is_attached() )
    {
      $this->event_type = 'unknown'; // | enum('current','preauth','grace0','grace1','grace2','nograce','unknown','invalid')
      $this->may_open_door = true;
    }
    else
    {
      $expired_date = null; $expired_days = null; $dateofsecthurs = null; $days_past_cutoff = null;

      $dbh->update_useful_vars();

      $sql = 'select ExpireDate, '.
                   ' datediff( now(), ExpireDate ) as expired_days, '.
                   ' @scndthurs as dateofsecthurs, '.                     // @scndthurs is one of the
                   ' datediff( now(), @scndthurs ) as days_past_cutoff '. // useful_var just updated
              ' from members where mmbr_id = ? ';

      $stmt = $dbh->prepare( $sql );
      // error_log( "with parameter: ".$this->mmbr->get_mmbr_id().",  running $sql" );
      $stmt->execute( array( $this->mmbr->get_mmbr_id() ) );

      $stmt->bindColumn( 'ExpireDate', $expired_date );
      $stmt->bindColumn( 'expired_days', $expired_days );
      $stmt->bindColumn( 'dateofsecthurs', $dateofsecthurs );
      $stmt->bindColumn( 'days_past_cutoff', $days_past_cutoff );

      $row = $stmt->fetch( PDO::FETCH_BOUND );
      // error_log( "after FETCH_BOUND " . print_r( $row, true ) );

      //error_log(" ExpireDate: $expired_date expired_days: $expired_days dateofsecthurs: $dateofsecthurs days_past_cutoff: $days_past_cutoff ");

      if( $expired_days !== null && $expired_days <= 0 )  // good standing 
      {
        $this->event_type = 'current'; // | enum('current','preauth','grace0','grace1','grace2','nograce','unknown','invalid')
        $this->may_open_door = true;
      }
      elseif ( $days_past_cutoff !== null && $days_past_cutoff <= 0 )  // grace period is in effect
      {
        $this->event_type = 'grace0'; // | enum('current','preauth','grace0','grace1','grace2','nograce','unknown','invalid')
        $this->may_open_door = true;
      }
      else
      {
        // this default was already set above.  reassert.
        $this->may_open_door = false;
        $this->event_type = 'nograce';  // default event type is denied, remains locked
      }
    }

    return $this->may_open_door;
  }

  // criteria knows the real details of what kind of event this
  // is, so it's in the best position to log the event
  public function log_event()
  {
    $le = new cardkey_log_entry();

    if( $this->mmbr !== null )
    {
      $rfid = $this->mmbr->get_rfid_key()->__toString();
      $mmbrid = null;
      $mmbridsec = null;

      if( $this->mmbr->get_rfid_is_attached() )
      {
        $mmbrid    = $this->mmbr->get_mmbr_id();
        $mmbridsec = $this->mmbr->get_mmbr_secondary_id();
      }

      $le->create_new_log_entry( $rfid, $mmbrid, $mmbridsec, $this->event_type, $this->note );
    }
    else
    {
      $le->create_new_log_entry( null, null, null, $this->event_type, $this->note );
    }
  }

  // public function passes_unlock_criteria( &$evtype ) { $evtype = $this->eventype; return $this->may_open_door; }
  public function passes_unlock_criteria() { return $this->may_open_door; }
}

class bw_member extends bw_db_table_row
{
  // make these public so that we can do: $stmt->setFetchMode( PDO::FETCH_INTO, $this );
  public $mmbr_id = null;
  public $FullName = null;
  public $FirstName = null;
  public $LastName = null;
  public $Address1 = null;
  public $Address2 = null;
  public $City = null;
  public $State = null;
  public $Zip = null;
  public $Phone = null;
  public $Email = null;
  public $DOB = null;
  public $FamilyMembership = null;
  public $Rank = null;
  public $AnnualMember = null;
  public $ExpireDate = null;
  public $IDVerifiedBy = null;
  public $ApplicationDate = null;
  public $WaiverDate = null;
  public $RFID = null;



  protected $rfidkey = null;
  //protected $mmbr_id = null;
  protected $mmbr_secondary_id = null;
  // protected $membership_expires = null;
  // protected $member_expire_date = null;

  protected $rfid_is_attached = true; // assume that the incoming rfid is attached to a bw_member.

  public function get_rfid_is_attached()   { return $this->rfid_is_attached; }
  public function get_mmbr_id()            { return $this->mmbr_id; }
  public function get_mmbr_secondary_id()  { return $this->mmbr_secondary_id; }
  public function get_rfid_key()           { return $this->rfidkey; }

  // public function get_member_expire_date() { return $this->member_expire_date; }


  public function __construct( $key, $mmbr_id=null, $mmbr_secondary_id=null )
  {
    $this->rfidkey = $key;

    $dbh = bw_db_connection::instance();

    if( $mmbr_id === null )
      $dbh->fetch_mmbr_id_etc_from_rfid( $key, $mmbr_id, $mmbr_secondary_id );

    $this->mmbr_id = $mmbr_id;
    $this->mmbr_secondary_id = $mmbr_secondary_id;
  }
}


class RFID_Reader
{
  protected $lockgpio;
  protected $locked = true;
  protected $lock_time = null;     // an absolute future time-stamp when the door should lock. Ev is a better way.
  protected $dooropentime = 0;

  protected $lock_EvTimer = null;
  protected $RFID_EvIo = null;

  protected $RFID_serial = null;

  const GPIO_OPENDOOR = 1;
  const GPIO_LOCKDOOR = 0;

  public function __construct()
  {
    $this->RFID_serial = new phpSerial();
    if( ! $this->RFID_serial->confPort( "/dev/ttyAMA0", 9600 ) )
      throw new Excption("RFID_serial::confPort() failed");

    $this->RFID_serial->deviceOpen();
    $this->RFID_serial->confBlocking( false ); // non-blocking

    $this->lockgpio = new RPiGPIO( 2, "out" );
    $this->lockgpio->export();
    $this->lock_door();
  }

  public function is_locked() { return $this->locked; }

  public function unlock_door()
  {
    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: running unlock door' );
    $this->lockgpio->write_value( RFID_Reader::GPIO_OPENDOOR );
    $this->locked = false;
    $this->lock_time = time() + 10;
    $this->lock_EvTimer = new EvTimer( 3, 0, function($tmr) { $tmr->data->lock_door(); }, $this );
  }

  public function lock_door()
  {
    $d = new DateTime; error_log( $d->format("Y-m-d H:i:s").' :: running lock door' );
    $this->lockgpio->write_value( RFID_Reader::GPIO_LOCKDOOR );
    $this->lock_time = null;
    $this->locked = true;
  }


  public function open_door_if_member_allowed( $mmbr )
  {
    $crtra = new door_lock_criteria();
    $crtra->check_members_access( $mmbr );

    if( $crtra->passes_unlock_criteria() )
      $this->unlock_door();

    $crtra->log_event();
  }

  public function add_RFID_read_handler()
  {
    $this->RFID_EvIo = new EvIo(

        $this->RFID_serial->getFilehandle(),

        Ev::READ,

        function( $io, $revents )          // this anonymous function is the handler
        {
          static $key = null;

          try
          {
            if( $key === null )
              $key = "";

            $key .= $this->RFID_serial->readPort( 1024, null );

            if( strlen( $key ) >= 16 )
            {
              $k = new rfid_key ( $key ); // throws an exception if the key is badly formed
              if( $k->key_is_valid() )
              {
                $found = $k->lookup_rfid();

                $m = new bw_member( $k );

                // '$io->data' is 'this', an instance of 'RFID_Reader'
                $io->data->open_door_if_member_allowed( $m ); // this function logs the event

                $key = null;
              }
              else
              {
                error_log( "bad key: ".$e );
                $crtra = new door_lock_criteria();
                $crtra->set_key_is_invalid( "invalid key: $key" );
                $crtra->log_event();

                $key = null;
              }
            }
          }
          catch( Exception $e )
          {
            error_log( "exception: ".$e );
            $crtra = new door_lock_criteria();
            $crtra->set_key_is_invalid( "exception generated while processing key: $key" );
            $crtra->log_event();
            $key = null;

            // this sleep doesn't hurt. it helps handle the case where all calls throw an exception.
            sleep(1); 
          }
        },

        $this
    );          // new EvIo
  }
}

class bw_db_connection
{
  protected $dbh = NULL;

  // singleton instanciator
  public static function instance()
  {
    static $inst = null;
    if( $inst === null )
      $inst = new bw_db_connection();

    return $inst; // return a copy
  }

  // public function prepare( $s )  { return $this->dbh->prepare( $s, array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true) ); }
  public function prepare( $s )  { return $this->dbh->prepare( $s ); }
  public function query( $s )  { return $this->dbh->query( $s ); }
  public function lastInsertId() { return $this->dbh->lastInsertId(); }

  protected function __construct()
  {
    $this->dbh = new PDO('mysql:host=localhost;dbname=BridgewireMembers', 'root', ''); 
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // use exceptions to handle all errors
  }

  public function member_lookup_byRFID( $key )
  {
    $mmbr_id = null;
    $mmbr_secondary_id = null;

    $sql = 'select mmbr_id, mmbr_secondary_id from cardkey where RFID = ?';
    $stmt = $this->dbh->prepare( $sql );

    $stmt->bindColumn( 'mmbr_id', $mmbr_id );
    $stmt->bindColumn( 'mmbr_secondary_id', $mmbr_secondary_id );

    $stmt->execute( array( "$key" ) );  // uses $key->__toString() as argument to execute.

    return new bw_member( $mmbr_id, $mmbr_secondary_id, $key );
  }

  public function fetch_mmbr_id_etc_from_rfid( $key, &$mmbr_id, &$mmbr_secondary_id )
  {
    $sql = 'select mmbr_id, mmbr_secondary_id from cardkey where RFID = ?';
    $stmt = $this->dbh->prepare( $sql );

    $stmt->execute( array( "$key" ) );  // uses $key->__toString() as argument to execute.

    $stmt->bindColumn( 'mmbr_id',           $mmbr_id );
    $stmt->bindColumn( 'mmbr_secondary_id', $mmbr_secondary_id );

    $stmt->fetch( PDO::FETCH_BOUND );
  }


  public function update_useful_vars()
  {
    $sql = 'select @yr:=year(now()) as yr, @mn:=month(now()) as mn, @dayone:=date(concat(@yr,"-",@mn,"-01")) as dayone';
    $stmt = $this->query($sql);
    $stmt->closeCursor(); // flush all possible open-ended things

    $sql = 'select @scndthurs := date(concat(@yr,"-",@mn,"-",'.
            '7 + (if(5 - date_format(@dayone, "%w") <= 0, 12 - date_format(@dayone, "%w"), 5 - date_format(@dayone, "%w"))))) as scndthurs';
    $stmt = $this->query($sql);
    $stmt->closeCursor(); // flush all possible open-ended things
  }
}






if( preg_grep( '/^(--daemonize|-d)$/', $argv ) ) { error_log('debug: theoretically we daemonize here.'); }

$door = new RFID_Reader();

$door->add_RFID_read_handler();

Ev::run();


/*
class member_info
{
  protected $mmbr_id;
  protected $fullname;
  protected $email;
  protected $expiredate;
  protected $rfid;
  protected $daysexpired;

  public function __construct() {  error_log("member_info::__construct() says Hi Mom."); }

  public function __toString() { return $this->mmbr_id.', '.$this->fullname.', '.$this->email.', '.$this->expiredate.', '.$this->rfid.', '.$this->daysexpired ; }
}
*/

/*
class rfid_key
{
  protected $debug = true;
  protected $good_checksum = false;
  protected $hexdigits = array();
  protected $withdashes = '';
  protected $rawkey = '';
  protected $valid_checksum = false;

  // every rfid key is, or soon will be, associated with an owner, and is
  // (will be) thus associated with a member id and potentially a
  // secondary-member id too. memeberships expire with a grace period, etc.');

  protected $mmbr_id;
  protected $mmbr_secondary_id;
  protected $expired_days_count;

  public function __toString() { return $this->withdashes; }
  public function get_key()    { return $this->withdashes; }
  public function get_rawkey() { return $this->rawkey; }

  public function __construct( $key_string=null )
  {
    $this->set_key_string( $key_string );
  }

  public function set_key_string( $key_string )
  {
    $this->valid_checksum = false;
    if( $key_string !== null )
      $this->parse_key( $key_string );
  }

  public function key_is_valid()
  {
    return $this->valid_checksum;
  }

  protected function parse_key( $key )
  {
    if( strlen($key) >= 16 )
    {
      // if the input string "$key" contains more than one key, ignore all but the first.
      $matches = array();
      $pattern = '/\002(([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2}))\r\n\003/i';
      if( preg_match($pattern, $key, $matches ) )
      {
        $this->rawkey = $matches[1];

        $sum = 0;
        $checksum = hexdec( $matches[7] );
        $frmtkey = "";
        for( $i = 2; $i < 7; $i++ )
        {
          $this->hexdigits[] = $matches[$i];
          $sum ^= hexdec( $matches[$i] );
          $frmtkey .= ($i > 2 ? '-' : '').$matches[$i] ;
        }
        $this->hexdigits[] = $matches[7];
        $frmtkey .= '-'.$matches[7];

        if( $sum == $checksum )
        {
          $this->valid_checksum = true;      // the checksum has passed, so it
          $this->withdashes = $frmtkey;      // *can* be evaluated as a key.
        }
      }
    }
    return $this->valid_checksum;
  }

  protected function fetch_cardkey_data()
  {
    assert( $this->valid_checksum );

  }

  protected function lookup_member()
  {
    if( $this->valid_checksum )
    {
    $mmbr_id = null;
    $mmbr_secondary_id = null;

    $sql = 'select mmbr_id, mmbr_secondary_id from cardkey where RFID = ?';
    $stmt = $this->dbh->prepare( $sql );

    $stmt->bindColumn( 'mmbr_id', $this->mmbr_id );
    $stmt->bindColumn( 'mmbr_secondary_id', $this->mmbr_secondary_id );

    $stmt->execute( array( $this->withdashes ) );
    }
  }
}
*/


/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
