<?php
/**
 * layouts/user_menu.php
 *
 * @package default
 */


?>
<ul>
  <li>
    <a href="../users/home.php">
      <i class="glyphicon glyphicon-home"></i>
      <span>Dashboard</span>
    </a>
  </li>

  <li>
    <a href="#" class="submenu-toggle">
      <i class="glyphicon glyphicon-shopping-cart"></i>
      <span>Products</span>
    </a>
    <ul class="nav submenu">
       <li><a href="../products/media.php">Media</a> </li>
       <li><a href="../products/categories.php">Categories</a> </li>
       <li><a href="../products/products.php">Manage Products</a> </li>
       <li><a href="../products/product_search.php">Product Search</a> </li>
       <li><a href="../products/stock.php">Manage Stock</a> </li>
   </ul>
  </li>
  <li>
    <a href="../customers/customers.php" class="submenu-toggle">
      <i class="glyphicon glyphicon-user"></i>
      <span>Customers</span>
    </a>
  </li>
  <li>
    <a href="#" class="submenu-toggle">
      <i class="glyphicon glyphicon-piggy-bank"></i>
       <span>Sales</span>
      </a>
      <ul class="nav submenu">
         <li><a href="../sales/add_order_by_customer.php">Add Order</a> </li>
         <li><a href="../sales/orders.php">Manage Orders</a> </li>
         <li><a href="../sales/sales.php">Manage Sales</a> </li>
         <li><a href="../sales/add_sale_by_sku.php">Add Sale by SKU</a> </li>
     </ul>
  </li>
  <li>

</ul>
