<?php

/**
 * Serial port control class
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * Changes added by Rizwan Kassim <rizwank@geekymedia.com> for OSX functionality
 * default serial device for osx devices is /dev/tty.serial for machines with a built in serial device
 *
 * @author Rémy Sanchez <thenux@gmail.com>
 * @thanks Aurélien Derouineau for finding how to open serial ports with windows
 * @thanks Alec Avedisyan for help and testing with reading
 * @thanks Jim Wright for OSX cleanup/fixes.
 * @copyright under GPL 2 licence
 *
 * As of 2014/02/18, all the IO of this class has essentially been rewritten.
 * These changes are major and include many changes to the public interface.
 * Code written to take advantage of this class will likely need to be altered.
 * Many of the changes have not yet been tested on any platform and those that
 * have were tested only on a Raspbian, a Linux variant on the Raspberry PI (v2).
 * The changes are expected to fix several reliability issues, will likely work
 * well on all platforms, but push some data management to the user of this class
 * thus it is less simple to use (and more powerful).
 * @thanks and/or curses to Christiana Johnson for these changes.
 */
class phpSerial
{
	const SERIAL_DEVICE_NOTSET = 0;
	const SERIAL_DEVICE_SET    = 1;
	const SERIAL_DEVICE_OPENED = 2;

	var $_device = null;
	var $_windevice = null;
	var $_dHandle = null;
	var $_dState = self::SERIAL_DEVICE_NOTSET;
	var $_dPrevState = self::SERIAL_DEVICE_NOTSET;
	var $_dBlocking = true;
	var $_buffer = "";
	var $_buflen = 0;
	var $_os = "";

	/**
	 * This var says if buffer should be flushed by sendMessage (true) or manualy (false)
	 *
	 * @var bool
	 */
	var $autoflush = true;

	/**
	 * Constructor. Perform some checks about the OS and setserial
	 *
	 * @return phpSerial
	 */
	function phpSerial ()
	{
		setlocale(LC_ALL, "en_US");

		$sysname = php_uname();

		if (substr($sysname, 0, 5) === "Linux")
		{
			$this->_os = "linux";

			if($this->_exec("stty --version") === 0)
			{
				register_shutdown_function(array($this, "deviceClose"));
			}
			else
			{
				trigger_error("No stty availible, unable to run.", E_USER_ERROR);
			}
		}
		elseif (substr($sysname, 0, 6) === "Darwin")
		{
			$this->_os = "osx";
            // We know stty is available in Darwin. 
            // stty returns 1 when run from php, because "stty: stdin isn't a
            // terminal"
            // skip this check
//			if($this->_exec("stty") === 0)
//			{
				register_shutdown_function(array($this, "deviceClose"));
//			}
//			else
//			{
//				trigger_error("No stty availible, unable to run.", E_USER_ERROR);
//			}
		}
		elseif(substr($sysname, 0, 7) === "Windows")
		{
			$this->_os = "windows";
			register_shutdown_function(array($this, "deviceClose"));
		}
		else
		{
			trigger_error("Host OS is neither osx, linux nor windows, unable to run.", E_USER_ERROR);
			exit();
		}
	}

	//
	// OPEN/CLOSE DEVICE SECTION -- {START}
	//

