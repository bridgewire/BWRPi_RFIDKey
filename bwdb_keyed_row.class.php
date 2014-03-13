<?

/* Author: Christiana Johnson
 * Copyright 2014
 * License GPL v2
 *
 * I like the row-oriented database stuff because it seems like the most 
 * important web operations apply to single rows. Even when lists of rows are 
 * displayed on a page only one row is edited at a time (mostly).  Also, I like 
 * auto-increment columns everywhere unless some other key *really* makes a lot 
 * more sense, such as, perhaps in this case, the RFID keys we're working with.  
 * So, I like abstract row classes that handle auto-incremented primary key 
 * columns by default. I'm wondering how useful this will be now.  not sure.
 *
 */

require_once('bwdb.php');

class bwdb_column
{
  public $colname;
  public $sel_expr;
  public $ins_expr;
  public $upd_expr;
  public $val;
  public $hasval;
  public $isfromdb;
//  public $oldval;
//  public $hasoldval;
  public $updateable;
  public $insertable;

  public function __construct( $name, $selectexpr=null, $insertexpr=null, $updateexpr=null, $updtbl=true, $inrtble=true, $val=null, $has_val=false )
  {
    $this->colname  = $name;
    $this->sel_expr = ($selectexpr !== null ? $selectexpr : $name);
    $this->ins_expr = ($insertexpr !== null ? $insertexpr : $name);
    $this->upd_expr = ($updateexpr !== null ? $updateexpr : $name);
    $this->val      = $val;
    $this->hasval   = $has_val;
    $this->updateable = $updtbl;
    $this->insertable = $inrtble;
  }
  public function setvalue( $val, $has_val=true )
  {
    //if( $this->hasval && ! $this->hasoldval )
    //{
    //  $this->oldval = $this->val;
    //  $this->hasoldval = true;
    //}

    if( $has_val && $this->isfromdb && $val !== $this->val )
      $this->isfromdb = false;

    $this->val = $val;
    $this->hasval = $has_val; 
  }
  public function setvalue_fromselect( $val )
  {
    //$this->oldval = null
    //$this->hasoldval = false;
    $this->val = $val;
    $this->hasval = true;
    $this->isfromdb = true;
  }

  public function __toString(){ return $this->sel_expr.' == '.$this->val; }
}

abstract class bwdb_keyed_row
{
  // this class (hopefully) makes it easy to focus on data rather than on sql.
  // The model is row-centric, as opposed to cursor table centric though this
  // class may be useful in implementing some kind of cursor-centric class.

  // these must be set by the subclass as part of it's defition.
  protected $table_name = null;
  protected $keycol = null; // new bwdb_column( 'id', 'id', 'id', 'id', false, false, null, false );
  protected $cols = null;

  // these will be used if their set by the class user, but remain blank otherwise
  // these allow for data ops that aren't directly supported by the class.
  protected $select_sql = '';
  protected $insert_sql = '';
  protected $update_sql = '';

  // these are for internal use, but aren't 'private' so that subclasses have access
  protected $col_nums = array();
  protected $isfromdb = false;    // true data came from a select execution.


  // even though this class is abstract it make sense to give it a constructor.
  // the constructor will fail if it is not called via the parent::__construct
  // mechanism. Finally, keycol and cols[] must be properly setup prior to run.
  public function __construct( $keyval = null )
  {
    if( ! is_object($this->keycol) || ! is_a($this->keycol, 'bwdb_column') ) 
      throw new Exception('keycol must be set before the baseclass constructor is called');

    if( ! is_array( $this->cols ) || count( $this->cols ) == 0 ) 
      throw new Exception('cols must be set before the baseclass constructor is called');

    for( $i = 0; $i < count( $this->cols ); $i++ )
      $this->col_nums[ $this->cols[$i]->upd_expr ] = $i;

    if( $keyval !== null )
      $this->keycol->setvalue( $keyval );

    if( $this->keycol->hasval ) 
      $this->do_select();

  }

