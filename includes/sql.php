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
/* Function for Perform queries (legacy — prefer prepare_select)
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
	if (tableExists($table)) {
		return $db->prepare_select_one(
			"SELECT * FROM {$db->escape($table)} WHERE id = ? LIMIT 1",
			"i", (int)$id
		);
	}
	return null;
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
		return $db->prepare_select_one(
			"SELECT * FROM {$db->escape($table)} WHERE name = ? LIMIT 1",
			"s", $name
		);
	}
	return null;
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
		$stmt = $db->prepare_query(
			"DELETE FROM ".$db->escape($table)." WHERE id = ? LIMIT 1",
			"i", (int)$id
		);
		$affected = $stmt->affected_rows;
		$stmt->close();
		return ($affected === 1);
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
		$stmt = $db->prepare_query(
			"DELETE FROM ".$db->escape($table)." WHERE remote_ip = ?",
			"s", $remote_ip
		);
		$affected = $stmt->affected_rows;
		$stmt->close();
		return ($affected >= 1);
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
 * Authenticate a user by username and password.
 * Uses password_verify() for bcrypt hashes with automatic rehash on login
 * for users whose passwords were hashed with the old sha1 method.
 *
 * @param string $username
 * @param string $password
 * @return int|false  User ID on success, false on failure
 */
function authenticate($username='', $password='') {
	global $db;
	$sql  = "SELECT id,username,password,user_level FROM users WHERE username = ? LIMIT 1";
	$result = $db->prepare_select_one($sql, "s", $username);

	if ($result) {
		$stored_hash = $result['password'];

		// Check if stored hash is a legacy SHA1 hash (40-char hex string)
		if (strlen($stored_hash) === 40 && ctype_xdigit($stored_hash)) {
			// Legacy SHA1 comparison
			if (sha1($password) === $stored_hash) {
				// Rehash with bcrypt for future logins
				$new_hash = password_hash($password, PASSWORD_BCRYPT);
				$db->prepare_query(
					"UPDATE users SET password = ? WHERE id = ?",
					"si", $new_hash, $result['id']
				);
				return $result['id'];
			}
		} else {
			// Modern bcrypt comparison
			if (password_verify($password, $stored_hash)) {
				// Check if hash needs rehash (cost factor change, etc.)
				if (password_needs_rehash($stored_hash, PASSWORD_BCRYPT)) {
					$new_hash = password_hash($password, PASSWORD_BCRYPT);
					$db->prepare_query(
						"UPDATE users SET password = ? WHERE id = ?",
						"si", $new_hash, $result['id']
					);
				}
				return $result['id'];
			}
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
	$sql = "SELECT u.id,u.name,u.username,u.user_level,u.status,u.last_login,";
	$sql .="g.group_name ";
	$sql .="FROM users u ";
	$sql .="LEFT JOIN user_groups g ";
	$sql .="ON g.group_level=u.user_level ORDER BY u.name ASC";
	return find_by_sql($sql);
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
	$stmt = $db->prepare_query(
		"UPDATE users SET last_login = ? WHERE id = ? LIMIT 1",
		"si", $date, $user_id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
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
	$stmt = $db->prepare_query(
		"INSERT INTO log (user_id, remote_ip, action, date) VALUES (?, ?, ?, ?)",
		"isss", $user_id, $remote_ip, $action, $date
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
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
	$sql = "SELECT group_name FROM user_groups WHERE group_name = ? LIMIT 1";
	$result = $db->prepare_select($sql, "s", $val);
	return count($result) === 0;
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
	$sql = "SELECT group_status FROM user_groups WHERE group_level = ? LIMIT 1";
	return $db->prepare_select_one($sql, "i", (int)$level);
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
	$search = "%{$p_name}%";
	return $db->prepare_select(
		"SELECT name FROM products WHERE name LIKE ? LIMIT 5",
		"s", $search
	);
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
	return $db->prepare_select(
		"SELECT * FROM products WHERE name = ? LIMIT 1",
		"s", $title
	);
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
	$search = "%{$product_sku}%";
	return $db->prepare_select(
		"SELECT sku FROM products WHERE sku LIKE ? LIMIT 5",
		"s", $search
	);
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
	return $db->prepare_select(
		"SELECT * FROM products WHERE sku = ? LIMIT 1",
		"s", $product_sku
	);
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
	$search = "%{$customer}%";
	return $db->prepare_select(
		"SELECT name FROM customers WHERE name LIKE ? LIMIT 5",
		"s", $search
	);
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
	return $db->prepare_select(
		"SELECT * FROM customers WHERE name = ? LIMIT 1",
		"s", $customer_name
	);
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
	$search = "%{$p_search}%";
	return $db->prepare_select(
		"SELECT * FROM products WHERE (name LIKE ? OR sku LIKE ? OR description LIKE ?) LIMIT 5",
		"sss", $search, $search, $search
	);
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
	$like = "%{$p_search}%";

	$sql  =" SELECT p.id,p.name,p.sku,p.location,p.quantity,p.buy_price,p.sale_price,p.media_id,p.date,c.name";
	$sql  .=" AS category,m.file_name AS image";
	$sql  .=" FROM products p";
	$sql  .=" LEFT JOIN categories c ON c.id = p.category_id";
	$sql  .=" LEFT JOIN media m ON m.id = p.media_id";
	$sql  .=" WHERE ( p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? )";
	$sql  .=" ORDER BY p.id ASC";

	return $db->prepare_select($sql, "sss", $like, $like, $like);
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
	$sql  .=" WHERE c.id = ?";
	$sql  .=" ORDER BY p.id ASC";
	return $db->prepare_select($sql, "i", (int)$cat);
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
	$id  = (int) $p_id;
	$stmt = $db->prepare_query(
		"UPDATE products SET quantity = quantity + ? WHERE id = ?",
		"ii", $qty, $id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
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
	$id  = (int) $p_id;
	$stmt = $db->prepare_query(
		"UPDATE products SET quantity = quantity - ? WHERE id = ?",
		"ii", $qty, $id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
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
	$sql .= " WHERE s.order_id = ?";
	$sql .= " ORDER BY s.date DESC";
	return $db->prepare_select($sql, "i", (int)$id);
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
	$sql .= " WHERE s.date BETWEEN ? AND ?";
	$sql .= " GROUP BY DATE(s.date),p.name";
	$sql .= " ORDER BY DATE(s.date) DESC";
	return $db->prepare_select($sql, "ss", $start_date, $end_date);
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
	$year_month = "{$year}-{$month}";
	$sql  = "SELECT s.qty,";
	$sql .= " DATE_FORMAT(s.date, '%Y-%m-%e') AS date,p.name,";
	$sql .= "SUM(p.sale_price * s.qty) AS total_selling_price";
	$sql .= " FROM sales s";
	$sql .= " LEFT JOIN products p ON s.product_id = p.id";
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y-%m' ) = ?";
	$sql .= " GROUP BY DATE_FORMAT( s.date,  '%e' ),s.product_id";
	return $db->prepare_select($sql, "s", $year_month);
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
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y' ) = ?";
	$sql .= " GROUP BY DATE_FORMAT( s.date,  '%c' ),s.product_id";
	$sql .= " ORDER BY date_format(s.date, '%c' ) ASC";
	return $db->prepare_select($sql, "s", $year);
}