	/**
	 * Device set function : used to set the device name/address.
	 * -> linux : use the device address, like /dev/ttyS0
	 * -> osx : use the device address, like /dev/tty.serial
	 * -> windows : use the COMxx device name, like COM1 (can also be used
	 *     with linux)
	 *
	 * @param string $device the name of the device to be used
	 * @return bool
	 */
	function deviceSet ($device)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			if ($this->_os === "linux")
			{
				if (preg_match("@^COM(\d+):?$@i", $device, $matches))
				{
					$device = "/dev/ttyS" . ($matches[1] - 1);
				}

				// setup some better standard serial-port parameters.
				// -F $defice              : which device to config?
				// tell the tty to do less. we want fewer suprises, more raw data.
				// '-hup'                  : don't send hup when last process closes tty
				// 'ignbrk'                : ignore break characters  (pass them through)
				// '-icrnl -onlcr'         : do not translate \r <--> \n  at all.
				// '-opost'                : don't post-process outgoing data
				// '-isig -icanon -iexten' : do not enable various special characters
				// '-echo -echoe -echok'   : turn off some special echo options
				$sttycmd = 'stty -F '.$device;
				$sttycmd .= ' -hup ignbrk -icrnl -onlcr -opost -isig -icanon -iexten -echo -echoe -echok';

				if ($this->_exec($sttycmd) === 0)
				{
					$this->_device = $device;
					$this->_changeState(self::SERIAL_DEVICE_SET);
					return true;
				}
			}
			elseif ($this->_os === "osx")
			{
				if ($this->_exec("stty -f " . $device) === 0)
				{
					$this->_device = $device;
					$this->_changeState(self::SERIAL_DEVICE_SET);
					return true;
				}
			}
			elseif ($this->_os === "windows")
			{
				if (preg_match("@^COM(\d+):?$@i", $device, $matches) and $this->_exec(exec("mode " . $device . " xon=on BAUD=9600")) === 0)
				{
					$this->_windevice = "COM" . $matches[1];
					$this->_device = "\\.\com" . $matches[1];
					$this->_changeState(self::SERIAL_DEVICE_SET);
					return true;
				}
			}

