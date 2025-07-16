<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$isEdit = false;
$expense = [
    'expense_date' => '',
    'company_id' => '',
    'description' => '',
    'total_amount' => '',
];
$items = [];

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $edit_id = (int)$_GET['edit_id'];
    // Fetch expense
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense = $result->fetch_assoc();

    // Fetch items
    $stmt = $conn->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = $_POST['expense_date'];
    $company_id = $_POST['company_id'];
    $description = $_POST['description'];
    $total_amount = $_POST['total_amount'];
    $item_names = $_POST['item_name'];
    $amounts = $_POST['amount'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if ($edit_id) {
        // Update expense
        $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, company_id=?, description=?, total_amount=? WHERE id=?");
        $stmt->bind_param("sissd", $expense_date, $company_id, $description, $total_amount, $edit_id);
        $stmt->execute();

        // Delete old items
        $stmt = $conn->prepare("DELETE FROM expense_items WHERE expense_id=?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();

        // Insert new items
        $stmt = $conn->prepare("INSERT INTO expense_items (expense_id, item_name, amount) VALUES (?, ?, ?)");
        foreach ($item_names as $idx => $name) {
            $amt = $amounts[$idx];
            $stmt->bind_param("isd", $edit_id, $name, $amt);
            $stmt->execute();
        }
        echo '<div class="alert alert-success">Expense updated successfully.</div>';
        exit;
    } else {
        // Insert new expense
        $stmt = $conn->prepare("INSERT INTO expenses (expense_date, company_id, description, total_amount, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sisd", $expense_date, $company_id, $description, $total_amount);
        $stmt->execute();
        $expense_id = $stmt->insert_id;

        // Insert items
        $stmt = $conn->prepare("INSERT INTO expense_items (expense_id, item_name, amount) VALUES (?, ?, ?)");
        foreach ($item_names as $idx => $name) {
            $amt = $amounts[$idx];
            $stmt->bind_param("isd", $expense_id, $name, $amt);
            $stmt->execute();
        }
        echo '<div class="alert alert-success">Expense added successfully.</div>';
        exit;
    }
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name ASC");
?>

<div class="modal-header">
    <h5 class="modal-title"><?= $isEdit ? 'Edit Expense' : 'Add New Expense' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form method="POST" action="" autocomplete="off">
    <?php if ($isEdit): ?>
        <input type="hidden" name="edit_id" value="<?= htmlspecialchars($_GET['edit_id']) ?>">
    <?php endif; ?>
    <div class="row mb-3">
    <div class="col-md-6">
            <label for="company_id" class="form-label">Company</label>
            <select class="form-control" name="company_id" id="company_id" required>
                <option value="">Select Company</option>
                <?php while ($row = $companies->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= $row['id'] == $expense['company_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="expense_date" class="form-label">Date</label>
            <input type="date" class="form-control" name="expense_date" id="expense_date" value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
        </div>
       
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control" name="description" id="description" required><?= htmlspecialchars($expense['description']) ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Items</label>
        <div id="items-container">
            <?php if ($isEdit && $items): ?>
                <?php foreach ($items as $idx => $item): ?>
                    <div class="row item-row mb-2" id="item-row-<?= $idx ?>">
                        <div class="col-md-6 mb-2">
                            <input type="text" name="item_name[]" class="form-control" placeholder="Item name" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <input type="number" name="amount[]" class="form-control item-amount" placeholder="Amount" step="0.01" value="<?= htmlspecialchars($item['amount']) ?>" required>
                        </div>
                        <div class="col-md-2 mb-2 d-flex align-items-center">
                            <button type="button" class="remove-item btn btn-danger btn-sm" data-row="item-row-<?= $idx ?>"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="row item-row mb-2" id="item-row-0">
                    <div class="col-md-6 mb-2">
                        <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="number" name="amount[]" class="form-control item-amount" placeholder="Amount" step="0.01" required>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-center">
                        <button type="button" class="remove-item btn btn-danger btn-sm" data-row="item-row-0"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-item"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    <div class="mb-3">
        <label for="total_amount" class="form-label">Total Amount</label>
        <input type="number" class="form-control" name="total_amount" id="total_amount" value="<?= htmlspecialchars($expense['total_amount']) ?>" step="0.01" required readonly>
    </div>
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Expense' : 'Add Expense' ?></button>
</form>
<script>
if (typeof initAddExpenseModalJS === 'function') {
    initAddExpenseModalJS();
}
</script>

<style>
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .card {
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
        border-radius: 12px;
    }

    .form-label {
        font-weight: 500;
    }

    .item-row .form-control {
        min-width: 120px;
    }

    .remove-item {
        background: #f44336;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 6px 12px;
        margin-left: 8px;
    }

    .remove-item:hover {
        background: #d32f2f;
    }

    .btn-add-item {
        background: #0d6efd;
        color: #fff;
        border-radius: 4px;
        margin-top: 10px;
    }

    .btn-add-item:hover {
        background: #0b5ed7;
    }

    .total-amount {
        font-weight: bold;
        font-size: 18px;
        margin-top: 20px;
    }

    .message {
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .error {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }

    .success {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }
</style>

<div class="page-header">
    <h2 class="mb-0"><i class="fas fa-plus me-2 text-primary"></i>Add New Expense</h2>
    <a href="reports_expenses.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Expenses</a>
</div>
<div class="card p-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-3"> <?= $error ?> </div>
    <?php elseif (isset($success)): ?>
        <div class="alert alert-success mb-3"> <?= $success ?> </div>
    <?php endif; ?>
    <form method="POST" action="" autocomplete="off">
        <div class="row mb-3">
            <div class="col-md-4 mb-3">
                <label class="form-label">Company</label>
                <select name="company_id" class="form-select" required>
                    <option value="">Select Company</option>
                    <?php while ($company = $companies->fetch_assoc()): ?>
                        <option value="<?= $company['id'] ?>"><?= $company['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="expense_date" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="1"></textarea>
            </div>
        </div>
        <h5 class="mb-3">Expense Items</h5>
        <div id="items-container">
            <div class="row item-row mb-2">
                <div class="col-md-6 mb-2">
                    <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
                </div>
                <div class="col-md-4 mb-2">
                    <input type="number" name="amount[]" class="form-control item-amount" placeholder="Amount" step="0.01" required>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <!-- Remove button will be added dynamically -->
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-add-item" id="add-item"><i class="fas fa-plus"></i> Add Another Item</button>
        <div class="total-amount mt-3">
            <label class="form-label me-2">Total Amount:</label>
            <input type="number" name="total_amount" class="form-control d-inline-block w-auto" style="width: 180px;" step="0.01" required readonly>
        </div>
        <button type="submit" class="btn btn-success mt-4"><i class="fas fa-save me-1"></i> Save Expense</button>
    </form>
</div>

<script>
    $(document).ready(function() {
        let itemCount = 1;
        // Add new item row
        $('#add-item').click(function() {
            const newRow = `
        <div class="row item-row mb-2" id="item-row-${itemCount}">
            <div class="col-md-6 mb-2">
                <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
            </div>
            <div class="col-md-4 mb-2">
                <input type="number" name="amount[]" class="form-control item-amount" placeholder="Amount" step="0.01" required>
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-center">
                <button type="button" class="remove-item btn btn-danger btn-sm" data-row="item-row-${itemCount}"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `;
            $('#items-container').append(newRow);
            itemCount++;
        });
        // Remove item row
        $(document).on('click', '.remove-item', function() {
            $('#' + $(this).data('row')).remove();
            calculateTotal();
        });
        // Calculate total amount when item amounts change
        $(document).on('input', '.item-amount', function() {
            calculateTotal();
        });

        function calculateTotal() {
            let total = 0;
            $('.item-amount').each(function() {
                const val = parseFloat($(this).val()) || 0;
                total += val;
            });
            $('input[name="total_amount"]').val(total.toFixed(2));
        }
    });
</script>