<?php
$page_title = 'Organizations';
require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('../users/index.php', false); }
page_require_level(ROLE_ADMIN);

$orgs = find_all_orgs();
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
  </div>
</div>

<div class="row">
  <!-- Create form -->
  <div class="col-md-4">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-plus"></span> Add New Organization</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="update_org.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="0">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" placeholder="Organization Name" required>
          </div>
          <button type="submit" class="btn btn-primary">Create Organization</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Org list -->
  <div class="col-md-8">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-list"></span> All Organizations</strong>
      </div>
      <div class="panel-body">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orgs as $o): ?>
            <tr<?php echo $o['deleted_at'] ? ' class="danger"' : ''; ?>>
              <td><?php echo (int)$o['id']; ?></td>
              <td><?php echo h($o['name']); ?></td>
              <td><?php echo $o['deleted_at'] ? '<span class="label label-danger">Deleted</span>' : '<span class="label label-success">Active</span>'; ?></td>
              <td>
                <?php if (!$o['deleted_at']): ?>
                  <a href="edit_org.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-xs btn-info">
                    <span class="glyphicon glyphicon-edit"></span> Edit
                  </a>
                  <form method="POST" action="delete_org.php" class="form-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="org_id" value="<?php echo (int)$o['id']; ?>">
                    <button type="submit" class="btn btn-xs btn-danger"
                            onclick="return confirm('Soft-delete this organization?')">
                      <span class="glyphicon glyphicon-trash"></span> Delete
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="restore_org.php" class="form-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="org_id" value="<?php echo (int)$o['id']; ?>">
                    <button type="submit" class="btn btn-xs btn-success">
                      <span class="glyphicon glyphicon-refresh"></span> Restore
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orgs)): ?>
            <tr><td colspan="4" class="text-center">No organizations found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
