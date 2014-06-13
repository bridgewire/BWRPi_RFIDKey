<?

require_once('rfid_key.class.php');
require_once('php_serial.class.php');

interface RFIDResourceController
{
  const CTRLRFIDRSLT_NULL  = '';
  const CTRLRFIDRSLT_UNKON = 'u';
  const CTRLRFIDRSLT_GRANT = 'g';
  const CTRLRFIDRSLT_DENY  = 'd';
  const CTRLRFIDRSLT_ADMIN = 'a'; // possible future use. this is just a placeholder.
  const CTRLRFIDRSLT_BADKY = 'b';

  public function receive_rfid_event( rfid_key $e );
}

class RFID_Reader
{
  protected $dooropentime = 0;

  protected $RFID_EvIo = null;

  protected $RFID_serial = null;
  protected $controller = null;
  protected $keybuf = '';

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
          if( $this->RFID_serial->readPort( $this->keybuf ) && strlen( $this->keybuf ) >= 16 )
          {
            $rfid_event_result = RFIDResourceController::CTRLRFIDRSLT_NULL;
            try
            {
              $rfid_event_result = $this->controller->receive_rfid_event( new rfid_key( $this->keybuf ) );
            }
            catch ( Exception $e )
            {
              error_log( $e->getMessage() );
              error_log( print_r($e->getTrace(), true) );
            }

            if( $rfid_event_result != RFIDResourceController::CTRLRFIDRSLT_BADKY )
            {
              // the key is not "bad" so the event must have been properly handled.
              $this->keybuf = ''; // reset key data after handled event
            }
            elseif( strlen($this->keybuf) > 64 )
            {
              // not only is the key bad, there's no key embedded in a string that's large
              // enough to hold 4 concatenated keys.  This is when our key-reader should
              // be reset.  for now we just truncate the key so that it doesn't grow too
              // large.  Elsewhere in the program, for now anyway, there exists code to
              // exit(1) the program if too many sequential bad-key results are collected.
              // exit...  will result in a quick restart of the server.
              //
              // this code is here because I'm looking for a better way to handle bad keys
              // than a simple exit and restart.  a work in progress.  for instance:
              // in the future, in this place or nearby...  we will likely have a
              // call like:  "$this->RFID_serial->reset()" which presently doesn't exist.

              $this->keybuf = '';
            }
          }
        },

        $this
    );          // new EvIo
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
