<?php
/**
 * users/settings.php
 *
 * Admin-only Settings page. Currently exposes one knob — the app-wide
 * currency code — backed by the `settings` table. Designed so additional
 * key/value rows can be added with one extra form field + Settings::set()
 * call here, no schema change.
 */


$page_title = 'Settings';
require_once '../includes/load.php';
page_require_level(ROLE_ADMIN);

$current_currency = Settings::get('currency_code', 'USD');
$supported = supported_currency_codes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $session->msg('d', 'Invalid or missing security token.');
        redirect('../users/settings.php', false);
    }

    $posted = isset($_POST['currency_code']) ? trim($_POST['currency_code']) : '';
    if (!in_array($posted, $supported, true)) {
        $session->msg('d', 'Unsupported currency code: ' . htmlspecialchars($posted, ENT_QUOTES, 'UTF-8'));
        redirect('../users/settings.php', false);
    }

    Settings::set('currency_code', $posted);
    $session->msg('s', 'Currency updated to ' . htmlspecialchars($posted, ENT_QUOTES, 'UTF-8') . '.');
    redirect('../users/settings.php', false);
}
?>
<?php include_once '../layouts/header.php'; ?>

<div class="row">
   <div class="col-md-12">
      <?php echo display_msg($msg); ?>
   </div>
</div>

<div class="row">
  <div class="col-md-6 col-md-offset-3">
    <div class="panel panel-default">
      <div class="panel-heading">
        <strong>
          <span class="glyphicon glyphicon-cog"></span> Application Settings
        </strong>
      </div>
      <div class="panel-body">
        <form method="post" action="../users/settings.php">
          <?php echo csrf_field(); ?>

          <div class="form-group">
            <label for="currency_code" class="control-label">Currency Code</label>
            <select name="currency_code" id="currency_code" class="form-control">
              <?php foreach ($supported as $code): ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                  <?php echo ($code === $current_currency) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="help-block">
              ISO 4217 code. Applies to every monetary value rendered by the system
              (invoices, picklists, sales/stock reports, dashboards). Sample:
              <strong><?php echo formatcurrency(1234.56, $current_currency); ?></strong>.
            </p>
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
