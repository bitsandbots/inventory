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
	if (!tableExists($table)) {
		return null;
	}
	$sql = "SELECT * FROM " . $db->escape($table);
	if (table_has_soft_delete($table)) {
		$sql .= " WHERE deleted_at IS NULL";
	}
	return find_by_sql($sql);
}


/*--------------------------------------------------------------*/
/* Function for Perform queries (legacy — still used internally for complex joins.
/* New queries with user input must use prepare_select() instead)
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
	if (!tableExists($table)) {
		return null;
	}
	$where = "WHERE id = ?";
	if (table_has_soft_delete($table)) {
		$where .= " AND deleted_at IS NULL";
	}
	return $db->prepare_select_one(
		"SELECT * FROM {$db->escape($table)} {$where} LIMIT 1",
		"i", (int)$id
	);
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
	return false;
}


/*--------------------------------------------------------------*/
/* Soft-delete helpers (PR: soft-delete pattern, 2026-05-16).
/* In-scope tables: users, customers, sales, orders, stock.
/*--------------------------------------------------------------*/

/**
 * In-scope tables for the soft-delete pattern. Adding a table here is
 * NOT enough to enable soft-delete — the table also needs the
 * `deleted_at` column from the matching 005-009 migration.
 */
const SOFT_DELETE_TABLES = ['users', 'customers', 'sales', 'orders', 'stock'];

/**
 * Returns true when $table is in the in-scope allowlist AND its
 * `deleted_at` column exists. Cached per request. Never throws —
 * a probe failure returns false so the deploy-window fallback works.
 *
 * @param string $table
 * @return bool
 */
function table_has_soft_delete(string $table): bool {
	static $cache = [];
	if (array_key_exists($table, $cache)) {
		return $cache[$table];
	}
	if (!in_array($table, SOFT_DELETE_TABLES, true)) {
		return $cache[$table] = false;
	}
	global $db;
	try {
		$r = $db->connection()->query(
			"SHOW COLUMNS FROM `" . $db->escape($table) . "` LIKE 'deleted_at'"
		);
		$has = ($r !== false && $r->num_rows > 0);
		if ($r) {
			$r->free();
		}
		return $cache[$table] = $has;
	} catch (\Throwable $e) {
		return $cache[$table] = false;
	}
}

/*--------------------------------------------------------------*/
/* Tenancy: org-scoped tables and helpers
/*--------------------------------------------------------------*/

/**
 * In-scope tables for the org-scoping pattern. Adding a table here is
 * NOT enough to enable org-scoping — the table also needs the
 * `org_id` column from the matching 013-019 migration.
 */
const ORG_SCOPED_TABLES = [
	'customers', 'products', 'categories',
	'sales', 'orders', 'stock', 'media',
];

/**
 * Returns true when $table is in the in-scope allowlist AND its
 * `org_id` column exists. Cached per request. Never throws —
 * a probe failure returns false so the deploy-window fallback works.
 *
 * @param string $table
 * @return bool
 */
function table_has_org_id(string $table): bool {
	static $cache = [];
	if (array_key_exists($table, $cache)) {
		return $cache[$table];
	}
	if (!in_array($table, ORG_SCOPED_TABLES, true)) {
		return $cache[$table] = false;
	}
	global $db;
	try {
		$r = $db->connection()->query(
			"SHOW COLUMNS FROM `" . $db->escape($table) . "` LIKE 'org_id'"
		);
		$has = ($r !== false && $r->num_rows > 0);
		if ($r) {
			$r->free();
		}
		return $cache[$table] = $has;
	} catch (\Throwable $e) {
		return $cache[$table] = false;
	}
}

/**
 * Returns the active org_id from the session.
 * Throws RuntimeException when called outside an authenticated session.
 *
 * @return int
 * @throws RuntimeException
 */
function current_org_id(): int {
	if (empty($_SESSION['current_org_id'])) {
		throw new \RuntimeException(
			'current_org_id() called with no active org — session not initialized'
		);
	}
	return (int)$_SESSION['current_org_id'];
}