  public function __toString()
  {
    $str='table: '.$this->table_name."\n";
    $str.=" -- $this->keycol\n";
    if( is_array( $this->cols ) && count( $this->cols ) > 0 )
    {
      foreach( $this->cols as $c )
        $str.=" -- $c\n";
    }
    return $str;
  }

  public function auto_select_statement()
  {
    $sql = 'select ';
    for( $i=0; $i < count( $this->cols ); $i++ )
      $sql .= ($i === 0 ? '' : ', ' ).$this->cols[$i]->sel_expr;

    $sql .= ' from '.$this->table_name.' where '.$this->keycol->sel_expr.' = ?';
    return $sql;
  }

  // public function auto_select()
  public function do_select()
  {
    $success = false;
    if( $this->keycol->hasval )
    {
      $dbh = bwdb_connection::instance();
      $stmt = null;
      $sql = (strlen( $this->select_sql ) > 0 ? $this->select_sql : $this->auto_select_statement());
      $stmt = $dbh->prepare( $sql );
      $stmt->execute( array( $this->keycol->val ) );
      $row = $stmt->fetch( PDO::FETCH_NUM );

      if( $row )
      {
        $rows = count($row);
        //error_log('debug: rows == '.$rows);
        if( $rows > 0 )  // sanity check
        {
          $success = true;
          for( $i = 0; $i < $rows; $i++ )
            $this->cols[$i]->setvalue_fromselect( $row[$i] );
        }
      }
      $stmt->closeCursor(); // necessary?
    }

    $this->isfromdb = $success;

    return $success;  // or throw?
  }

  public function auto_insert_statement()
  {
    $sql = 'insert into '.$this->table_name.' ( ' ;
    $vals_list = ' values (';
    $c = 0;

    if( $this->keycol->insertable && $this->keycol->hasval )
    {
      $sql       .=     $this->keycol->ins_expr;
      $vals_list .= ':'.$this->keycol->ins_expr;
      $c++;
    }

    for( $i=0; $i < count( $this->cols ); $i++ )
    {
      if( $this->cols[$i]->insertable && $this->cols[$i]->hasval )
      {
        $sql       .= ($c === 0 ? ''  : ', '  ).$this->cols[$i]->ins_expr;
        $vals_list .= ($c === 0 ? ':' : ', :' ).$this->cols[$i]->ins_expr ;
        $c++;
      }
    }
    $sql .= ')'.$vals_list.')';

    if( $c == 0 )
      $sql = null;

    return $sql;
  }

  // public function auto_insert()
  public function do_insert()
  {
    $success = false;
    $sql = null;

    if( ! $this->isfromdb )
    {
      if( $this->keycol->insertable )
      {
        if( ! $this->keycol->hasval )
          throw Exception('no no no no no');

        $sql = (strlen( $this->insert_sql ) > 0 ? $this->insert_sql : $this->auto_insert_statement());
      }
      elseif( ! $this->keycol->insertable )
      {
        $this->keycol->hasval = false; // invalidate whatever value is present, if any.
        $sql = (strlen( $this->insert_sql ) > 0 ? $this->insert_sql : $this->auto_insert_statement());
      }

      if( $sql !== null )
      {
        $dbh = bwdb_connection::instance();
        $stmt = $dbh->prepare( $sql );
        $c = 0;

        if( $this->keycol->insertable )
        {
          $stmt->bindParam( ':'.$this->keycol->ins_expr, $this->keycol->val );
          $c++;
        }

        for( $i=0; $i < count( $this->cols ); $i++ )
        {
          if( $this->cols[$i]->insertable && $this->cols[$i]->hasval )
          {
            $stmt->bindParam( ':'.$this->cols[$i]->ins_expr, $this->cols[$i]->val );
            $c++;
          }
        }

        if( $c > 0 && $stmt->execute() )
        {
          if( ! $this->keycol->insertable )
          {
            $this->keycol->val = $dbh->lastInsertId();
            //error_log('debug: after insert with non-insertable key, key value is: '.$this->keycol->val );
            assert( preg_match('/^\d+$/', $this->keycol->val) );
            $this->keycol->hasval = true;
          }
          $success = true;
        }
      }
    }

    return $success;  // or throw?
  }


