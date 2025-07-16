<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../db.php';

// Function to build query string
if (!function_exists('buildQueryString')) {
  function buildQueryString($params)
  {
    $query = [];
    foreach ($params as $key => $value) {
      if ($key != 'p' && $value !== '') {
        $query[] = "$key=" . urlencode($value);
      }
    }
    return implode('&', $query);
  }
}

// Handle deletion if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client_id'])) {
  $client_id = (int)$_POST['delete_client_id'];
  $company_id = (int)$_POST['company_id'];

  // Perform deletion
  $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $stmt->close();

  // Redirect to same page to prevent form resubmission
  header("Location: index.php?page=report_quotation&company_id=" . $company_id);
  exit();
}

// Fetch all companies for dropdown
$companies = [];
$stmt = $conn->prepare("SELECT id, name FROM companies ORDER BY name");
if ($stmt) {
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
  }
  $stmt->close();
}

// Get selected company - check GET first, then POST, then session, then default to first company
if (isset($_GET['company_id'])) {
  $company_id = (int)$_GET['company_id'];
} elseif (isset($_POST['company_id'])) {
  $company_id = (int)$_POST['company_id'];
} elseif (isset($_SESSION['current_company_id'])) {
  $company_id = (int)$_SESSION['current_company_id'];
} else {
  $company_id = $companies[0]['id'] ?? 0;
}

// Store company_id in session to persist across requests
$_SESSION['current_company_id'] = $company_id;

// Get search parameters
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $records_per_page;

// Base query for counting and fetching
$base_query = "FROM clients c 
LEFT JOIN window_calculation_details wcd ON c.id = wcd.client_id AND c.company_id = wcd.company_id
WHERE c.company_id = ?";

$params = [$company_id];
$param_types = "i";

// Add search conditions
if (!empty($search_name)) {
  $base_query .= " AND c.name LIKE ?";
  $params[] = "%$search_name%";
  $param_types .= "s";
}

if (!empty($from_date) && !empty($to_date)) {
  $base_query .= " AND DATE(wcd.created_at) BETWEEN ? AND ?";
  $params[] = $from_date;
  $params[] = $to_date;
  $param_types .= "ss";
} elseif (!empty($from_date)) {
  $base_query .= " AND DATE(wcd.created_at) >= ?";
  $params[] = $from_date;
  $param_types .= "s";
} elseif (!empty($to_date)) {
  $base_query .= " AND DATE(wcd.created_at) <= ?";
  $params[] = $to_date;
  $param_types .= "s";
}

// Get total number of clients for the selected company with filters
$total_clients = 0;
$count_query = "SELECT COUNT(DISTINCT c.id) $base_query";
$stmt = $conn->prepare($count_query);
if ($stmt) {
  $stmt->bind_param($param_types, ...$params);
  $stmt->execute();
  $stmt->bind_result($total_clients);
  $stmt->fetch();
  $stmt->close();
}

// Calculate total pages for pagination
$total_pages = ceil($total_clients / $records_per_page);

// Fetch clients for the selected company with pagination and filters
$clients = [];
$select_query = "SELECT DISTINCT c.id, c.name, c.phone, c.address $base_query ORDER BY c.name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($select_query);
if ($stmt) {
  // Add pagination parameters
  $params[] = $records_per_page;
  $params[] = $offset;
  $param_types .= "ii";

  $stmt->bind_param($param_types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
  }
  $stmt->close();
}

// Build base query string for pagination
$queryParams = [
  'page' => 'report_quotation',
  'company_id' => $company_id,
  'search_name' => $search_name,
  'from_date' => $from_date,
  'to_date' => $to_date
];
$baseQueryString = buildQueryString($queryParams);

