<?
require_once('bwdb_rowdefs.php');

define('LOCAL_LASTSTAMP_FILE', '/home/pi/frontdoord/data/lastupdatedstamp.txt' );

main();

function main()
{
  $remote_stamp = fetch_site_lastupdatedstamp();
  $local_stamp  = fetch_local_lastupdatedstamp();

  if( $remote_stamp !== $local_stamp )
  {
    if( fetch_keycards_from_site() )
    {
      if( false === save_local_lastupdatedstamp( $remote_stamp ) )
        error_log( 'save_local_lastupdatedstamp() failed to save remote_stamp: '.$remote_stamp );
    }
    else
      error_log( 'fetch_keycards_from_site() returned false, reporting no successful database writes' );
  }
  //else
  //  error_log( 'the remote and local stamps are the same' );
}


function fetch_from_site( $page )
{
  $site = 'http://bridgewire.org';
  $credentials = '<youruser>:<userspasswd>';

  $headers = array(
    "GET".$page." HTTP/1.0",
    "Authorization: Basic " . base64_encode($credentials) );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $site.$page );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  return curl_exec($ch); 
}

function fetch_site_lastupdatedstamp()
{
  return fetch_from_site( '/z/priv/checkfor_cardkey_update.php' );
}

function fetch_local_lastupdatedstamp() { return file_get_contents( LOCAL_LASTSTAMP_FILE ); }
function save_local_lastupdatedstamp( $stamp ) { return file_put_contents( LOCAL_LASTSTAMP_FILE, $stamp, LOCK_EX ); }


function fetch_keycards_from_site()
{
  $success = false;

  $data = fetch_from_site( '/z/priv/fetchcardkeys.php' );
  if( $data === false || strlen($data) === 0 )
    error_log('fetch_from_site() returned false.  failed to retrieve keycard info.');

  else
  {
    $newcardkeys = json_decode( $data );    //  print_r( $newcardkeys );

    $dbh = bwdb_connection::instance();
    $havetmp = $dbh->query("create temporary table allkeys (rfid char(17), index(rfid))"); 

    $tmpcount = 0;
    $rowcount = 0;
    foreach( $newcardkeys as $u )
    {
      $v = get_object_vars($u);

      if( ! array_key_exists( 'RFID', $v ) || ! preg_match( '/^[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}$/i', $v['RFID'] ) )
      {
        error_log("sanity check failed for fetched data element...");
        error_log(print_r($v,true));
      }
      else
      {
        if( $havetmp )
        {
          if( $dbh->query('insert into allkeys values ("'.$v['RFID'].'")') )
            $tmpcount++;
        }
        $rowcount++;


        // this instruction fetches a row from the table 'cardkey' that 
        // has $v['RFID'] as its primary key. this allows us to compare 
        // the incoming data with the data we already have in the table.
        // More importantly it allows to select between an insert and
        // update sql commands.  (replace into <table> ... might be better.)
        $ckr = new cardkey_row( $v['RFID'] );

        if( $ckr->isFromDB() )
        {
          $elms = array( 'mmbr_id', 'mmbr_secondary_id', 'expires', 'override', 'override_expires' );

          $found_diff = false;
          foreach( $elms as $e )
          {
            if( $ckr[$e]  !== $v[$e] )
            {
              $ckr[$e] = $v[$e];
              $found_diff = true;
            }
          }

          if( $found_diff )
          {
            error_log( 'debug: updating cardkey record for RFID: '.$v['RFID'] );
            if( ! $ckr->do_update() )
              error_log( '$ckr->do_update() failed on ckr: '.$ckr );
            else
              $success = true;  // success is at least one successful db write
          }
        }
        else
        {
          error_log( 'debug: inserting new cardkey record for RFID: '.$v['RFID'] );
          $ckr->set_col_values( $v );
          if( ! $ckr->do_insert() )
            error_log( '$ckr->do_insert() failed on ckr: '.$ckr );
          else
            $success = true;    // success is at least one successful db write
        }
      }
    }

    if( $havetmp && $tmpcount > 0 && $tmpcount === $rowcount  )
    {
      # instead of deleting  rows for non-existant keys, set an 'active' flag to false.
      $dbh->query('update cardkey set active = 0 where rfid not in (select rfid from allkeys)');
      $dbh->query('update cardkey set active = 1 where rfid in (select rfid from allkeys)');
    }
  }

  return $success;
}


/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
