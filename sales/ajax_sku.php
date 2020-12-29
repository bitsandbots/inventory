<?php
/**
 * ajax_sku.php
 *
 * @package default
 */


require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('index.php', false);}
?>

<?php
// Auto suggestion
$html = "";
if (isset($_POST['product_sku']) && strlen($_POST['product_sku'])) {
	$products = find_product_by_sku($_POST['product_sku']);
	if ($products) {
		foreach ($products as $product):
			$html .= "<li class=\"list-group-item\">";
		$html .= $product['sku'];
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
// find all product
if (isset($_POST['p_sku']) && strlen($_POST['p_sku'])) {
	$product_sku = remove_junk($db->escape($_POST['p_sku']));
	if ($results = find_all_product_info_by_sku($product_sku)) {
		foreach ($results as $result) {

			$html .= "<tr>";

			$html .= "<td id=\"s_name\">{$result['name']}</td>";
			$html .= "<input type=\"hidden\" name=\"s_id\" value=\"{$result['id']}\">";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['sku']}";
			$html .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['location']}";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['quantity']}";
			$html  .= "</td>";
			$html .= "<td id=\"s_qty\" class=\"text-center\">";
			$html .= "<input type=\"text\" class=\"form-control\" name=\"quantity\" value=\"1\">";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html  .= "<input type=\"text\" class=\"form-control\" name=\"price\" value=\"{$result['sale_price']}\">";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html  .= "<input type=\"text\" class=\"form-control\" name=\"total\" value=\"{$result['sale_price']}\">";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html  .= "<button type=\"submit\" name=\"add_sale\" class=\"btn btn-primary\">Add Sale</button>";
			$html  .= "</td>";
			$html  .= "</tr>";

		}
	} else {
		$html = "<tr><td colspan=\"8\">Product Name Not Registered!</td></tr>";
	}

	echo json_encode($html);
}
?>
