<?

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

  public function lookup_rfid()
  {
    $found = false;
    if( $this->valid_checksum )
    {
      $mmbr_id = null;
      $mmbr_secondary_id = null;

      $sql = 'select mmbr_id, mmbr_secondary_id from cardkey where RFID = ?';
      $stmt = $this->dbh->prepare( $sql );

      $stmt->bindColumn( 'mmbr_id', $this->mmbr_id );
      $stmt->bindColumn( 'mmbr_secondary_id', $this->mmbr_secondary_id );

      $found = $stmt->execute( array( $this->withdashes ) );
    }
    return $found;
  }
}



/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
