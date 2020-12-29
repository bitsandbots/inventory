<?php
/**
 * includes/sql.php
 *
 * @package default
 */


require_once 'load.php';

/*--------------------------------------------------------------*/
/* Function for find all database table rows by table name
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @return unknown
 */
function find_all($table) {
	global $db;
	if (tableExists($table)) {
		return find_by_sql("SELECT * FROM ".$db->escape($table));
	}
}


/*--------------------------------------------------------------*/
/* Function for Perform queries
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $sql
 * @return unknown
 */
function find_by_sql($sql) {
	global $db;
	$result = $db->query($sql);
	$result_set = $db->while_loop($result);
	return $result_set;
}


/*--------------------------------------------------------------*/
/*  Function for Find data from table by id
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @param unknown $id
 * @return unknown
 */
function find_by_id($table, $id) {
	global $db;
	$id = (int)$id;
	if (tableExists($table)) {
		$sql = $db->query("SELECT * FROM {$db->escape($table)} WHERE id='{$db->escape($id)}' LIMIT 1");
		if ($result = $db->fetch_assoc($sql))
			return $result;
		else
			return null;
	}
}


/*--------------------------------------------------------------*/
/*  Function for Find data from table by name
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @param unknown $name
 * @return unknown
 */
function find_by_name($table, $name) {
	global $db;
	if (tableExists($table)) {
		$sql = $db->query("SELECT * FROM {$db->escape($table)} WHERE name='{$db->escape($name)}' LIMIT 1");
		if ($result = $db->fetch_assoc($sql))
			return $result;
		else
			return null;
	}
}


/*--------------------------------------------------------------*/
/* Function for Delete data from table by id
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @param unknown $id
 * @return unknown
 */
function delete_by_id($table, $id) {
	global $db;
	if (tableExists($table)) {
		$sql = "DELETE FROM ".$db->escape($table);
		$sql .= " WHERE id=". $db->escape($id);
		$sql .= " LIMIT 1";
		$db->query($sql);
		return ($db->affected_rows() === 1) ? true : false;
	}
}


/*--------------------------------------------------------------*/
/* Function for Delete data from table by ip
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @param unknown $remote_ip
 * @return unknown
 */
function delete_by_ip($table, $remote_ip) {
	global $db;
	if (tableExists($table)) {
		$sql = "DELETE FROM ".$db->escape($table);
		$sql .= " WHERE remote_ip='". $db->escape($remote_ip)."'";

		$db->query($sql);
		return ($db->affected_rows() >= 1) ? true : false;
	}
}


/*--------------------------------------------------------------*/
/* Function for Count id  By table name
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @return unknown
 */
function count_by_id($table) {
	global $db;
	if (tableExists($table)) {
		$sql    = "SELECT COUNT(id) AS total FROM ".$db->escape($table);
		$result = $db->query($sql);
		return $db->fetch_assoc($result);
	}
}


/*--------------------------------------------------------------*/
/* Function for last id  By table name
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @return unknown
 */
function last_id($table) {
	global $db;
	if (tableExists($table)) {
		$sql    = "SELECT id FROM ".$db->escape($table) . " ORDER BY id DESC LIMIT 1";
		$result = $db->query($sql);
		return $db->fetch_assoc($result);
	}
}


/*--------------------------------------------------------------*/
/* Determine if database table exists
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $table
 * @return unknown
 */
function tableExists($table) {
	global $db;
	$table_exit = $db->query('SHOW TABLES FROM '.DB_NAME.' LIKE "'.$db->escape($table).'"');
	if ($table_exit) {
		if ($db->num_rows($table_exit) > 0)
			return true;
		else
			return false;
	}
}


/*--------------------------------------------------------------*/
/* Login with the data provided in $_POST,
 /* coming from the login form.
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $username (optional)
 * @param unknown $password (optional)
 * @return unknown
 */
function authenticate($username='', $password='') {
	global $db;
	$username = $db->escape($username);
	$password = $db->escape($password);
	$sql  = sprintf("SELECT id,username,password,user_level FROM users WHERE username ='%s' LIMIT 1", $username);
	$result = $db->query($sql);
	if ($db->num_rows($result)) {
		$user = $db->fetch_assoc($result);
		$password_request = sha1($password);
		if ($password_request === $user['password'] ) {
			return $user['id'];
		}
	}
	return false;
}


