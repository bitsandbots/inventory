<?php
/**
 * layouts/header.php
 *
 * @package default
 */


?>
<!DOCTYPE html>
  <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>
    <?php
$user = current_user();
if (!empty($page_title))
	echo remove_junk($page_title);
elseif (!empty($user))
	echo h(ucfirst($user['name']));
else echo "Inventory Management System";
?>
    </title>
    <link rel="stylesheet" href="../libs/bootstrap/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../libs/datepicker/bootstrap-datepicker.min.css" />
    <link rel="stylesheet" href="../libs/css/main.css" />
  </head>
  <body>
  <?php  if ($session->isUserLoggedIn()): ?>
    <header id="header">
      <div class="logo pull-left">My Inventory
<!--
      <img src="/images/hydroMazing_trans_logo.gif" height="60" width="140">
-->

      </div>
      <div class="header-content">
      <div class="header-date pull-left">
        <strong><?php echo date("F j, Y, g:i a");?></strong>
      </div>
      <div class="pull-right clearfix">
        <ul class="info-menu list-inline list-unstyled">
          <?php
          // Org switcher: only render when user has ≥ 2 active memberships.
          $_user_orgs = [];
          if ($session->isUserLoggedIn() && isset($_SESSION['user_id'])) {
              $_user_orgs = find_org_memberships((int)$_SESSION['user_id']);
          }
          if (count($_user_orgs) >= 2):
              $_current_org_name = 'Organization';
              foreach ($_user_orgs as $_o) {
                  if ((int)$_o['org_id'] === (int)($_SESSION['current_org_id'] ?? 0)) {
                      $_current_org_name = h($_o['name']);
                      break;
                  }
              }
          ?>
          <li class="dropdown">
            <a href="#" data-toggle="dropdown" class="toggle">
              <span class="glyphicon glyphicon-th-list"></span>
              <?php echo $_current_org_name; ?> <i class="caret"></i>
            </a>
            <ul class="dropdown-menu">
              <?php foreach ($_user_orgs as $_o): ?>
              <li>
                <form method="POST" action="../users/switch_org.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="org_id" value="<?php echo (int)$_o['org_id']; ?>">
                  <button type="submit" class="org-switcher-btn">
                    <?php echo h($_o['name']); ?>
                    <?php if ((int)$_o['org_id'] === (int)($_SESSION['current_org_id'] ?? 0)): ?>
                    <span class="glyphicon glyphicon-ok pull-right"></span>
                    <?php endif; ?>
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
          </li>
          <?php endif; ?>
          <li class="profile">
            <a href="#" data-toggle="dropdown" class="toggle" aria-expanded="false">
              <img src="../uploads/users/<?php echo h($user['image']);?>" alt="user-image" class="img-circle img-inline">
              <span><?php echo remove_junk(ucfirst($user['name'])); ?> <i class="caret"></i></span>
            </a>
            <ul class="dropdown-menu">
              <li>
                  <a href="../users/profile.php?id=<?php echo (int)$user['id'];?>">
                      <i class="glyphicon glyphicon-user"></i>
                      Profile
                  </a>
              </li>
             <li>
                 <a href="../users/edit_account.php" title="edit account">
                     <i class="glyphicon glyphicon-cog"></i>
                     Settings
                 </a>
             </li>
             <li class="last">
                 <a href="../users/logout.php">
                     <i class="glyphicon glyphicon-off"></i>
                     Logout
                 </a>
             </li>
           </ul>
          </li>
        </ul>
      </div>
     </div>
    </header>
    <div class="sidebar">
      <?php $ulvl = (int)($user['user_level'] ?? 0); ?>
      <?php if ($ulvl === 1): ?>
        <!-- admin menu -->
      <?php include_once 'admin_menu.php';?>

      <?php elseif ($ulvl === 2): ?>
        <!-- Special user -->
      <?php include_once 'special_menu.php';?>

      <?php elseif ($ulvl === 3): ?>
        <!-- User menu -->
      <?php include_once 'user_menu.php';?>

      <?php else: ?>
        <!-- You shouldn't be here -->
      <?php redirect('../users/logout.php', false); ?>

      <?php endif;?>

   </div>
<?php endif;?>

<div class="page">
  <div class="container-fluid">
