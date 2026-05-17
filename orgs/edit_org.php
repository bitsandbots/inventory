<?php
$page_title = 'Edit Organization';
require_once '../includes/load.php';
if (!$session->isUserLoggedIn(true)) { redirect('../users/index.php', false); }
page_require_level(1);

$org_id = (int)($_GET['id'] ?? 0);
$org    = $org_id > 0 ? find_org_by_id($org_id) : null;
if (!$org) {
	$session->msg('d', 'Organization not found.');
	redirect('orgs.php', false);
}
require_org_role('owner', 'admin');
$members = find_org_members($org_id);
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
    <a href="orgs.php" class="btn btn-default btn-sm">
      <span class="glyphicon glyphicon-arrow-left"></span> Back to Organizations
    </a>
  </div>
</div>

<div class="row">
  <!-- Rename form -->
  <div class="col-md-4">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-edit"></span> Rename Organization</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="update_org.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="<?php echo (int)$org['id']; ?>">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?php echo h($org['name']); ?>" required>
          </div>
          <button type="submit" class="btn btn-primary">Save Name</button>
        </form>
      </div>
    </div>

    <!-- Add member form -->
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-user"></span> Add Member</strong>
      </div>
      <div class="panel-body">
        <form method="POST" action="add_member.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="org_id" value="<?php echo (int)$org['id']; ?>">
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="Username" required>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
              <option value="owner">Owner</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success">Add Member</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Members table -->
  <div class="col-md-8">
    <div class="panel">
      <div class="panel-heading">
        <strong><span class="glyphicon glyphicon-users"></span>
          Members (<?php echo count($members); ?>)
        </strong>
      </div>
      <div class="panel-body">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
              <td><?php echo h($m['name']); ?></td>
              <td><?php echo h($m['username']); ?></td>
              <td>
                <form method="POST" action="update_member.php" class="form-inline">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="org_id"  value="<?php echo (int)$org_id; ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                  <select name="role" class="form-control input-sm">
                    <?php foreach (['owner','admin','member'] as $r): ?>
                    <option value="<?php echo $r; ?>"<?php echo $m['role'] === $r ? ' selected' : ''; ?>>
                      <?php echo ucfirst($r); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-xs btn-default">Update</button>
                </form>
              </td>
              <td>
                <form method="POST" action="remove_member.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="org_id"  value="<?php echo (int)$org_id; ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                  <button type="submit" class="btn btn-xs btn-danger"
                          onclick="return confirm('Remove <?php echo h($m['username']); ?> from this org?')">
                    <span class="glyphicon glyphicon-remove"></span>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
            <tr><td colspan="4" class="text-center">No members.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
