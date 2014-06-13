<?

/*
 * Author: Christiana Johnson
 * Copyright 2014
 * License GPL v2
 */

class rfid_key
{
  protected $hexdigits = array();
  protected $withdashes = '';
  protected $rawkey = '';
  protected $valid_checksum = false;
  protected $is_bad_key = false;

  public function __toString() { return $this->withdashes; }
  public function get_key()    { return $this->withdashes; }
  public function get_rawkey() { return $this->rawkey; }

  /* rfid_key::__construct()
   *  @param bytestring key_string  null or matches: /\002(([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2}))\r\n\003/i
   *  @param bool key_is_bad  set to true if the key is known to be badly formed. allows for pushing error handling into processing code.
   *  returns null
   */
  public function __construct( $key_string=null, $key_is_bad=false )
  {
    $this->set_key_string( $key_string, $key_is_bad );
  }

  public function set_key_string( $key_string, $key_is_bad=false )
  {
    $parse_success = false;

    $this->is_bad_key = $key_is_bad;
    $this->rawkey = $key_string;

    $this->valid_checksum = false;
    if( $key_string !== null && ! $this->is_bad_key )
      $parse_success = $this->parse_key( $key_string );

    return $parse_success;
  }

  public function key_is_valid()
  {
    return $this->valid_checksum;
  }

  
  /* rfid_key::set_cooked_key(  $key_string, $key_is_bad=false  )
   *    gives a way to set the RFID key using a string from the db, for instance
   *
   *  @param string cookedkey matches: /[0-9a-f]{2}-?[0-9a-f]{2}-?[0-9a-f]{2}-?[0-9a-f]{2}-?[0-9a-f]{2}-?[0-9a-f]{2}/i
   *  @param bool key_is_bad  see description of this parameter in the __construct() method
   *  returns null
   */
  public function set_cooked_key( $k, $key_is_bad=false )
  {
    // $k is a 'cooked' key
    // from that we construct a raw key, then run the usual algrorithm.

    $new_rawkey = '';

    $pattern = '/([0-9a-f]{2})-?([0-9a-f]{2})-?([0-9a-f]{2})-?([0-9a-f]{2})-?([0-9a-f]{2})-?([0-9a-f]{2})/i';
    if( preg_match($pattern, $k, $matches ) )
    {
      $new_rawkey = chr(2);
      for( $i = 0; $i < 6; $i++ )
        $new_rawkey .= $matches[$i+1];
      $new_rawkey .= "\r\n".chr(3);
    }

    return $this->set_key_string( $new_rawkey, $key_is_bad );
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
        $sum = 0;
        $checksum = hexdec( $matches[7] );
        $frmtkey = "";

        $this->hexdigits = array();

        for( $i = 2; $i < 7; $i++ )
        {
          $this->hexdigits[] = $matches[$i];

          # to check the key's validity we xor the first 5 bytes
          $sum ^= hexdec( $matches[$i] );

          # create the 'withdashes' member
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
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
