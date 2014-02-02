<?PHP
/* Author: Christiana Johnson
 * Copyright 2014
 * License: GPL v2  (it's a standard. look it up.)
 *
 * This class is for use in raspbian on a standard Raspberry Pi.
 * Example:
 * 
 * # export gpio 0, setting its type to 'out', then set the pin value high.
 * $g = new RPiGPIO( 0, 'out' );
 * $g->export();
 * $g->write_value( 1 );
 *
 * The pin will remain in whatever state it is given, even after your
 * program ends, until its state is otherwise changed.
 *
 * XXX This class makes no attempt to handle resource contention.  i.e. we 
 * don't flock anything, we don't create lock files... none of that.
 *
 * */

class RPiGPIO
{
  protected $pinn = 0;
  protected $drctn  = 'in';
  protected $d = '';
  protected $expd = false;
  protected $value_fd = false;

  protected $vfile_handle;

  public function __construct( $pinno = 0, $drctn = 'in' )
  {
    if( $pinno !== 0    && $pinno !== 1     ) throw new exception("invalid arg: pinno === $pinno, must be type int");
    if( $drctn !== 'in' && $drctn !== 'out' ) throw new exception("invalid arg: drctn === $drctn");

    $this->drctn = $drctn;
    $this->pinn  = $pinno;
    $this->d = '/sys/class/gpio/gpio'.$this->pinn;
  }

  public function __destruct() {}

  public function export()
  {
    if( ! file_exists( $this->d ) )
    {
      $fh = fopen( '/sys/class/gpio/export', 'r+' ); // open for reading and writing
      if( $fh )
      {
        $p = $this->pinn;
        $wrtn = fwrite( $fh, "$p\n" ); // write a string version of the pin number to export
        if( $wrtn )
        {
          fflush( $fh );  // make sure the write is complete
          usleep(100000); # wait 100 milliseconds for the file to be created
        }
        fclose( $fh );
      }
      $direction_set = false;

      // if the above block succeded,  then this file will now exist. it must exist.
      if( ! file_exists( $this->d ) )
        throw new exception( 'failed to export gpio pin#: '.$this->pinn  );
      else
      {
        $fh = fopen( $this->d.'/direction', 'r+' );  // open for reading and writing
        if( $fh )
        {
          $wrtn = fwrite( $fh, $this->drctn."\n" );    // write "in" or "out" to fh
          if( $wrtn )
          {
            $direction_set = true;
            usleep(100000); // pause for 1/10 of a second to allow gpio configuration to take place
          }
          fclose( $fh );
        }

        if( ! $direction_set )
          throw new exception( 'failed to set gpio direction for pin#: '.$this->pinn  );
      }
    }
  }


  public function unexport()
  {
    if( file_exists( $this->d ) )
    {
      $fh = fopen( '/sys/class/gpio/unexport', 'r+' ); // open for reading and writing
      $wrtn = fwrite( $fh, ''.$this->pinn.'' );        // write a string version of the pin number to unexport
      if( $wrtn )
        fflush($fh);  // make sure the write is complete
      fclose( $fh );

      if( file_exists( $this->d ) )
        throw new exception( 'failed to unexport gpio pin number: '.$this->pinn  );
    }
  }

  public function read_value()
  {
    $val = false;

    if( $this->open() )
    {
      $val = fread( $this->value_fd, 1024 ); // be sure to empty the buffer
      if( $val !== false && strlen($val) > 0 )
      {
        $val = substr($val, 0, 1); // exactly one char
        $val = (int) $val;
      }
      else
        $val = false;
    }

    $this->close();

    return $val;
  }

  public function write_value( $val )
  {
    $wrtn = false;

    if( $val !== 0 && $val !== 1 )
      throw new exception("invalid arg: $val must be int in:{0,1}");

    if( $this->open() )
    {
      $wrtn = fwrite( $this->value_fd, "$val" ); 
      if( $wrtn !== 1 )
        $wrtn = false;
    }
    $this->close();

    return $wrtn;
  }


  public function open()
  {
    $ret = true;

    if( $this->value_fd === false )
    {
      $mode = 'r';    // read-only
      if( $this->drctn == 'out' )
        $mode .= '+'; // writable writable too.

      $this->value_fd = fopen( $this->d.'/value', $mode );
      if( $this->value_fd === false )
        $ret = false;
    }
    return $ret;
  }

  public function close()
  {
    $ret = true;
    if( $this->value_fd !== false )
      $ret = fclose( $this->value_fd );
    if( $ret )
      $this->value_fd = false;
    return $ret;
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