  public function auto_update_statement()
  {
    $sql = 'update '.$this->table_name.' set ' ;
    $c=0;

    for( $i=0; $i < count( $this->cols ); $i++ )
    {
      if( $this->cols[$i]->updateable &&  $this->cols[$i]->hasval && ! $this->cols[$i]->isfromdb )
      {
        $sql .= ($c === 0 ? '' : ', ' ).$this->cols[$i]->upd_expr .' = :'.$this->cols[$i]->upd_expr;
        $c++;
      }
    }
    $sql .= ' where '.$this->keycol->upd_expr.' = '.$this->keycol->val;

    if( $c == 0 )
      $sql = null;

    return $sql;
  }

  // public function auto_update()
  public function do_update()
  {
    $success = false;
    if( $this->keycol->hasval )
    {
      $sql = (strlen( $this->update_sql ) > 0 ? $this->update_sql : $this->auto_update_statement());

      if( $sql !== null )
      {
        $dbh = bwdb_connection::instance();
        $stmt = $dbh->prepare( $sql );
        $c = 0;
        for( $i=0; $i < count( $this->cols ); $i++ )
        {
          if( $this->cols[$i]->updateable && $this->cols[$i]->hasval && ! $this->cols[$i]->isfromdb )
          {
            $stmt->bindParam( ':'.$this->cols[$i]->upd_expr, $this->cols[$i]->val );
            $c++;
          }
        }

        if( $c > 0 && $stmt->execute() )
          $success = true;
      }
    }
    return $success;  // or throw?
  }

  public function do_delete()
  {
    $success = false;
    if( $this->keycol->hasval )
    {
      $sql = 'delete from '.$this->table_name.' where '.$this->keycol->upd_expr.' = ?';
      $dbh = bwdb_connection::instance();
      $stmt = $dbh->prepare( $sql );
      $success = $stmt->execute( array( $this->keycol->val ) );
    }
    return $success;
  }

  public function unset_key_value() { $this->keycol->hasval = false; }

  public function   set_key_value( $keyval, $refresh_data=true )
  {
    $this->keycol->val = $keyval;
    $this->keycol->hasval = true;
    if( $refresh_data )
      $this->do_select();
  }

  public function get_key_value( &$keyval )
  {
    $success = $this->keycol->hasval;
    if( $success )
      $keyval = $this->keycol->val;
    return $success;
  }

  // short way to 'get' the value of a single column
  // instead of returning true or false this throws an exception on any failure
  public function g( $c )
  {
    if( $c === $this->keycol->colname )
    {
      $v=null;
      if( $this->get_key_value( $v ) )
        return $v;
    }
    else
    {
      $i = -1;
      if( ! is_int( $c ) && isset( $this->col_nums[ $c ] ) )
          $i = $this->col_nums[ $c ];
      elseif( is_int( $c ) && $c >= 0 && $c <= count($this->cols) )
        $i = $k;

      if( $i > -1 && $this->cols[ $i ]->hasval )
        return $this->cols[ $i ]->val;
    }

    // else
    throw new Exception(__FILE__.':'.__LINE__.': either the specified column: '.$c.' does not exist, or it has no value to get');
  }

