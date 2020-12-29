<?php
/**
 * ajax_customer.php
 *
 * @package default
 */


require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('index.php', false);}
?>

<?php
// Auto suggestion
$html = "";
if (isset($_POST['customer_name']) && strlen($_POST['customer_name'])) {
	$customers = find_customer_by_name($_POST['customer_name']);
	if ($customers) {
		foreach ($customers as $customer):
			$html .= "<li class=\"list-group-item\">";
		$html .= $customer['name'];
		$html .= "</li>";
		endforeach;
	} else {
		$html = "<li class=\"list-group-item\">";
		$html .= "Not found";
		$html .= "</li>";

	}

	echo json_encode($html);
}
?>

<?php
// find customer
if (isset($_POST['c_name']) && strlen($_POST['c_name'])) {
	$customer_name = remove_junk($db->escape($_POST['c_name']));
	if ($results = find_all_customer_info_by_name($customer_name)) {
		foreach ($results as $result) {

			$html .= "<tr>";

			$html .= "<td id=\"customer_name\">{$result['name']}</td>";
			$html .= "<input type=\"hidden\" name=\"customer-name\" value=\"{$result['name']}\">";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['address']}";
			$html .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['postcode']}";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html  .= "<select class=\"form-control\" name=\"paymethod\">";
			$html  .= "<option value=\"\">Select Payment Method</option>";
			$html  .= "<option value=\"Cash\"";
			if ($result['paymethod'] === "Cash" ): $html  .= "selected"; endif;
			$html  .= ">Cash</option>";
			$html  .= "<option value=\"Check\"";
			if ($result['paymethod'] === "Check" ): $html  .= "selected"; endif;
			$html  .= ">Check</option>";
			$html  .= "<option value=\"Credit\"";
			if ($result['paymethod'] === "Credit" ): $html  .=  "selected"; endif;
			$html  .= ">Credit</option>";
			$html  .= "<option value=\"Charge\"";
			if ($result['paymethod'] === "Charge" ): $html  .=  "selected"; endif;
			$html  .= ">Charge to Account</option>";
			$html  .= "</select>";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html  .= "<button type=\"submit\" name=\"add_order\" class=\"btn btn-primary\">Start Order</button>";
			$html  .= "</td>";
			$html  .= "</tr>";

		}
	} else {
		$html   = "<tr>";
		$html  .= "<td colspan=\"4\">Customer Not Registered!</td>";
		$html  .= "<td class=\"text-center\">";
		$html  .= "<a href=\"../customers/add_customer.php\" class=\"btn btn-primary\">Add Customer</a>";
		$html  .= "</td>";
		$html  .= "</tr>";
	}

	echo json_encode($html);
}
?>
