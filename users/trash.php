<?php
/**
 * users/trash.php
 *
 * Admin-only trash page. Lists soft-deleted rows per table with Restore
 * and Purge actions. Tabs across the top switch tables via ?table=...
 * 5 supported tables: users, customers, sales, orders, stock.
 */

require_once '../includes/load.php';
page_require_level(1);

// SOFT_DELETE_TABLES is defined in includes/sql.php (Task 7).
$table = isset($_GET['table']) ? (string)$_GET['table'] : 'users';
if (!in_array($table, SOFT_DELETE_TABLES, true)) {
    $session->msg('d', 'Invalid trash table.');
    redirect('trash.php?table=users', false);
}

$rows = find_with_deleted($table);
// Keep only soft-deleted rows.
$rows = array_values(array_filter($rows, function ($r) {
    return !empty($r['deleted_at']);
}));

// Per-table label-column projector. Falls back to id only.
function trash_label_columns(string $table, array $row): array {
    switch ($table) {
        case 'users':
            return ['username' => $row['username'] ?? '', 'name' => $row['name'] ?? ''];
        case 'customers':
            return ['name' => $row['name'] ?? ''];
        case 'sales':
            return ['date' => $row['date'] ?? '', 'qty' => $row['qty'] ?? '', 'price' => $row['price'] ?? ''];
        case 'orders':
            return ['customer' => $row['customer'] ?? '', 'date' => $row['date'] ?? ''];
        case 'stock':
            $product = !empty($row['product_id']) ? find_by_id('products', (int)$row['product_id']) : null;
            return [
                'product' => $product['name'] ?? ('product #' . ($row['product_id'] ?? '?')),
                'quantity' => $row['quantity'] ?? '',
                'date' => $row['date'] ?? '',
            ];
    }
    return [];
}

$page_title = 'Trash';
include_once('../layouts/header.php');
?>
<div class="row">
  <div class="col-md-12">
    <?php echo display_msg($msg); ?>
    <div class="panel panel-default">
      <div class="panel-heading clearfix">
        <strong><span class="glyphicon glyphicon-trash"></span> Trash</strong>
      </div>
      <ul class="nav nav-tabs">
        <?php foreach (SOFT_DELETE_TABLES as $t):
            $active = ($t === $table) ? ' class="active"' : '';
        ?>
          <li<?php echo $active; ?>>
            <a href="trash.php?table=<?php echo h($t); ?>"><?php echo h(ucfirst($t)); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="panel-body">
        <?php if (empty($rows)): ?>
          <p>No soft-deleted <?php echo h($table); ?> rows.</p>
        <?php else: ?>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>ID</th>
                <?php foreach (array_keys(trash_label_columns($table, $rows[0])) as $label_col): ?>
                  <th><?php echo h(ucfirst($label_col)); ?></th>
                <?php endforeach; ?>
                <th>Deleted at</th>
                <th>Deleted by</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row):
                $labels = trash_label_columns($table, $row);
                $deleter = !empty($row['deleted_by'])
                    ? find_by_id_with_deleted('users', (int)$row['deleted_by'])
                    : null;
                $deleter_name = $deleter ? ($deleter['username'] ?? ('user #' . $deleter['id'])) : '—';
              ?>
                <tr>
                  <td><?php echo (int)$row['id']; ?></td>
                  <?php foreach ($labels as $val): ?>
                    <td><?php echo h((string)$val); ?></td>
                  <?php endforeach; ?>
                  <td><?php echo h($row['deleted_at']); ?></td>
                  <td><?php echo h($deleter_name); ?></td>
                  <td>
                    <form method="post" action="restore.php" style="display:inline">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="table" value="<?php echo h($table); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn btn-success btn-xs">Restore</button>
                    </form>
                    <form method="post" action="purge.php" style="display:inline"
                          onsubmit="return confirm('Permanently delete this row? This cannot be undone.');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="table" value="<?php echo h($table); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-xs">Purge</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include_once('../layouts/footer.php'); ?>