  // short way to 'set' the value of a single column
  //
  // // example:
  // require("bwdb_rowdefs.php");
  // $ck = new cardkey_row("4F-00-A8-F9-76-68"); $c="mmbr_id"; 
  // $v = $ck->g($c);
  // print( "$c equals: ".($v === null ? "null" : $v)."\n" );
  // $ck->s("RFID","4F-00-A8-E6-C1-C0");  // this is the table key, so updates row when it's set
  // $v=$ck->g($c);
  // print( "$c equals: ".($v === null ? "null" : $v)."\n" );  '
  //
  public function s( $c, $v, $refresh_data=true )
  {
    if( $c === $this->keycol->colname )
    {
      $this->isfromdb = false; // if( $refresh_data ) then this will likely be true upon return
      $this->set_key_value( $v, $refresh_data );
      return;
    }

    $i = -1;
    if( ! is_int( $c ) && isset( $this->col_nums[ $c ] )  )
      $i = $this->col_nums[ $c ];
    elseif( is_int( $c ) && $c >= 0 && $c <= count($this->cols) )
      $i = $k;

    if( $i > -1 )
    {
      $this->isfromdb = false; // if anything changes, the row is not strictly from db.
      $this->cols[ $i ]->setvalue( $v );
    }
    else
      throw new Exception(__FILE__.':'.__LINE__.': the specified column: '.$c.' does not exist');
  }


  public function set_col_values( $colvals )
  {
    $set_count = 0; // XXX this is not actually useful, because if($get_count !== count($colvals)) then throw
    $cc = count($this->cols);

    foreach( $colvals as $k => $v )
    {
      $i = -1;

      if( ! is_int($k) && isset( $this->col_nums[ $k ] ) )
        $i = $this->col_nums[ $k ];
      elseif( is_int( $k ) && $k >= 0 && $k < $cc )
        $i = $k;

      if( $i > -1 )
      {
        $this->isfromdb = false;
        $this->cols[ $i ]->setvalue( $v );
        $set_count++;
      }
      else
        throw new Exception("no such column: \"$k\" in ".__CLASS__);
    }
    return $set_count;
  }

  public function get_col_values( &$colvals )
  {
    $get_count = 0; // XXX this is not actually useful, because if($get_count !== count($colvals)) then throw
    $cc = count($this->cols);

    foreach( $colvals as $k => $v )
    {
      $i = -1;

      if( ! is_int($k) && isset( $this->col_nums[ $k ] ) )
        $i = $this->col_nums[ $k ];
      elseif( is_int( $k ) && $k >= 0 && $k < $cc )
        $i = $k;

      if( $i < 0 )
        throw new Exception("no such column: \"$k\" in ".__CLASS__);

      $colvals[$k] = $this->cols[$i]->val;
      $get_count++;
    }
    return $get_count;
  }

  public function found_in_db()
  {
    return $this->isfromdb;
  }
}

# class nosuchtablename98723987_row extends bwdb_keyed_row
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
# function bwdb_keyed_row_rununittests()
# {
#   try {
# 
#   $dbh = bwdb_connection::instance();
#   $sql = "create table nosuchtablename98723987 ( thekey int primary key, astring char(64), bstring char(64) )";
#   assert( $dbh->exec( $sql ) );
# 
#   $r = new nosuchtablename98723987_row();
#   $rmax = getrandmax();
#   // $keyvals = array();
#   for( $i = 0; $i < 5; $i++ )
#   {
#     $vals = array('astring'=>rand(10000,$rmax), 'bstring'=>rand(10000,$rmax));
#     $r->set_key_value( $i, false );
#     $r->set_col_values( $vals );
#     assert( $r->do_insert() );
#     // $keyvals[$i] = '';
#     //assert($r->get_key_value( $keyvals[$i] ));
#     //assert( $keyvals[$i] == $i );
#     print 'inserted: '.$i.', '.implode(', ', $vals )."\n" ;
#   }
#   print "\n" ;
# 
#   for( $i = 0; $i < 5; $i++ )
#   {
#     $r->set_key_value( $i );
#     if( $r->do_select() )
#     {
#       assert( $r->get_col_values( $vals ) );
#       print( "success do_select() -- " );
#       print 'got: '.$i.', '.implode(', ', $vals )."\n" ;
#     }
#     else
#       print( "failed on do_select()\n" );
#   }
#   print "\n" ;
# 
#   for( $i = 1; $i < 5; $i++ )
#   {
#     $r->set_key_value( $i );
#     assert( $r->do_delete() );
#     print( ( $r->do_select() ? 'failed while' : 'successfully' )." deleted row with key: '.$i."\n" );
#   }
#   print "\n" ;
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
