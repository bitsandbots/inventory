<?php
/**
 * ajax_product.php
 *
 * @package default
 */


require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('index.php', false);}
?>

<?php
// Auto suggestion
$html = "";
if (isset($_POST['product_search']) && strlen($_POST['product_search'])) {
	$products = find_products_by_search($_POST['product_search']);
	if ($products) {
		foreach ($products as $product):
			$html .= "<li class=\"list-group-item\">";
		$html .= $product['name'];
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
if (isset($_POST['p_search']) && strlen($_POST['p_search'])) {
	$product_search = remove_junk($db->escape($_POST['p_search']));
	if ($results = find_all_product_info_by_search($product_search)) {
		foreach ($results as $result) {

			$html .= "<tr>";
			$html .= "<td id=\"s_name\"><a href=\"view_product.php?id={$result['id']}\">{$result['name']}</a></td>";
			$html .= "<input type=\"hidden\" name=\"s_id\" value=\"{$result['id']}\">";
			$html .= "<td class=\"text-center\">";
			if ($result['media_id'] === '0') {
				$html .= "<img class=\"img-avatar img-circle\" src=\"../uploads/products/no_image.jpg\">";
			} else {
				$html .= "<img class=\"img-avatar img-circle\" src=\"../uploads/products/{$result['image']}\">";
			}
			$html .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['sku']}";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['location']}";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "{$result['quantity']}";
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= formatcurrency( $result['buy_price'], $CURRENCY_CODE);
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= formatcurrency( $result['sale_price'], $CURRENCY_CODE);
			$html  .= "</td>";
			$html .= "<td class=\"text-center\">";
			$html .= "<div class=\"btn-group\">";
			$html .= "<a href=\"add_stock.php?id={$result['id']}\" class=\"btn btn-xs btn-warning\" data-toggle=\"tooltip\" title=\"Add\">";
			$html .= "<span class=\"glyphicon glyphicon-th-large\"></span></a>";
			$html .= "<a href=\"edit_product.php?id={$result['id']}\" class=\"btn btn-info btn-xs\"  title=\"Edit\" data-toggle=\"tooltip\">";
			$html .= "<span class=\"glyphicon glyphicon-edit\"></span></a>";
			$html .= "<a href=\"delete_product.php?id={$result['id']}\" onClick=\"return confirm('Are you sure you want to delete?')\" class=\"btn btn-danger btn-xs\"  title=\"Delete\" data-toggle=\"tooltip\">";
			$html .= "<span class=\"glyphicon glyphicon-trash\"></span></a>";
			$html .= "</div>";
			$html  .= "</td>";
			$html  .= "</tr>";

		}
	} else {
		$html ="<tr><td colspan=\"8\">Product Not Registered!</td></tr>";
	}

	echo json_encode($html);
}
?>
