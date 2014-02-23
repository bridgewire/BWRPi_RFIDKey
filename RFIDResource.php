<?

require_once('rfid_key.class.php');
require_once('php_serial.class.php');

class RFIDEvent extends rfid_key               // requires rfid_key.class.php
{
  public $bad_key_data = null;

  public function __construct( $key_string=null, $isbadkey=false )
  {
    if( $isbadkey )
      $this->bad_key_data = $key_string;

    parent::__construct($key_string);
  }
}

interface RFIDResourceController
{
  public function receive_rfid_event( RFIDEvent $e );
}

class RFID_Reader
{
  protected $dooropentime = 0;

  protected $RFID_EvIo = null;

  protected $RFID_serial = null;
  protected $controller = null;

  public function __construct( RFIDResourceController $resource_controller )
  {
    $this->controller = $resource_controller;

    $this->RFID_serial = new phpSerial();      // requires php_serial.class.php

    if( ! $this->RFID_serial->confPort( "/dev/ttyAMA0", 9600 ) )
      throw new Excption("RFID_serial::confPort() failed");

    $this->RFID_serial->deviceOpen();
    $this->RFID_serial->confBlocking( false ); // non-blocking
  }

  public function add_RFID_read_handler()
  {
    $this->RFID_EvIo = new EvIo(

        $this->RFID_serial->getFilehandle(),

        Ev::READ,

        function( $io, $revents )              // the handler. anonymous fnct
        {
          static $key = null;                  // '$key' is the read-buffer.

          if( $key === null )
            $key = "";

          if( $this->RFID_serial->readPort( $key ) && strlen( $key ) >= 16 )
          {
            $this->controller->receive_rfid_event( new RFIDEvent( $key ) );
            $key = null; // reset key data after handled event
          }
        },

        $this
    );          // new EvIo
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