/*--------------------------------------------------------------*/
/* Find current log in user by session id
  /*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function current_user() {
	static $current_user;
	global $db;
	if (!$current_user) {
		if (isset($_SESSION['user_id'])):
			$user_id = intval($_SESSION['user_id']);
		$current_user = find_by_id('users', $user_id);
		endif;
	}
	return $current_user;
}


/*--------------------------------------------------------------*/
/* Find all user by
  /* Joining users table and user gropus table
  /*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function find_all_user() {
	global $db;
	$results = array();
	$sql = "SELECT u.id,u.name,u.username,u.user_level,u.status,u.last_login,";
	$sql .="g.group_name ";
	$sql .="FROM users u ";
	$sql .="LEFT JOIN user_groups g ";
	$sql .="ON g.group_level=u.user_level ORDER BY u.name ASC";
	$results = find_by_sql($sql);
	return $results;
}


/*--------------------------------------------------------------*/
/* Function to update the last log in of a user
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $user_id
 * @return unknown
 */
function updateLastLogIn($user_id) {
	global $db;
	$date = make_date();
	$sql = "UPDATE users SET last_login='{$date}' WHERE id ='{$user_id}' LIMIT 1";
	$result = $db->query($sql);
	return $result && $db->affected_rows() === 1 ? true : false;
}


/*--------------------------------------------------------------*/
/* Function to log the action of a user
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $user_id
 * @param unknown $remote_ip
 * @param unknown $action
 * @return unknown
 */
function logAction($user_id, $remote_ip, $action) {
	global $db;
	$date = make_date();
	$sql  = "INSERT INTO log (user_id,remote_ip,action,date)";
	$sql .= " VALUES ('{$user_id}','{$remote_ip}','{$action}','{$date}')";
	$result = $db->query($sql);
	return $result && $db->affected_rows() === 1 ? true : false;
}


/*--------------------------------------------------------------*/
/* Find all Group name
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $val
 * @return unknown
 */
function find_by_groupName($val) {
	global $db;
	$sql = "SELECT group_name FROM user_groups WHERE group_name = '{$db->escape($val)}' LIMIT 1 ";
	$result = $db->query($sql);
	return $db->num_rows($result) === 0 ? true : false;
}


/*--------------------------------------------------------------*/
/* Find group level
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $level
 * @return unknown
 */
function find_by_groupLevel($level) {
	global $db;
	$sql = " ";
	$sql = $db->query("SELECT group_status FROM user_groups WHERE group_level = '{$db->escape($level)}' LIMIT 1");
	if ($result = $db->fetch_assoc($sql))
		return $result;
	else
		return null;
}


/*--------------------------------------------------------------*/
/* Function for cheaking which user level has access to page
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $require_level
 * @return unknown
 */
function page_require_level($require_level) {
	global $session;
	$current_user = current_user();
	$login_level = find_by_groupLevel($current_user['user_level']);
	//if user not login
	if (!$session->isUserLoggedIn()):
		$session->msg('d', 'Please login...');
	redirect('index.php', false);
	//if Group status Deactive
	elseif ($current_user['status'] === '0'):
		$session->msg('d', 'Your account has been disabled!');
	redirect('../users/home.php', false);
	elseif ($login_level['group_status'] === '0'):
		$session->msg('d', 'Your group has been disabled!');
	redirect('../users/home.php', false);
	//cheackin log in User level and Require level is Less than or equal to
	elseif ($current_user['user_level'] <= (int)$require_level):
		return true;
	else:
		$session->msg("d", "Sorry! you dont have permission to view the page.");
	redirect('../users/home.php', false);
	endif;

}


/*--------------------------------------------------------------*/
/* Function for Finding all product name
   /* JOIN with category  and media database table
   /*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function join_product_table() {
	global $db;
	$sql  =" SELECT p.id,p.name,p.sku,p.location,p.quantity,p.buy_price,p.sale_price,p.media_id,p.date,c.name";
	$sql  .=" AS category,m.file_name AS image";
	$sql  .=" FROM products p";
	$sql  .=" LEFT JOIN categories c ON c.id = p.category_id";
	$sql  .=" LEFT JOIN media m ON m.id = p.media_id";
	$sql  .=" ORDER BY p.id ASC";
	return find_by_sql($sql);

}


/*--------------------------------------------------------------*/
/* Function for Finding all product name
  /* Request coming from ajax.php for auto suggest
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $product_name
 * @return unknown
 */
function find_product_by_title($product_name) {
	global $db;
	$p_name = remove_junk($db->escape($product_name));
	$sql = "SELECT name FROM products WHERE name like '%$p_name%' LIMIT 5";
	$result = find_by_sql($sql);
	return $result;
}


/*--------------------------------------------------------------*/
/* Function for Finding all product info by product title
  /* Request coming from ajax.php
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $title
 * @return unknown
 */