// If AJAX request, return only the table body
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  ob_clean();
?>
  <tbody id="clients-table-body">
    <?php if (empty($clients)): ?>
      <tr>
        <td colspan="5" class="text-center py-2">No clients found</td>
      </tr>
    <?php else: ?>
      <?php foreach ($clients as $index => $client): ?>
        <?php
        $quotation_number = '';
        $stmt_quote = $conn->prepare("SELECT quotation_number FROM window_calculation_details WHERE client_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
        if ($stmt_quote) {
          $stmt_quote->bind_param("ii", $client['id'], $company_id);
          $stmt_quote->execute();
          $stmt_quote->bind_result($quotation_number);
          $stmt_quote->fetch();
          $stmt_quote->close();
        }
        ?>
        <tr>
          <td><?= $offset + $index + 1 ?></td>
          <td><?= htmlspecialchars($client['name']) ?></td>
          <td><?= htmlspecialchars($client['phone']) ?></td>
          <td><?= htmlspecialchars($client['address']) ?></td>
          <td>
            <?php if ($quotation_number): ?>
              <a href="index.php?page=reports_worker&quotation_number=<?= urlencode($quotation_number) ?>&client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit"></i> Worker
              </a>
            <?php else: ?>
              <span class="text-muted">No Quotation</span>
            <?php endif; ?>

            <a href="client_quotation.php?client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-info">
              <i class="fas fa-eye"></i> View
            </a>

            <form method="post" action="index.php?page=report_quotation&company_id=<?= $company_id ?>" style="display:inline;">
              <input type="hidden" name="delete_client_id" value="<?= $client['id'] ?>">
              <input type="hidden" name="company_id" value="<?= $company_id ?>">
              <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this?')">
                <i class="fas fa-trash"></i> Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
<?php
  exit();
}

