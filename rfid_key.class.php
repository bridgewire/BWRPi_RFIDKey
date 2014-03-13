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

  public function __construct( $key_string=null, $key_is_bad=false )
  {
    $this->set_key_string( $key_string, $key_is_bad );
  }

  public function set_key_string( $key_string, $key_is_bad=false )
  {
    $this->is_bad_key = $key_is_bad;
    $this->rawkey = $key_string;

    $this->valid_checksum = false;
    if( $key_string !== null && ! $this->is_bad_key )
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
