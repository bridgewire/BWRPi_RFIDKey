<?

require_once('rfid_key.class.php');
require_once('php_serial.class.php');

interface RFIDResourceController
{
  public function receive_rfid_event( rfid_key $e );
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
            try
            {
              $this->controller->receive_rfid_event( new rfid_key( $key ) );
            }
            catch ( Exception $e )
            {
              error_log( $e->getMessage() );
              error_log( print_r($e->getTrace(), true) );
            }

            $key = null; // reset key data after handled event
          }
        },

        $this
    );          // new EvIo
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