function find_all_product_info_by_title($title) {
	global $db;
	$sql  = "SELECT * FROM products ";
	$sql .= " WHERE name ='{$title}'";
	$sql .=" LIMIT 1";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Finding all product name
  /* Request coming from ajax_sku.php for auto suggest
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $product_sku
 * @return unknown
 */
function find_product_by_sku($product_sku) {
	global $db;
	$p_sku = $db->escape($product_sku);
	$sql = "SELECT sku FROM products WHERE sku like '%$p_sku%' LIMIT 5";
	$result = find_by_sql($sql);
	return $result;
}


/*--------------------------------------------------------------*/
/* Function for Finding all product info by product title
  /* Request coming from ajax_sku.php
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $product_sku
 * @return unknown
 */
function find_all_product_info_by_sku($product_sku) {
	global $db;
	$sql  = "SELECT * FROM products ";
	$sql .= " WHERE sku ='{$product_sku}'";
	$sql .=" LIMIT 1";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Finding by customer name
  /* Request coming from ajax_customer.php for auto suggest
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $customer_name
 * @return unknown
 */
function find_customer_by_name($customer_name) {
	global $db;
	$customer = remove_junk($db->escape($customer_name));
	$sql = "SELECT name FROM customers WHERE name like '%$customer%' LIMIT 5";
	$result = find_by_sql($sql);
	return $result;
}


/*--------------------------------------------------------------*/
/* Function for Finding all product info by product title
  /* Request coming from ajax_customer.php
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $customer_name
 * @return unknown
 */
function find_all_customer_info_by_name($customer_name) {
	global $db;
	$sql  = "SELECT * FROM customers ";
	$sql .= " WHERE name ='{$customer_name}'";
	$sql .=" LIMIT 1";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Finding all product search
  /* Request coming from ajax_product.php for auto suggest
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $product_search
 * @return unknown
 */
function find_products_by_search($product_search) {
	global $db;
	$p_search = remove_junk($db->escape($product_search));
	$sql = "SELECT * FROM products WHERE ( name like '%$p_search%' OR sku like '%$p_search%' OR description like '%$p_search%' ) LIMIT 5";
	$result = find_by_sql($sql);
	return $result;
}


/*--------------------------------------------------------------*/
/* Function for Finding all product info by product search
  /* Request coming from ajax_product.php
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $search
 * @return unknown
 */
function find_all_product_info_by_search($search) {
	global $db;
	$p_search = remove_junk($db->escape($search));
	$sql  =" SELECT p.id,p.name,p.sku,p.location,p.quantity,p.buy_price,p.sale_price,p.media_id,p.date,c.name";
	$sql  .=" AS category,m.file_name AS image";
	$sql  .=" FROM products p";
	$sql  .=" LEFT JOIN categories c ON c.id = p.category_id";
	$sql  .=" LEFT JOIN media m ON m.id = p.media_id";
	$sql  .=" WHERE ( p.name like '%$p_search%' OR p.sku like '%$p_search%' OR p.description like '%$p_search%' )";
	$sql  .=" ORDER BY p.id ASC";


	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Finding all product by category
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $cat
 * @return unknown
 */
function find_products_by_category($cat) {
	global $db;
	$sql  =" SELECT p.id,p.name,p.sku,p.location,p.quantity,p.buy_price,p.sale_price,p.media_id,p.date,c.name";
	$sql  .=" AS category,m.file_name AS image";
	$sql  .=" FROM products p";
	$sql  .=" LEFT JOIN categories c ON c.id = p.category_id";
	$sql  .=" LEFT JOIN media m ON m.id = p.media_id";
	$sql  .=" WHERE c.id = '{$cat}'";
	$sql  .=" ORDER BY p.id ASC";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Increase product quantity
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $qty
 * @param unknown $p_id
 * @return unknown
 */
function increase_product_qty($qty, $p_id) {
	global $db;
	$qty = (int) $qty;
	$id  = (int)$p_id;
	$sql = "UPDATE products SET quantity=quantity +'{$qty}' WHERE id = '{$id}'";
	$result = $db->query($sql);
	return $db->affected_rows() === 1 ? true : false;

}


/*--------------------------------------------------------------*/
/* Function for Decrease product quantity
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $qty
 * @param unknown $p_id
 * @return unknown
 */
function decrease_product_qty($qty, $p_id) {
	global $db;
	$qty = (int) $qty;
	$id  = (int)$p_id;
	$sql = "UPDATE products SET quantity=quantity -'{$qty}' WHERE id = '{$id}'";
	$result = $db->query($sql);
	return $db->affected_rows() === 1 ? true : false;

}


/*--------------------------------------------------------------*/
/* Function for Display Recent product Added
  /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $limit
 * @return unknown
 */
function find_recent_product_added($limit) {
	global $db;
	$sql   = " SELECT p.id,p.name,p.sale_price,p.media_id,c.name AS category,";
	$sql  .= "m.file_name AS image FROM products p";
	$sql  .= " LEFT JOIN categories c ON c.id = p.category_id";
	$sql  .= " LEFT JOIN media m ON m.id = p.media_id";
	$sql  .= " ORDER BY p.id DESC LIMIT ".$db->escape((int)$limit);
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Find Highest selling Product
 /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $limit
 * @return unknown
 */
function find_highest_selling_product($limit) {
	global $db;
	$sql  = "SELECT p.name, COUNT(s.product_id) AS totalSold, SUM(s.qty) AS totalQty";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN products p ON p.id = s.product_id ";
	$sql .= " GROUP BY s.product_id";
	$sql .= " ORDER BY SUM(s.qty) DESC LIMIT ".$db->escape((int)$limit);
	return $db->query($sql);
}


/*--------------------------------------------------------------*/
/* Function for find all sales
 /*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function find_all_sales() {
	global $db;
	$sql  = "SELECT s.id,s.order_id,s.qty,s.price,s.date,p.name";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN orders o ON s.order_id = o.id";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " ORDER BY s.date DESC";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for find all orders
 /*--------------------------------------------------------------*/


/**
 *
 * @return unknown
 */
function find_all_orders() {
	global $db;
	$sql  = "SELECT o.id,o.sales_id,o.date";
	$sql .= " FROM orders o";
	$sql .= " LEFT JOIN sales s ON s.id = o.sales_id";
	$sql .= " ORDER BY o.date DESC";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for find sales by order_id
 /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $id
 * @return unknown
 */
function find_sales_by_order_id($id) {
	global $db;
	$sql  = "SELECT s.id,s.product_id,s.qty,s.price,s.date,p.name,p.sku,p.location";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN orders o ON s.order_id = o.id";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " WHERE s.order_id = " . $db->escape((int)$id);
	$sql .= " ORDER BY s.date DESC";
	return find_by_sql($sql);
}




/*--------------------------------------------------------------*/
/* Function for Display Recent sale
 /*--------------------------------------------------------------*/


/**
 *
 * @param unknown $limit
 * @return unknown
 */
function find_recent_sale_added($limit) {
	global $db;
	$sql  = "SELECT s.id,s.qty,s.price,s.date,p.name";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " ORDER BY s.date DESC LIMIT ".$db->escape((int)$limit);
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Generate sales report by two dates
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $start_date
 * @param unknown $end_date
 * @return unknown
 */
function find_sale_by_dates($start_date, $end_date) {
	global $db;
	$start_date  = date("Y-m-d", strtotime($start_date));
	$end_date    = date("Y-m-d", strtotime($end_date));
	$sql  = "SELECT s.date, p.name,p.sale_price,p.buy_price,";
	$sql .= "COUNT(s.product_id) AS total_records,";
	$sql .= "SUM(s.qty) AS total_sales,";
	$sql .= "SUM(p.sale_price * s.qty) AS total_selling_price,";
	$sql .= "SUM(p.buy_price * s.qty) AS total_buying_price ";
	$sql .= "FROM sales s ";
	$sql .= "LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " WHERE s.date BETWEEN '{$start_date}' AND '{$end_date}'";
	$sql .= " GROUP BY DATE(s.date),p.name";
	$sql .= " ORDER BY DATE(s.date) DESC";
	return $db->query($sql);
}


/*--------------------------------------------------------------*/
/* Function for Generate Daily sales report
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $year
 * @param unknown $month
 * @return unknown
 */
function dailySales($year, $month) {
	global $db;
	$sql  = "SELECT s.qty,";
	$sql .= " DATE_FORMAT(s.date, '%Y-%m-%e') AS date,p.name,";
	$sql .= "SUM(p.sale_price * s.qty) AS total_selling_price";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y-%m' ) = '{$year}-{$month}'";
	$sql .= " GROUP BY DATE_FORMAT( s.date,  '%e' ),s.product_id";
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Generate Monthly sales report
/*--------------------------------------------------------------*/


/**
 *
 * @param unknown $year
 * @return unknown
 */
function monthlySales($year) {
	global $db;
	$sql  = "SELECT s.qty,";
	$sql .= " DATE_FORMAT(s.date, '%Y-%m-%e') AS date,p.name,";
	$sql .= "SUM(p.sale_price * s.qty) AS total_selling_price";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y' ) = '{$year}'";
	$sql .= " GROUP BY DATE_FORMAT( s.date,  '%c' ),s.product_id";
	$sql .= " ORDER BY date_format(s.date, '%c' ) ASC";
	return find_by_sql($sql);
}


?>
