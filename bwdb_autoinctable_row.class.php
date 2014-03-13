<?

/* Author: Christiana Johnson
 * Copyright 2014
 * License GPL v2
 */

require_once('bwdb_keyed_row.class.php');

abstract class bwdb_autoinctable_row extends  bwdb_keyed_row
{
  public function __construct( $keyval = null )
  {
    if( ! is_object($this->keycol) || ! is_a($this->keycol, 'bwdb_column') ) 
      throw new Exception('keycol must be set before the baseclass constructor is called');

    $this->keycol->updateable = false;
    $this->keycol->insertable = false;

    parent::__construct( $keyval );
  }
}


// simplistic, but worthwhile unit tests
# class nosuchtablename98723987_row extends bwdb_autoinctable_row
# {
#   protected $table_name = 'nosuchtablename98723987';
#   public function __construct()
#   {
#     $this->keycol = new bwdb_column( 'thekey' );
#     $this->cols = array( new bwdb_column( 'astring' ), new bwdb_column( 'bstring' ) );
#     parent::__construct();
#   }
# }
# 
# function bwdb_autoinctable_row_rununittests()
# {
#   try {
# 
#   $dbh = bwdb_connection::instance();
#   $sql = "create table nosuchtablename98723987 ( thekey int primary key not null auto_increment, astring char(64), bstring char(64) )";
#   assert( 0 === $dbh->exec( $sql ) );
# 
#   $r = new nosuchtablename98723987_row();
#   $rmax = getrandmax();
#   $keyvals = array();
#   for( $i = 0; $i < 5; $i++ )
#   {
#     $vals = array('astring'=>rand(10000,$rmax), 'bstring'=>rand(10000,$rmax));
#     $r->set_col_values( $vals );
#     assert( $r->do_insert() );
#     $v = null;
#     assert($r->get_key_value( $v ));
#     $keyvals[$i] = $v;
#     print 'inserted: '.$keyvals[$i].', '.implode(', ', $vals )."\n" ;
#   }
#   print "\n" ;
# 
#   for( $i = 0; $i < 5; $i++ )
#   {
#     $r->set_key_value( $keyvals[$i] );
#     assert( $r->do_select() );
#     assert( $r->get_col_values( $vals ) );
#     print 'selected: '. $keyvals[$i].', '.implode(', ', $vals )."\n" ;
#   }
#   print "\n" ;
# 
#   for( $i = 1; $i < 5; $i++ )
#   {
#     $r->set_key_value( $keyvals[$i] );
#     assert( $r->do_delete() );
#     assert( ! $r->do_select() );  // after delete, select on the same key should fail.
#     print 'deleted row with key: '.$i."\n" ;
#   }
#   print "\n" ;
# #
#   $r->set_key_value( $keyvals[0] );
#   assert( $r->do_select() );
#   assert( $r->get_col_values( $vals ) );
#   print 'still exists: '. $keyvals[0].', '.implode(', ', $vals )."\n" ;
# 
# 
#   $sql = "drop table nosuchtablename98723987";
#   assert( 0 === $dbh->exec( $sql ) );
# 
#   } catch ( Exception $e ) {
#     error_log( $e->getMessage() );
#   }
# }

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
