<?php
/**
 * includes/functions.php
 *
 * @package default
 */


$errors = array();

/*--------------------------------------------------------------*/
/* Function for Remove escapes special
 /* characters in a string for use in an SQL statement
 /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $str
 * @return unknown
 */
function real_escape($str) {
	global $con;
	$escape = mysqli_real_escape_string($con, $str);
	return $escape;
}


/*--------------------------------------------------------------*/
/* Function for Remove html characters
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $str
 * @return unknown
 */
function remove_junk($str) {
	$str = nl2br($str);
	$str = trim($str);
	$str = stripslashes($str);
	$str = strip_tags($str);
	$str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	return $str;
}


/*--------------------------------------------------------------*/
/* HTML-safe output helper — shorthand for htmlspecialchars
/*--------------------------------------------------------------*/

/**
 * Escape a string for safe HTML output.
 * Use this on ALL dynamic data before echoing into HTML context.
 *
 * @param mixed $str
 * @return string
 */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}


/*--------------------------------------------------------------*/
/* Function for Uppercase first character
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $str
 * @return unknown
 */
function first_character($str) {
	$val = str_replace('-', " ", $str);
	$val = ucfirst($val);
	return $val;
}


/*--------------------------------------------------------------*/
/* Function for Checking input fields not empty
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $var
 * @return unknown
 */
function validate_fields($var) {
	global $errors;
	foreach ($var as $field) {
		$val = remove_junk($_POST[$field]);
		if (isset($val) && $val=='') {
			$errors = $field ." can't be blank.";
			return $errors;
		}
	}
}


/*--------------------------------------------------------------*/
/* Function for Display Session Message
   Ex echo displayt_msg($message);
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $msg (optional)
 * @return unknown
 */
function display_msg($msg ='') {
	$output = array();
	if (!empty($msg)) {
		foreach ($msg as $key => $value) {
			$output  = "<div class=\"alert alert-{$key}\">";
			$output .= "<a href=\"#\" class=\"close\" data-dismiss=\"alert\">&times;</a>";
			$output .= remove_junk(first_character($value));
			$output .= "</div>";
		}
		return $output;
	} else {
		return "" ;
	}
}


/*--------------------------------------------------------------*/
/* Function for redirect
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $url
 * @param unknown $permanent (optional)
 */
function redirect($url, $permanent = false) {
	if (headers_sent() === false) {
		header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
	}

	exit();
}


/*--------------------------------------------------------------*/
/* Function for find out total sale price, cost price and profit
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $totals
 * @return unknown
 */
function total_price($totals) {
	$sum = 0;
	$sub = 0;
	$profit = 0;
	foreach ($totals as $total ) {
		$sum += $total['total_selling_price'];
		$sub += $total['total_buying_price'];
		$profit = $sum - $sub;
	}
	return array($sum, $profit);
}


/*--------------------------------------------------------------*/
/* Function for Readable date time
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $str
 * @return unknown
 */
function read_date($str) {
	if ($str)
		//      return date('F j, Y, g:i:s a', strtotime($str));
		return date('M j, Y, g:i:s a', strtotime($str));
	else
		return null;
}


/*--------------------------------------------------------------*/
/* Function for  Readable Make date time
/*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function make_date() {
	return date("Y-m-d H:i:s");
}


/*--------------------------------------------------------------*/
/* Function for  Readable date time
/*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function count_id() {
	static $count = 1;
	return $count++;
}


/*--------------------------------------------------------------*/
/* Function for Creting random string
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $length (optional)
 * @return unknown
 */
function randString($length = 5) {
	$str='';
	$cha = "0123456789abcdefghijklmnopqrstuvwxyz";

	for ($x=0; $x<$length; $x++)
		$str .= $cha[mt_rand(0, strlen($cha) - 1)];
	return $str;
}


/*--------------------------------------------------------------*/
/* CSRF Protection
/*--------------------------------------------------------------*/

/**
 * Generate a CSRF token and store it in the session.
 *
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field containing the CSRF token.
 * Call this inside every <form method="post">.
 *
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify the CSRF token from a POST request.
 *
 * Returns true if valid or not a POST request (GET, HEAD, etc. are
 * side-effect-free, so CSRF protection is not needed).
 * Returns false if the token is missing or does not match.
 *
 * Callers should handle the failure response (redirect, error page, etc.).
 *
 * @return bool
 */
function verify_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}
