<?php
  require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-form-submitter.php';
  const COOKIE_DUR  = 86400 * 3; // 3 days
  const COOKIE_LEN  = 32;
  const DSN         = 'mysql:host=localhost;dbname=formsubmit_live';
  const UN          = 'formsubmit_rw';
  const PW          = 'keeXP3Qxuh6K';
  $allow_cookies    = isset( $_COOKIE['CookiePolicy'] ) && preg_match('/\bf1\b/',$_COOKIE['CookiePolicy']);
  $session          = '';
  $is_get           = $_SERVER['REQUEST_METHOD'] === 'GET';
  $code             = $is_get ? $_GET[ 'code' ] : $_POST[ 'code' ];
  $cookie_name      = 'fs-cookie-'.$code;
  $dbh              = false;
  $session_code     = '';

  if( array_key_exists( $cookie_name, $_COOKIE ) ) {
    if( $allow_cookies ) {
      $session_code = $_COOKIE[ $cookie_name ];
    } else {
      setcookie( $cookie_name, '', 1 );
    }
  }
  //$session_code = 'XX';
  $form = new FormSubmitter();
  $form->set_code( $code )->fetch( );
  //print_r( $form );exit;
  error_log( $session_code );
  if( $is_get ) {
    if( $session_code ) { // Get details...
      $d = get_session_from_db( $session_code );
      if( $d ) {
        header( 'Content-Type: application/json' );
        print json_encode( $d['userdata'] );
      } else {
        http_response_code( 404 );
      }
    } else {
      http_response_code( 204 );
    }
    exit;
  }
  error_log("POST");
  if( !$session_code && isset( $_POST[ 'session_code' ] ) ) {
    $session_code = $_POST[ 'session_code' ];
  }
  if( !$session_code ) {
    $session_code = get_session_str();
  } 
  if( $allow_cookies ) {
    setcookie( $cookie_name, $session_code, time()+COOKIE_DUR, '', '', true, true );
  }
  
  if( $_POST['__action'] === 'confirm' ) {
    // We need to add code to handle submission here!!!
    write_session_to_db( $session_code, $_POST, true );
    // Get call back URL and submit data....
    // or get email list and send emails...
    setcookie( $cookie_name, '', 1 ); // Clear cookie!
  } else {
    write_session_to_db( $session_code, $_POST );
    header( 'Content-Type: application/json' );
    print json_encode( ['code' => $session_code] );
  } 
  
  
  function get_session_str( $len = COOKIE_LEN ) {
    return base64_encode(openssl_random_pseudo_bytes( $len ));
  }
  function get_pdo() {
    return new PDO( DSN, UN, PW );
  }
  function write_session_to_db( $session_code, $data, $completed='no' ) {
    $dbh = get_pdo();
    error_log( $dbh );
    $t = get_session_from_db( $session_code );
    if( $t && $t['completed'] === 'yes' ) {
      return;
    }
    error_log( $session_code );
    error_log( print_r( $data, 1 ) );
    $stmt = $dbh->prepare(
      'replace into submission (code,userdata,completed)
               values( :code, :data, :completed )' );
    return $stmt->execute( [ 'code' => $session_code, 'data' => json_encode( $data ), 'completed' => $completed ] );    
  }

  function get_session_from_db( $session_code ) {
    $dbh = get_pdo();
    $stmt = $dbh->prepare('select * from submission where code = :code' );
    $stmt->execute( ['code' => $session_code ] );
    $data = $stmt->fetch( PDO::FETCH_ASSOC );
    if( $data ) {
      $data['userdata'] = json_decode( $data['userdata'], true );
    }
    return $data;
  }
  
  
