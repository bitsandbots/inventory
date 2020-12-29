<?php
/**
 * layouts/special_menu.php
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
    <a href="#" class="submenu-toggle">
      <i class="glyphicon glyphicon-signal"></i>
       <span>Reports</span>
      </a>
      <ul class="nav submenu">
        <li><a href="../reports/stock_report.php">Stock Report </a></li>
        <li><a href="../reports/sales_report.php">Sales by Dates </a></li>
        <li><a href="../reports/monthly_sales.php">Monthly Sales</a></li>
        <li><a href="../reports/daily_sales.php">Daily Sales</a> </li>
      </ul>
  </li>

  <li>
    <a href="#" class="submenu-toggle">
      <i class="glyphicon glyphicon-cog"></i>
      <span>User Management</span>
    </a>
    <ul class="nav submenu">
      <li><a href="../users/group.php">Manage Groups</a> </li>
      <li><a href="../users/users.php">Manage Users</a> </li>
   </ul>
  </li>

</ul>