if (isset($_GET['quotation_id'])) {
  $quotation_id = (int)$_GET['quotation_id'];
  // Fetch all calculation details for this quotation
  $stmt = $conn->prepare("SELECT * FROM window_calculation_details WHERE quotation_number = (SELECT quotation_number FROM quotations WHERE id = ? LIMIT 1)");
  $stmt->bind_param("i", $quotation_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $fields = [
    'id',
    'calculation_id',
    'quotation_id',
    'quotation_number',
    'client_id',
    'company_id',
    'window_type',
    'height',
    'width',
    'quantity',
    'total_area',
    'frame_length',
    'sash_length',
    'net_sash_length',
    'beading_length',
    'interlock_length',
    'steel_quantity',
    'net_area',
    'net_rubber_quantity',
    'burshi_length',
    'locks',
    'dummy',
    'boofer',
    'stopper',
    'double_wheel',
    'net_wheel',
    'sada_screw',
    'fitting_screw',
    'self_screw',
    'rawal_plug',
    'silicon_white',
    'hole_caps',
    'water_caps',
    'material_cost',
    'hardware_cost',
    'glass_cost',
    'total_cost',
    'created_at',
    'updated_at'
  ];
  // Fetch client and company info for header
  $stmt = $conn->prepare("SELECT q.quotation_number, q.date, q.created_at, c.name AS client_name, c.phone AS client_phone, c.address AS client_address, co.name AS company_name FROM quotations q INNER JOIN clients c ON q.client_id = c.id INNER JOIN companies co ON q.company_id = co.id WHERE q.id = ? LIMIT 1");
  $stmt->bind_param("i", $quotation_id);
  $stmt->execute();
  $stmt->bind_result($quotation_number, $quotation_date, $created_at, $client_name, $client_phone, $client_address, $company_name);
  $stmt->fetch();
  $stmt->close();

  // Carded container and header
?>
  <div class="container quotation-container" style="max-width:1200px; margin:30px auto; background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,0.1); padding:24px; border:1px solid #e8f4fd;">
    <div class="quotation-header" style="display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #e3eaf1; padding-bottom:20px; margin-bottom:24px;">
      <div style="display:flex; align-items:center;">
        <img src="../logo/mod.jpg" alt="Company Logo" style="height:70px; margin-right:20px; border-radius:12px; border:2px solid #e8f4fd; box-shadow:0 4px 16px rgba(25,118,210,0.15);">
        <div>
          <div style="font-size:28px; font-weight:800; color:#1976d2; letter-spacing:1px; margin-bottom:4px;"><?= htmlspecialchars($company_name ?? 'MODERN WINDOWS & DOORS') ?></div>
          <div style="font-size:16px; color:#666; font-weight:500;">Calculation Details</div>
        </div>
      </div>
      <div style="text-align:right; background:#f8f9fa; padding:16px; border-radius:12px; border:1px solid #e3eaf1;">
        <div style="font-size:18px; color:#1976d2; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($quotation_number ?? '') ?></div>
        <div style="font-size:12px; color:#999; font-weight:400;">Created: <?= $created_at ? date('d/M/Y H:i', strtotime($created_at)) : '' ?></div>
      </div>
    </div>

    <!-- Client and Contact Information Row -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
      <!-- Client Information Section -->
      <div style="background:linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%); border-radius:10px; padding:16px; border:1px solid #e3eaf1; box-shadow:0 3px 12px rgba(25,118,210,0.08);">
        <h4 style="color:#1976d2; margin-bottom:12px; font-weight:700; font-size:16px;"><i class="fas fa-user me-2"></i>Client Information</h4>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #1976d2; font-size:14px;"><strong>Name:</strong> <?= htmlspecialchars($client_name ?? '') ?></div>
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #1976d2; font-size:14px;"><strong>Phone:</strong> <?= htmlspecialchars($client_phone ?? '') ?></div>
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #1976d2; font-size:14px;"><strong>Address:</strong> <?= htmlspecialchars($client_address ?? '') ?></div>
        </div>
      </div>
      <!-- Quotation Information Section -->
      <div style="background:linear-gradient(135deg, #f8f9fa 0%, #e8f5e9 100%); border-radius:10px; padding:16px; border:1px solid #e3eaf1; box-shadow:0 3px 12px rgba(76,175,80,0.08);">
        <h4 style="color:#4caf50; margin-bottom:12px; font-weight:700; font-size:16px;"><i class="fas fa-file-invoice me-2"></i>Quotation Details</h4>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #4caf50; font-size:14px;"><strong>Quotation #:</strong> <?= htmlspecialchars($quotation_number ?? '') ?></div>
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #4caf50; font-size:14px;"><strong>Date:</strong> <?= $quotation_date ? date('d/M/Y', strtotime($quotation_date)) : '' ?></div>
          <div style="background:#fff; padding:8px 10px; border-radius:6px; border-left:3px solid #4caf50; font-size:14px;"><strong>Status:</strong> <span style="color:#4caf50; font-weight:600;">Active</span></div>
        </div>
      </div>
    </div>

    <!-- Calculation Details Table -->
    <div style="background:#fff; border-radius:12px; border:1px solid #e3eaf1; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08);">
      <div style="background:linear-gradient(135deg, #1976d2 0%, #1565c0 100%); padding:16px 20px;">
        <h3 style="color:#fff; margin:0; font-weight:700; font-size:18px;"><i class="fas fa-calculator me-2"></i>Calculation Details</h3>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
          <thead>
            <tr style="background:#f8f9fa; border-bottom:2px solid #e3eaf1;">
              <th style="padding:12px 8px; text-align:left; font-weight:700; color:#1976d2; border-right:1px solid #e3eaf1;">Field</th>
              <th style="padding:12px 8px; text-align:center; font-weight:700; color:#1976d2; border-right:1px solid #e3eaf1;">Value</th>
              <th style="padding:12px 8px; text-align:center; font-weight:700; color:#1976d2; border-right:1px solid #e3eaf1;">Unit</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()):
              foreach ($fields as $field):
                if ($field === 'id' || $field === 'calculation_id' || $field === 'quotation_id' || $field === 'quotation_number' || $field === 'client_id' || $field === 'company_id' || $field === 'created_at' || $field === 'updated_at') continue;
                $value = $row[$field] ?? '';
                $unit = '';
                switch ($field) {
                  case 'height':
                  case 'width':
                  case 'frame_length':
                  case 'sash_length':
                  case 'net_sash_length':
                  case 'beading_length':
                  case 'interlock_length':
                  case 'burshi_length':
                    $unit = 'mm';
                    break;
                  case 'total_area':
                  case 'net_area':
                    $unit = 'sq ft';
                    break;
                  case 'material_cost':
                  case 'hardware_cost':
                  case 'glass_cost':
                  case 'total_cost':
                    $unit = 'Rs';
                    break;
                  case 'quantity':
                  case 'steel_quantity':
                  case 'net_rubber_quantity':
                  case 'locks':
                  case 'dummy':
                  case 'boofer':
                  case 'stopper':
                  case 'double_wheel':
                  case 'net_wheel':
                  case 'sada_screw':
                  case 'fitting_screw':
                  case 'self_screw':
                  case 'rawal_plug':
                  case 'silicon_white':
                  case 'hole_caps':
                  case 'water_caps':
                    $unit = 'pcs';
                    break;
                }
            ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                  <td style="padding:10px 8px; font-weight:600; color:#333; border-right:1px solid #e3eaf1; text-transform:capitalize;"><?= str_replace('_', ' ', $field) ?></td>
                  <td style="padding:10px 8px; text-align:center; color:#666; border-right:1px solid #e3eaf1;"><?= htmlspecialchars($value) ?></td>
                  <td style="padding:10px 8px; text-align:center; color:#999; font-size:12px;"><?= $unit ?></td>
                </tr>
            <?php
              endforeach;
              break; // Only show first row
            endwhile;
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex; justify-content:center; gap:15px; margin-top:30px; padding-top:20px; border-top:1px solid #e3eaf1;">
      <button onclick="window.print()" style="background:linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:600; cursor:pointer; box-shadow:0 4px 12px rgba(25,118,210,0.3); transition:all 0.3s;">
        <i class="fas fa-print me-2"></i>Print Quotation
      </button>
      <a href="index.php?page=report_quotation" style="background:linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:600; cursor:pointer; box-shadow:0 4px 12px rgba(108,117,125,0.3); transition:all 0.3s; text-decoration:none; display:inline-flex; align-items:center;">
        <i class="fas fa-arrow-left me-2"></i>Back to List
      </a>
    </div>
  </div>

  <style>
    @media print {
      body * {
        visibility: hidden;
      }

      .quotation-container,
      .quotation-container * {
        visibility: visible;
      }

      .quotation-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
        box-shadow: none;
        border: none;
      }

      .quotation-container button,
      .quotation-container a {
        display: none;
      }
    }

    @media (max-width: 768px) {
      .quotation-container {
        margin: 10px;
        padding: 15px;
      }

      .quotation-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
      }

      .quotation-header>div:first-child {
        flex-direction: column;
        text-align: center;
      }

      .quotation-header img {
        margin-right: 0;
        margin-bottom: 10px;
      }

      table[style*='width:100%; font-size:13px'] th,
      table[style*='width:100%; font-size:13px'] td {
        padding: 2px 4px !important;
      }
    }
  </style>
