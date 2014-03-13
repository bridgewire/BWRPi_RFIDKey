<?

/* Author: Christiana Johnson
 * Copyright 2014
 * License GPL v2
 *
 */

require_once('bwdb_autoinctable_row.class.php');

class cardkey_log_row extends bwdb_autoinctable_row
{
  protected $table_name = 'cardkey_log';

  public function __construct( $cklog_id = null ) 
  { 
    $this->keycol = new bwdb_column( 'cardkey_log_id' );

    $this->cols = array( 
      new bwdb_column( 'RFID' ),
      new bwdb_column( 'mmbr_id' ),
      new bwdb_column( 'mmbr_secondary_id' ),
      new bwdb_column( 'stamp', null, null, null, false, false ), 
      new bwdb_column( 'event' ), 
      new bwdb_column( 'override' ), 
      new bwdb_column( 'note' ) );

    parent::__construct( $cklog_id );
  }
}

class cardkey_row extends bwdb_keyed_row
{
  protected $table_name = 'cardkey';

  public function __construct( $rfid = null ) 
  { 
    $this->keycol = new bwdb_column( 'RFID' );

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
  protected $table_name = 'members';

  public function __construct( $mmbr_id = null ) 
  { 
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
      new bwdb_column( 'rfid' ) );

    parent::__construct( $mmbr_id );
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
