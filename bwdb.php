<?

/* Author: Christiana Johnson ("Author" is loosely applied here.)
 * Copyright 2014
 * License GPL v2
 *
 * simplest kind of mysql wrapper
 */


class bwdb_connection
{
  private $db_host = 'localhost';
  private $db_name = 'BridgewireMembers';
  private $db_user = 'bw';
  private $db_pass = '1v.Gw1zMi';

  protected $dbh = NULL;

  // singleton instanciator
  public static function instance()
  {
    static $inst = null;
    if( $inst === null )
      $inst = new bwdb_connection();

    return $inst; // return a copy
  }

  // public function prepare( $s )  { return $this->dbh->prepare( $s, array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true) ); }
  public function prepare( $s )  { return $this->dbh->prepare( $s ); }
  public function query( $s )  { return $this->dbh->query( $s ); }
  public function lastInsertId() { return $this->dbh->lastInsertId(); }
  public function exec( $p ) { return $this->dbh->exec( $p ); }

  // yes, it's a singleont, even though they're "evil". all coding is evil by degree.
  protected function __construct()
  {
    $PDOinitializer = 'mysql:host='.$this->db_host.';dbname='.$this->db_name;
    $this->dbh = new PDO( $PDOinitializer, $this->db_user, $this->db_pass ); 
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // use exceptions to handle all errors
  }
}

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