<?php
  exit;
}
?>

<style>
  .report-table {
    border:1px solid #dee2e6;
    border-radius:12px;
    overflow:hidden;
    width:100%;
    background:#fff;
    margin-bottom: 24px;
  }
  .report-table th {
    background: linear-gradient(90deg, #e3f2fd 0%, #f8f9fa 100%);
    color: #1976d2;
    font-weight: 700;
    font-size: 1rem;
    text-align: left;
    vertical-align: middle;
    padding: 12px 10px;
    border:1px solid #dee2e6;
  }
  .report-table td {
    font-size: 0.97rem;
    color: #333;
    text-align: left;
    vertical-align: middle;
    padding: 10px 10px;
    border:1px solid #dee2e6;
    background: #fff;
  }
  .report-table tbody tr:hover {
    background: #f1f8ff;
    transition: background 0.2s;
  }
  .report-table .actions {
    white-space: nowrap;
  }
</style>


<div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd; max-width:1200px; margin:30px auto;">
    <div class="d-flex align-items-center bg-light rounded shadow-sm p-3 mb-4" style="gap: 14px;">
      <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
        <i class="fas fa-file-signature"></i>
      </span>
      <div style="display: flex; flex-direction: column;">
        <span style="font-size: 1.5rem; font-weight: 600; color: #1976d2; letter-spacing: 1px;">Quotation Report</span>
        <small class="text-muted" style="font-size: 1rem;">Overview of all quotations for the selected company</small>
      </div>
    </div>

    <div class="card-body p-2">
      <form method="get" class="mb-2">
          <input type="hidden" name="page" value="report_quotation">
          <div class="d-flex align-items-center gap-2" style="overflow-x: auto; white-space: nowrap;">
              <!-- Company Dropdown (Slightly Smaller) -->
              <select name="company_id" class="form-select" style="height: 38px; width: 180px;" onchange="this.form.submit()">
                  <?php foreach ($companies as $comp): ?>
                      <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $company_id ? 'selected' : '' ?>>
                          <?= htmlspecialchars($comp['name']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>

              <!-- Search Input (Slightly Smaller) -->
              <input type="text" name="search_name" class="form-control" style="height: 38px; width: 220px;"
                  placeholder="Search client" value="<?= htmlspecialchars($search_name) ?>">

              <!-- Date Range (Compact) -->
              <div class="d-flex align-items-center gap-1">
                  <span class="fw-medium" style="font-size: 0.9rem;">From:</span>
                  <input type="date" name="from_date" class="form-control" style="height: 38px; width: 140px;"
                      value="<?= htmlspecialchars($from_date) ?>" onchange="this.form.submit()">
                  <span class="fw-medium" style="font-size: 0.9rem;">To:</span>
                  <input type="date" name="to_date" class="form-control" style="height: 38px; width: 140px;"
                      value="<?= htmlspecialchars($to_date) ?>" onchange="this.form.submit()">
              </div>

              <!-- Buttons (Compact) -->
              <div class="d-flex gap-2">
                  <?php if (!empty($search_name) || !empty($from_date) || !empty($to_date)): ?>
                      <a href="index.php?page=report_quotation&company_id=<?= $company_id ?>" class="btn btn-secondary btn-sm">
                          Clear
                      </a>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-success btn-sm" style="height: 38px; padding: 0 16px;">
                      Search
                  </button>
              </div>
          </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="report-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Client Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="clients-table-body">
          <?php if (empty($clients)): ?>
            <tr>
              <td colspan="5" class="text-center py-2">No clients found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($clients as $index => $client): ?>
              <?php
              // Fetch latest quotation_number for this client
              $quotation_number = '';
              $stmt_quote = $conn->prepare("SELECT quotation_number FROM window_calculation_details WHERE client_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
              if ($stmt_quote) {
                $stmt_quote->bind_param("ii", $client['id'], $company_id);
                $stmt_quote->execute();
                $stmt_quote->bind_result($quotation_number);
                $stmt_quote->fetch();
                $stmt_quote->close();
              }
              ?>
              <tr>
                <td><?= $offset + $index + 1 ?></td>
                <td><?= htmlspecialchars($client['name']) ?></td>
                <td><?= htmlspecialchars($client['phone']) ?></td>
                <td><?= htmlspecialchars($client['address']) ?></td>
                <td>
                  <?php if ($quotation_number): ?>
                    <a href="index.php?page=reports_worker&quotation_number=<?= urlencode($quotation_number) ?>&client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-primary">
                      <i class="fas fa-edit"></i> Worker
                    </a>
                  <?php else: ?>
                    <span class="text-muted">No Quotation</span>
                  <?php endif; ?>

                  <a href="client_quotation.php?client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-eye"></i> View
                  </a>

                  <form method="post" action="index.php?page=report_quotation&company_id=<?= $company_id ?>" style="display:inline;">
                    <input type="hidden" name="delete_client_id" value="<?= $client['id'] ?>">
                    <input type="hidden" name="company_id" value="<?= $company_id ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this?')">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <nav aria-label="Page navigation" class="pagination pagination-sm justify-content-center mt-3">
        <ul class="pagination pagination-sm justify-content-center">
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $page - 1 ?>">Previous</a>
            </li>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $page + 1 ?>">Next</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchNameInput');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function() {
        const searchValue = searchInput.value;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('search_name', searchValue);
        currentUrl.searchParams.set('p', '1'); // Reset to first page
        window.location.href = currentUrl.toString();
      }, 500);
    });

    // AJAX pagination (optional enhancement)
    document.querySelectorAll('.pagination .page-link').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        window.location.href = href;
      });
    });
  });
</script>