			trigger_error("Specified serial port is not valid", E_USER_WARNING);
			return false;
		}
		else
		{
			trigger_error("You must close your device before to set an other one", E_USER_WARNING);
			return false;
		}
	}

	/**
	 * Opens the device for reading and/or writing.
	 *
	 * @param string $mode Opening mode : same parameter as fopen()
	 * @return bool
	 */
	function deviceOpen ($mode = "r+b")
	{
		if ($this->_dState === self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("The device is already opened", E_USER_NOTICE);
			return true;
		}

		if ($this->_dState === self::SERIAL_DEVICE_NOTSET)
		{
			trigger_error("The device must be set before to be open", E_USER_WARNING);
			return false;
		}

		if (!preg_match("@^[raw]\+?b?$@", $mode))
		{
			trigger_error("Invalid opening mode : ".$mode.". Use fopen() modes.", E_USER_WARNING);
			return false;
		}

		$this->_dHandle = @fopen($this->_device, $mode);

		if ($this->_dHandle !== false)
		{
			stream_set_blocking($this->_dHandle, 0);
			$this->_changeState(self::SERIAL_DEVICE_OPENED);
			return true;
		}

		$this->_dHandle = null;
		trigger_error("Unable to open the device", E_USER_WARNING);
		return false;
	}

	/**
	 * Closes the device
	 *
	 * @return bool
	 */
	function deviceClose ()
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			return true;
		}

		if (fclose($this->_dHandle))
		{
			$this->_dHandle = null;
			$this->_changeState(self::SERIAL_DEVICE_SET);
			return true;
		}

		trigger_error("Unable to close the device", E_USER_ERROR);
		return false;
	}

	/**
	 * Returns a device file handle to allow external stream_select() usages
	 *
	 * @return file pointer resource , the  serial port filehandle
	 */
	function getFilehandle ()
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened to get the file handle", E_USER_WARNING);
			return false;
		}

		return $this->_dHandle;
	}


	//
	// OPEN/CLOSE DEVICE SECTION -- {STOP}
	//

	//
	// CONFIGURE SECTION -- {START}
	//

	/**
	 * Configure all standard config options. 
	 * See other conf* methods for information about a specific arugment.
	 *
	 * @param string $device the name of the device to be used
	 * @param int $rate the rate to set the port in
	 * @param int $charbits length of a character (5 <= length <= 8)
	 * @param string $parity one of the modes
	 * @param float $stopbits the length of a stop bit.
	 * @param string $mode Set the flow control mode.
	 * @return bool  true means all settings succeeded.
	 */
	function confPort( $device, $rate=57600, $charbits=8, $parity='none', $stopbits=1, $mode='none' )
	{
		$sc =  ( $this->deviceSet ($device)               ? 1 : 0 );  // ex: "/dev/ttyS0"
		$sc += ( $this->confBaudRate ( $rate )            ? 1 : 0 );  // 9600
		$sc += ( $this->confCharacterLength ( $charbits ) ? 1 : 0 );  // 5,6,7,8
		$sc += ( $this->confParity ( $parity )            ? 1 : 0 );  // even, none, ...
		$sc += ( $this->confStopBits( $stopbits )         ? 1 : 0 );  // 1, 1.5, 2
		$sc += ( $this->confFlowControl( $mode )          ? 1 : 0 );  // none, rts/cts, xon/xoff
		return ( $sc == 6 );                                          // true if all above returned 1
	}


	/**
	 * Configure the stream blocking parameter.
	 * 
	 * @param bool $on true if blocking is desired
	 * @return void;
	 */

	function confBlocking( $on )
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened to configure the file handle", E_USER_WARNING);
			return false;
		}
		$this->_dBlocking = $on;
		return true;
	}

	/**
	 * Configure the Baud Rate
	 * Possible rates : 110, 150, 300, 600, 1200, 2400, 4800, 9600, 38400,
	 * 57600 and 115200.
	 *
	 * @param int $rate the rate to set the port in
	 * @return bool
	 */
	function confBaudRate ($rate)
	{
		$validBauds = array (
			110    => 11,
			150    => 15,
			300    => 30,
			600    => 60,
			1200   => 12,
			2400   => 24,
			4800   => 48,
			9600   => 96,
			19200  => 19,
			38400  => 38400,
			57600  => 57600,
			115200 => 115200
		);

		$ret = false;
		$havemsg = false;
		$errmsg = "";

		if ($this->_dState !== self::SERIAL_DEVICE_SET)
		{
			$havemsg = true;
			$errmsg = "the device is either not set or opened";
		}
		elseif (isset($validBauds[$rate]))
		{
			$havemsg = true;
			$out = array(0,"");

			switch( $this->_os )
			{
			case 'linux':
                $ret = (0 === $this->_exec("stty -F " . $this->_device . " " . (int) $rate, $out));
				break;
			case 'osx':
                $ret = (0 === $this->_exec("stty -f " . $this->_device . " " . (int) $rate, $out));
				break;
			case 'windows':
                $ret = (0 === $this->_exec("mode " . $this->_windevice . " BAUD=" . $validBauds[$rate], $out));
				break;
			default:
				$havemsg = false;
				break;
            }
			$errmsg = $out[1];
		}

		// the purpose of this is to report $out[1], an error message from the _exec'd command
		if (! $ret)
		{
			$msg = 'Unable to set baud rate';
			if( $havemsg )
				$msg .= ': '.$out[1];
			trigger_error( $msg, E_USER_WARNING );
		}

        return $ret;
	}

	/**
	 * Configure parity.
	 * Modes : odd, even, none
	 *
	 * @param string $parity one of the modes
	 * @return bool
	 */
	function confParity ($parity)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set parity : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$args = array(
			"none" => "-parenb",
			"odd"  => "parenb parodd",
			"even" => "parenb -parodd",
		);

		if (!isset($args[$parity]))
		{
			trigger_error("Parity mode not supported", E_USER_WARNING);
			return false;
		}

		if ($this->_os === "linux")
		{
			$ret = $this->_exec("stty -F " . $this->_device . " " . $args[$parity], $out);
		}
		elseif ($this->_os === "osx")
		{
			$ret = $this->_exec("stty -f " . $this->_device . " " . $args[$parity], $out);
		}
		else
		{
			$ret = $this->_exec("mode " . $this->_windevice . " PARITY=" . $parity{0}, $out);
		}

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set parity : " . $out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Sets the length of a character.
	 *
	 * @param int $int length of a character (5 <= length <= 8)
	 * @return bool
	 */
	function confCharacterLength ($int)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set length of a character : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$int = (int) $int;
		if ($int < 5) $int = 5;
		elseif ($int > 8) $int = 8;

		if ($this->_os === "linux")
		{
			$ret = $this->_exec("stty -F " . $this->_device . " cs" . $int, $out);
		}
		elseif ($this->_os === "osx")
		{
			$ret = $this->_exec("stty -f " . $this->_device . " cs" . $int, $out);
		}
		else
		{
			$ret = $this->_exec("mode " . $this->_windevice . " DATA=" . $int, $out);
		}

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set character length : " .$out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Sets the length of stop bits.
	 *
	 * @param float $length the length of a stop bit. It must be either 1,
	 * 1.5 or 2. 1.5 is not supported under linux and on some computers.
	 * @return bool
	 */
	function confStopBits ($length)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set the length of a stop bit : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		if ($length != 1 and $length != 2 and $length != 1.5 and !($length == 1.5 and $this->_os === "linux"))
		{
			trigger_error("Specified stop bit length is invalid", E_USER_WARNING);
			return false;
		}

		if ($this->_os === "linux")
		{
			$ret = $this->_exec("stty -F " . $this->_device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);
		}
		elseif ($this->_os === "osx")
		{
			$ret = $this->_exec("stty -f " . $this->_device . " " . (($length == 1) ? "-" : "") . "cstopb", $out);
		}
		else
		{
			$ret = $this->_exec("mode " . $this->_windevice . " STOP=" . $length, $out);
		}

		if ($ret === 0)
		{
			return true;
		}

		trigger_error("Unable to set stop bit length : " . $out[1], E_USER_WARNING);
		return false;
	}

	/**
	 * Configures the flow control
	 *
	 * @param string $mode Set the flow control mode. Availible modes :
	 * 	-> "none" : no flow control
	 * 	-> "rts/cts" : use RTS/CTS handshaking
	 * 	-> "xon/xoff" : use XON/XOFF protocol
	 * @return bool
	 */
	function confFlowControl ($mode)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_SET)
		{
			trigger_error("Unable to set flow control mode : the device is either not set or opened", E_USER_WARNING);
			return false;
		}

		$linuxModes = array(
			"none"     => "clocal -crtscts -ixon -ixoff",
			"rts/cts"  => "-clocal crtscts -ixon -ixoff",
			"xon/xoff" => "-clocal -crtscts ixon ixoff"
		);
		$windowsModes = array(
			"none"     => "xon=off octs=off rts=on",
			"rts/cts"  => "xon=off octs=on rts=hs",
			"xon/xoff" => "xon=on octs=off rts=on",
		);

		if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
			trigger_error("Invalid flow control mode specified", E_USER_ERROR);
			return false;
		}

		if ($this->_os === "linux")
			$ret = $this->_exec("stty -F " . $this->_device . " " . $linuxModes[$mode], $out);
		elseif ($this->_os === "osx")
			$ret = $this->_exec("stty -f " . $this->_device . " " . $linuxModes[$mode], $out);
		else
			$ret = $this->_exec("mode " . $this->_windevice . " " . $windowsModes[$mode], $out);

		if ($ret === 0) return true;
		else {
			trigger_error("Unable to set flow control : " . $out[1], E_USER_ERROR);
			return false;
		}
	}

	/**
	 * Sets a setserial parameter (cf man setserial)
	 * NO MORE USEFUL !
	 * 	-> No longer supported
	 * 	-> Only use it if you need it
	 *
	 * @param string $param parameter name
	 * @param string $arg parameter value
	 * @return bool
	 */
	function setSetserialFlag ($param, $arg = "")
	{
		if (!$this->_ckOpened()) return false;

		$return = exec ("setserial " . $this->_device . " " . $param . " " . $arg . " 2>&1");

		if ($return{0} === "I")
		{
			trigger_error("setserial: Invalid flag", E_USER_WARNING);
			return false;
		}
		elseif ($return{0} === "/")
		{
			trigger_error("setserial: Error with device file", E_USER_WARNING);
			return false;
		}
		else
		{
			return true;
		}
	}

	//
	// CONFIGURE SECTION -- {STOP}
	//

	//
	// I/O SECTION -- {START}
	//

	/**
	 * Sends a string to the device
	 *
	 * @param string $str string to be sent to the device
	 * @param int $len number of bytes to write.
	 *   if($len <= 0) then attempt to write the whole buffer.
	 * @return int indicating the number of bytes actually written.
	 */
	function sendMessage ($str, $len=0)
	{
		if( ! empty($str) ) // _buffer may not be empty
		{
			$this->_buffer .= $str;
			$this->_buflen += strlen($str);
		}

		$written = 0;
		$bytes = 0;

		if ($this->autoflush === true || $len < 0)
		{
			// success means the whole buffer was written.
			$bytes = $this->_buflen;
			if( ! $this->serialflush() )
				$bytes = false;
		}
		elseif ($len != 0)
		{
			// do not try to write more than we have in the buffer.
			$len = ( $len <= $this->_buflen ? $len : $this->_buflen );

			$written = 0;
			do{ 
				$bytes = $this->writePort( $this->_buffer, $len - $written );
				if ($bytes !== false)
					$this->_buflen -= $bytes;
			} while( $written < $len && $bytes !== false );

			// but...  what if $written !== 0 before this?
			if( $bytes === false )
				$written = false;
		}
		// else { write nothing at all right now. wait for explicit flush. }

		return $written;
	}

	public function appendToBuffer( $cntnt ) { $this->_buffer .= $cntnt; $this->_buflen = strlen($this->_buffer); }
	public function setBuffer( $cntnt )      { $this->_buffer  = $cntnt; $this->_buflen = strlen($this->_buffer); }
	public function getBuffer() { return $this->_buffer; }
	public function getBufLength() { return $this->_buflen; }

	/**
	 * XXX Experimental.
	 * This should work for all platforms, including windows, but is untested.
	 *
	 * Writes to the port.  A reaonably standard write() function.
	 *
	 * @pararm string &$content  This the content to write.  The resulting
	 *  value of this parameter, after return, will be shorter if successful.
	 * @pararm int $len  count of bytes characters write.(from write docs)
	 *  writing will stop after len bytes have been written or the end
	 *  of content is reached, whichever comes first.
	 * @pararm float $timeout number of seconds to try before returning.
	 *  null timeout means block until finished writing or until an error
	 * @return int the count of bytes written or false on error.
	 */
	public function writePort( &$content, $count=null, $timeout=null )
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened to write to it", E_USER_WARNING);
			return false;
		}

		// return 0 if there's nothing to write. validate $count.
		if ($count !== null && $count <= 0 )                return 0;
		if ($count === null || strlen($content) < $count )  $count = strlen($content);
		if ($count == 0)                                    return 0;

		// negative timeout has already timed out.
		if ($timeout !== null && $timeout < 0 )             return 0;
		// but now negative timeout becomes a code for "never timeout"
		if ($timeout === null)                              $timeout = -1;

		$starttime = microtime( true );
		$totcnt = 0;	// at all times, this is the total count of bytes sent

		$error = false; // stop loop if there's an error
		$et = 0;		// elapsed time. if timeout==0: execute all, once.

		do {
			$ready_count = 1;

			// because non-blocking is set on _dHandle, we must implement a 
			// blocking call on our own.  We do this using stream_select().
			// _dBlocking == true by default, so this code, in the 
			// following block of code, will probably run.
			if( $this->_dBlocking )
			{
				// if blocking, stop and wait for $timeout seconds
				// or until there's something to read
				$loop_count = 0;
				do {
					$loop_count++;
					$r = null;
					$w = array( $this->_dHandle );
					$e = null;

					if( $timeout < 0 ) // "no timeout" can be a very long time
						$ready_count = stream_select( $r, $w, $e, NULL );
					else
					{
						// if $et == $timeout then to_usec == 0, and select() won't block
						if( $et > $timeout )  // timed out.
							break;

						$to_sec = $to_usec = 0; // php-style declaration
						floatsec_to_secusec( $timeout - $et, $to_sec, $to_usec );

						$ready_count = stream_select( $r, $w, $e, $to_sec, $to_usec );
					}

					// if we're getting errors terminate this loop after 
					// only a few tries.  this should happen very rarely.
					if ($loop_count > 4)
						break;
				} while ( $ready_count === false );

				if ($ready_count === false)	// needed once in a blue moon.
					$ready_count = 0;
			}

			if ($ready_count > 0)
			{
				$ac = fwrite($this->_dHandle, $count-$totcnt);
				if( $ac === false )
					$error = true;
				elseif( $ac > 0 )
				{
					$totcnt += $ac;
					$content = substr( $content, $ac );
				}
			}

			if( $timeout >= 0 )
			{
				$et = microtime( true ) - $starttime;
				if( $et >= $timeout )	// timed out
					break;				// break from while() loop. stop everything.
			}
		} while (   $totcnt < $count	// stop if total meets read count sought
				 && $this->_dBlocking	// !!!  do not loop if non-blocking
				 && ! $error );			// stop if error

		// we want to report an error if there is one. setting totcnt to 
		// false doesn't destroy information because totcnt ==
		// strlen($content) and $content *is* returned.
		if( $error )
			$totcnt = false;

		return $totcnt;  // set to false if error
	}

	/**
	 * XXX Experimental but should fix bugs, hopefully without making new ones.
	 * This should work for all platforms, including windows, but is untested.
	 *
	 * Reads the port until no new datas are availible, then return the content.
	 *
	 * @pararm string &$content  This value is ignored, but the parameter is
	 *  used to return the data read from the port. Parameter is set to ""
	 *  if no data is read.
	 * @pararm int $count number of characters to be read (will stop before
	 * 	if less characters are in the buffer)
	 * @pararm float $timeout number of seconds to try before returning.
	 *  null timeout means block until finished reading or until an error.
	 *  This parameter is ignored in non-blocking mode.
	 * @return int the count of bytes read or false on error.
	 */
	function readPort (&$content, $count = null, $timeout = null)
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened to read it", E_USER_WARNING);
			return false;
		}

		if ($count !== null && $count <= 0 )    return 0;
		if ($count === null)                    $count = 0;
		if ($timeout !== null && $timeout < 0 ) return 0;
		if ($timeout === null)                  $timeout = -1;

		if ($content === null)
			$content = "";
		$starttime = microtime( true );
		$totcnt = 0;	// at all times, this is the total count of bytes read

		$error = false; // stop loop if there's an error
		$et = 0;		// elapsed time. if timeout==0: execute all, once.

		do {
			$ready_count = 1;

			// because non-blocking is set on _dHandle, we must implement a 
			// blocking call on our own.  We do this using stream_select().
			// _dBlocking == true by default, so this code, in the 
			// following block of code, will probably run.
			if( $this->_dBlocking )
			{
				// if blocking, stop and wait for $timeout seconds
				// or until there's something to read
				$loop_count = 0;
				do {
					$loop_count++;
					$r = array( $this->_dHandle );
					$w = null;
					$e = null;

					if( $timeout < 0 ) // "no timeout" can be a very long time
						$ready_count = stream_select( $r, $w, $e, NULL );
					else
					{
						// if $et == $timeout then to_usec == 0, and select() won't block
						if( $et > $timeout )  // timed out.
							break;

						$to_sec = $to_usec = 0; // php-style declaration
						floatsec_to_secusec( $timeout - $et, $to_sec, $to_usec );

						$ready_count = stream_select( $r, $w, $e, $to_sec, $to_usec );
					}

					// if we're getting errors terminate this loop after 
					// only a few tries.  this should happen very rarely.
					if ($loop_count > 4)
						break;
				} while ( $ready_count === false );

				if ($ready_count === false)	// needed once in a blue moon.
					$ready_count = 0;
			}

			if ($ready_count > 0)
			{
				$tc = 1024*1024;	// READ!!! as much as possible.
				if ($count !== 0)	// unless otherwise told
					$tc = ($count - $totcnt);

				$bytes = fread($this->_dHandle, $tc);
				if( $bytes === false )
					$error = true;
				else
				{
					$ac = strlen( $bytes ); // strlen() gives *byte* count, including nulls.
					if( $ac != 0 )
					{
						$totcnt += $ac;
						$content .= $bytes;
					}
				}
			}

			if( $timeout >= 0 )
			{
				$et = microtime( true ) - $starttime;
				if( $et >= $timeout )	// timed out
					break;				// break from while() loop. stop everything.
			}

		} while (   $totcnt < $count	// if($count == NULL) loop executes once
				 && $this->_dBlocking	// !!!  do not loop if non-blocking
				 && ! $error);			// stop if error

		// we want to report an error if there is one. setting totcnt to 
		// false doesn't destroy information because totcnt ==
		// strlen($content) and $content *is* returned.
		if( $error )
			$totcnt = false;

		return $totcnt;  // set to false if error
	}

	/**
	 * Flushes the output buffer
	 * Renamed from flush for osx compat. issues
	 *
	 * @return bool
	 */
	function serialflush ()
	{
		$success = false;
		if (!$this->_ckOpened()) return false;

		$init_length = $this->_buflen;
		$bytes = 0;
		$written = 0;
		while( $this->_buflen > 0 && $bytes !== false )
		{
			// long timeout because flush() should take all necessary time.
			$bytes = $this->writePort( $this->_buffer, $this->_buflen, 5.0 );
			if( $bytes !== false )
			{
				$written += $bytes;
				$this->_buflen -= $bytes;
			}
		}

		// success only truely depends only on if the buffer was actually 
		// written or not, so report that value regardless of errors.
		$success = ( $written === $init_length );

		if( $bytes === false )
			trigger_error('Error while sending message. wrote '.$written.' of '.$init_length.' byte(s) before failing.', E_USER_WARNING);

		// XXX  assert( $this->_buflen === 0 && strlen($this->_buffer) === 0 )
		if( $this->_buflen !== 0 || strlen($this->_buffer) !== 0 )
			trigger_error('Internal Error. Flush fell short? wrote '.$written.' of '.$init_length.' byte(s) before exit.', E_USER_WARNING);

		// after flush the buffer is guarenteed to be empty.
		$this->_buffer = "";
		$this->_buflen = 0;

		return $success;
	}

	//
	// I/O SECTION -- {STOP}
	//

	//
	// INTERNAL TOOLKIT -- {START}
	//

	/**
	 * Protected
	 * Change Device state. for managing state info
	 * 
	 * @param int $newstate the state change to. one of the SERIAL_DEVICE_* constants.
	 * @return int
	 */
	protected function _changeState( $newstate )
	{
		$this->_dPrevState = $this->_dState; // to detect 'closed' state. perhaps other.
		$this->_dState = $newstate;
		return $this->_dState;
	}

	function _ckOpened()
	{
		if ($this->_dState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be opened", E_USER_WARNING);
			return false;
		}

		return true;
	}

	function _ckClosed()
	{
		// to differentiate 'closed' from 'set' we check both _dState and _dPrevState
		if ($this->_dState !== self::SERIAL_DEVICE_SET || $this->_dPrevState !== self::SERIAL_DEVICE_OPENED)
		{
			trigger_error("Device must be closed", E_USER_WARNING);
			return false;
		}

		return true;
	}

	function _exec($cmd, &$out = null)
	{
		$desc = array(
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);

		$proc = proc_open($cmd, $desc, $pipes);

		$ret = stream_get_contents($pipes[1]);
		$err = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		$retVal = proc_close($proc);

		if (func_num_args() == 2) $out = array($ret, $err);
		return $retVal;
	}

	//
	// INTERNAL TOOLKIT -- {STOP}
	//
}

// convert float to pair of ints. sec and usec
function floatsec_to_secusec( $floatsec, &$sec, &$usec )
{
	$usec = $sec = $floatsec;
	$sec = floor( $sec );	// get int seconds
	$usec -= $sec;			// get fractional seconds
	$usec *= 1000000;		// micro is million
	$usec = floor( $usec );	// discard nanosec
}

/* vim: set ai noet ts=4  sw=4: */
?>