/**
 * Soft-delete a row. Stamps deleted_at = NOW() and deleted_by = actor.
 * No-op when the table is not in scope.
 *
 * @param string $table
 * @param int $id
 * @param int|null $actor_user_id  Defaults to $_SESSION['user_id'].
 * @return bool  True when exactly one row was updated.
 */
function soft_delete_by_id(string $table, int $id, ?int $actor_user_id = null): bool {
	if (!table_has_soft_delete($table)) {
		return false;
	}
	if ($actor_user_id === null) {
		$actor_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
	}
	global $db;
	$stmt = $db->prepare_query(
		"UPDATE `" . $db->escape($table) . "`
			SET deleted_at = NOW(), deleted_by = ?
		  WHERE id = ? AND deleted_at IS NULL LIMIT 1",
		"ii", $actor_user_id, $id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
}

/**
 * Reverse a soft-delete. Sets both deleted_at and deleted_by to NULL.
 *
 * @param string $table
 * @param int $id
 * @return bool  True when exactly one row was updated.
 */
function restore_by_id(string $table, int $id): bool {
	if (!table_has_soft_delete($table)) {
		return false;
	}
	global $db;
	$stmt = $db->prepare_query(
		"UPDATE `" . $db->escape($table) . "`
			SET deleted_at = NULL, deleted_by = NULL
		  WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1",
		"i", $id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
}

/**
 * Permanently delete a soft-deleted row. Refuses when the row is still
 * active (deleted_at IS NULL) — must be soft-deleted first.
 *
 * @param string $table
 * @param int $id
 * @return bool  True when one row was removed.
 */
function purge_by_id(string $table, int $id): bool {
	if (!table_has_soft_delete($table)) {
		return false;
	}
	global $db;
	$stmt = $db->prepare_query(
		"DELETE FROM `" . $db->escape($table) . "`
		  WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1",
		"i", $id
	);
	$affected = $stmt->affected_rows;
	$stmt->close();
	return ($affected === 1);
}

/**
 * Same as find_by_id but does NOT filter out soft-deleted rows.
 * For the trash UI and audit lookups.
 *
 * @param string $table
 * @param int $id
 * @return array|null
 */
function find_by_id_with_deleted(string $table, int $id): ?array {
	global $db;
	if (!tableExists($table)) {
		return null;
	}
	return $db->prepare_select_one(
		"SELECT * FROM `" . $db->escape($table) . "` WHERE id = ? LIMIT 1",
		"i", (int)$id
	);
}

/**
 * Same as find_all but does NOT filter out soft-deleted rows.
 * For the trash UI and audit/export lookups.
 *
 * @param string $table
 * @return array|null
 */
function find_with_deleted(string $table): ?array {
	global $db;
	if (!tableExists($table)) {
		return null;
	}
	return find_by_sql("SELECT * FROM " . $db->escape($table));
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
	return false;
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
 * Supports legacy SHA1 hashes and modern bcrypt hashes. Automatic rehash
 * on login: SHA1 → bcrypt on first login after migration, and bcrypt →
 * bcrypt if cost factor changes (password_needs_rehash).
 *
 * @param string $username
 * @param string $password
 * @return int|false  User ID on success, false on failure
 */
function authenticate($username='', $password='') {
	global $db;
	$sql  = "SELECT id,username,password,user_level FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1";
	$result = $db->prepare_select_one($sql, "s", $username);

	if ($result) {
		$stored_hash = $result['password'];

		// Check if stored hash is a legacy SHA1 hash (40-char hex string)
		if (strlen($stored_hash) === 40 && ctype_xdigit($stored_hash)) {
			// Legacy SHA1 comparison — hash_equals prevents timing attacks
			if (hash_equals($stored_hash, sha1($password))) {
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
	$sql .="ON g.group_level=u.user_level ";
	$sql .="WHERE u.deleted_at IS NULL ";
	$sql .="ORDER BY u.name ASC";
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
	// Anonymous hits (no session yet) insert NULL so the fk_log_user FK
	// is satisfied. Branching the SQL is more portable across mysqli
	// versions than relying on bind_param to translate PHP null on an
	// "i"-typed parameter.
	if (!$user_id) {
		$stmt = $db->prepare_query(
			"INSERT INTO log (remote_ip, action, date) VALUES (?, ?, ?)",
			"sss", $remote_ip, $action, $date
		);
	} else {
		$stmt = $db->prepare_query(
			"INSERT INTO log (user_id, remote_ip, action, date) VALUES (?, ?, ?, ?)",
			"isss", $user_id, $remote_ip, $action, $date
		);
	}
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
	// Disabled-account / disabled-group enforcement.
	// mysqli returns INT columns as int on PHP 8.1+, so compare as int.
	elseif ((int)$current_user['status'] === 0):
		$session->msg('d', 'Your account has been disabled!');
		redirect('../users/home.php', false);
	elseif ((int)$login_level['group_status'] === 0):
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
		"SELECT name FROM customers WHERE name LIKE ? AND deleted_at IS NULL LIMIT 5",
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
		"SELECT * FROM customers WHERE name = ? AND deleted_at IS NULL LIMIT 1",
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
	$sql .= " WHERE s.deleted_at IS NULL";
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
	$sql .= " WHERE s.deleted_at IS NULL";
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
	$sql .= " WHERE o.deleted_at IS NULL";
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
	$sql .= " WHERE s.order_id = ? AND s.deleted_at IS NULL";
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
	$sql .= " WHERE s.deleted_at IS NULL";
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
	$sql .= " WHERE s.date BETWEEN ? AND ? AND s.deleted_at IS NULL";
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
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y-%m' ) = ? AND s.deleted_at IS NULL";
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
	$sql .= " WHERE DATE_FORMAT(s.date, '%Y' ) = ? AND s.deleted_at IS NULL";
	$sql .= " GROUP BY DATE_FORMAT( s.date,  '%c' ),s.product_id";
	$sql .= " ORDER BY date_format(s.date, '%c' ) ASC";
	return $db->prepare_select($sql, "s", $year);
}


/*--------------------------------------------------------------*/
/* Login rate limiting
/*--------------------------------------------------------------*/

// Maximum failed attempts allowed per IP within the window before lockout.
if (!defined('LOGIN_MAX_ATTEMPTS')) {
	define('LOGIN_MAX_ATTEMPTS', 5);
}
// Lockout window in seconds.
if (!defined('LOGIN_WINDOW_SECONDS')) {
	define('LOGIN_WINDOW_SECONDS', 900); // 15 minutes
}

/**
 * Count failed logins from the given IP within the rate-limit window.
 *
 * @param string $ip Client IP
 * @return int Number of attempts in the window
 */
function recent_failed_login_count(string $ip): int
{
	global $db;
	$window = LOGIN_WINDOW_SECONDS;
	$rows = $db->prepare_select(
		"SELECT COUNT(*) AS n FROM failed_logins
		 WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)",
		"si", $ip, $window
	);
	return isset($rows[0]['n']) ? (int)$rows[0]['n'] : 0;
}

/**
 * Return true if this IP has exceeded the rate limit and should be blocked.
 */
function is_login_rate_limited(string $ip): bool
{
	return recent_failed_login_count($ip) >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Record a failed login attempt (IP + username attempted).
 */
function record_failed_login(string $ip, string $username_attempted): void
{
	global $db;
	$stmt = $db->prepare_query(
		"INSERT INTO failed_logins (ip, username_attempted, attempted_at)
		 VALUES (?, ?, NOW())",
		"ss", $ip, $username_attempted
	);
	$stmt->close();
}

/**
 * Clear all failed-login records for this IP. Called on successful login.
 */
function clear_failed_logins(string $ip): void
{
	global $db;
	$stmt = $db->prepare_query(
		"DELETE FROM failed_logins WHERE ip = ?",
		"s", $ip
	);
	$stmt->close();
}

/**
 * Prune failed_logins rows older than the rate-limit window.
 * Called probabilistically from load.php on page request — no cron needed.
 * A row older than the window has no effect on rate limiting, so its only
 * value is forensic, and we keep that in the audit log table instead.
 */
function prune_failed_logins(): void
{
	global $db;
	$window = LOGIN_WINDOW_SECONDS;
	$stmt = $db->prepare_query(
		"DELETE FROM failed_logins WHERE attempted_at < (NOW() - INTERVAL ? SECOND)",
		"i", $window
	);
	$stmt->close();
}
