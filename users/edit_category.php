<?php
/**
 * users/edit_category.php
 *
 * @package default
 */


$page_title = 'Edit Category';
require_once '../includes/load.php';
// Checkin What level user has permission to view this page
page_require_level(1);
?>
<?php
//Display all catgories.
$category = find_by_id('categories', (int)$_GET['id']);
if (!$category) {
	$session->msg("d", "Missing category id.");
	redirect('../users/category.php');
}
?>

<?php
if (isset($_POST['edit_cat'])) {
	$req_field = array('category-name');
	validate_fields($req_field);
	$cat_name = remove_junk($db->escape($_POST['category-name']));
	if (empty($errors)) {
		$sql = "UPDATE categories SET name='{$cat_name}'";
		$sql .= " WHERE id='{$category['id']}'";
		$result = $db->query($sql);
		if ($result && $db->affected_rows() === 1) {
			$session->msg("s", "Successfully updated category");
			redirect('../users/category.php', false);
		} else {
			$session->msg("d", "Sorry! Failed to Update");
			redirect('../users/category.php', false);
		}
	} else {
		$session->msg("d", $errors);
		redirect('../users/category.php', false);
	}
}
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
   <div class="col-md-12">
     <?php echo display_msg($msg); ?>
   </div>
   <div class="col-md-5">
     <div class="panel panel-default">
       <div class="panel-heading">
         <strong>
           <span class="glyphicon glyphicon-th"></span>
           <span>Editing <?php echo ucfirst($category['name']);?></span>
        </strong>
       </div>
       <div class="panel-body">
         <form method="post" action="../users/edit_categorie.php?id=<?php echo (int)$category['id'];?>">
           <div class="form-group">
               <input type="text" class="form-control" name="category-name" value="<?php echo ucfirst($category['name']);?>">
           </div>
           <button type="submit" name="edit_cat" class="btn btn-primary">Update category</button>
       </form>
       </div>
     </div>
   </div>
</div>



<?php include_once '../layouts/footer.php'; ?>
