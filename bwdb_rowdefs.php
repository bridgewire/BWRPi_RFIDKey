<?

/* Author: Christiana Johnson
 * Copyright 2014
 * License GPL v2
 *
 */

#define('BWTablePrefix','bw_');
define('BWTablePrefix','');

require_once('bwdb_autoinctable_row.class.php');

class cardkey_log_row extends bwdb_autoinctable_row
{
  public function __construct( $cklog_id = null ) 
  { 
    $this->table_name = BWTablePrefix.'cardkey_log';
    $this->keycol = new bwdb_column( 'cardkey_log_id' );

    $this->cols = array( 
      new bwdb_column( 'rfid' ),
      new bwdb_column( 'mmbr_id' ),
      new bwdb_column( 'mmbr_secondary_id' ),
      new bwdb_column( 'stamp', null, null, null, false, false ), 
      new bwdb_column( 'event' ), 
      new bwdb_column( 'override' ), 
      new bwdb_column( 'reason' ), 
      new bwdb_column( 'note' ) );

    parent::__construct( $cklog_id );
  }
}

class cardkey_row extends bwdb_keyed_row
{
  public function __construct( $rfid = null ) 
  { 
    $this->table_name = BWTablePrefix.'cardkey';
    $this->keycol = new bwdb_column( 'rfid' );

    $this->cols = array( 
      new bwdb_column( 'mmbr_id' ),
      new bwdb_column( 'mmbr_secondary_id' ),
      new bwdb_column( 'expires' ), 
      new bwdb_column( 'override' ), 
      new bwdb_column( 'override_expires' ) 
    );

    parent::__construct( $rfid );
  }
}

class bwdb_member_row extends bwdb_autoinctable_row
{
  public function __construct( $mmbr_id = null ) 
  { 
    $this->table_name = BWTablePrefix.'members';
    $this->keycol = new bwdb_column( 'mmbr_id' );

    $this->cols = array( 
      new bwdb_column( 'fullname' ),
      new bwdb_column( 'firstname' ),
      new bwdb_column( 'lastname' ),
      new bwdb_column( 'address1' ),
      new bwdb_column( 'address2' ),
      new bwdb_column( 'city' ),
      new bwdb_column( 'state' ),
      new bwdb_column( 'zip' ),
      new bwdb_column( 'phone' ),
      new bwdb_column( 'email' ),
      new bwdb_column( 'dob' ),
      new bwdb_column( 'familymembership' ),
      new bwdb_column( 'rank' ),
      new bwdb_column( 'annualmember' ),
      new bwdb_column( 'expiredate' ),
      new bwdb_column( 'idverifiedby' ),
      new bwdb_column( 'applicationdate' ),
      new bwdb_column( 'waiverdate' ),
      new bwdb_column( 'rfid' ) ); //,
      //new bwdb_column( 'pwsalt' ),
      //new bwdb_column( 'pwhash' ) );

    parent::__construct( $mmbr_id );
  }
}

class bwdb_txns_row extends bwdb_autoinctable_row
{
  public function __construct( $txn_id = null ) 
  { 
    $this->table_name = BWTablePrefix.'txns';
    $this->keycol = new bwdb_column( 'txn_id' );

    $this->cols = array( 
      new bwdb_column( 'mdbtablelink' ),
      new bwdb_column( 'txn_changedate' ),
      new bwdb_column( 'txn_entrydate' ),
      new bwdb_column( 'txn_date' ),
      new bwdb_column( 'amount' ),
      new bwdb_column( 'type' ),
      new bwdb_column( 'method' ),
      new bwdb_column( 'fund' ),
      new bwdb_column( 'notes' ),
      new bwdb_column( 'enteredby_id' ),
      new bwdb_column( 'mmbr_id' ),
      new bwdb_column( 'mmbr_period_start' ),
      new bwdb_column( 'mmbr_period_end' ),
      new bwdb_column( 'paidfamily' ),
      new bwdb_column( 'cleared' ) );

    parent::__construct( $txn_id );
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
