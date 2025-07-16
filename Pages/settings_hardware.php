<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $bundle_quantity = $_POST['bundle_quantity'];
    $bundle_price = $_POST['bundle_price'];
    $company_id = $_POST['company_id'];

    if (isset($_POST['edit_id'])) {
        // Update existing hardware
        $edit_id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE hardware SET name = ?, quantity = ?, price = ?, bundle_quantity = ?, bundle_price = ?, company_id = ? WHERE id = ?");
        $stmt->bind_param("sddddii", $name, $quantity, $price, $bundle_quantity, $bundle_price, $company_id, $edit_id);
        $stmt->execute();
        $success = "Hardware updated successfully!";
    } else {
        // Insert new hardware
        $stmt = $conn->prepare("INSERT INTO hardware (name, quantity, price, bundle_quantity, bundle_price, company_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddddi", $name, $quantity, $price, $bundle_quantity, $bundle_price, $company_id);
        $stmt->execute();
        $success = "Hardware added successfully!";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM hardware WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $success = "Hardware deleted successfully!";
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name");

// Get selected company from session or default to first
$selected_company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : 1;
if (isset($_GET['company_id'])) {
    $selected_company_id = $_GET['company_id'];
    $_SESSION['selected_company_id'] = $selected_company_id;
}

// Fetch hardware for selected company
$hardware = $conn->query("SELECT * FROM hardware WHERE company_id = $selected_company_id ORDER BY name");

// Handle edit
$edit_hardware = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM hardware WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $edit_id, $selected_company_id);
    $stmt->execute();
    $edit_hardware = $stmt->get_result()->fetch_assoc();
}
?>

<style>
    h2 {
        color: #444;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-top: 0;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 20px;
    }

    th,
    td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background-color: #333;
        color: white;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    .btn {
        display: inline-block;
        padding: 6px 12px;
        margin: 0 2px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
    }



    .form-container {
        margin: 20px 0;
        padding: 15px;
        background: #f2f2f2;
        border-radius: 4px;
        max-width: 100%;
        overflow-x: auto;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 120px;
    }

    label {
        font-weight: 600;
        white-space: nowrap;
        margin-bottom: 2px;
    }

    input[type="text"],
    input[type="number"],
    select {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .form-group input[name="name"] {
        width: 260px;
    }

    .form-group input[name="quantity"],
    .form-group input[name="price"],
    .form-group input[name="bundle_quantity"],
    .form-group input[name="bundle_price"] {
        width: 120px;
    }

    .professional-form {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        align-items: flex-end;
        max-width: 100%;
    }

    .form-group-grouped {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .form-group-grouped .form-group {
        margin-bottom: 0;
    }

    .professional-form .action-buttons {
        margin-left: 10px;
        align-items: flex-end;
    }

    @media (max-width: 900px) {
        .professional-form {
            flex-direction: column;
            gap: 15px;
        }

        .form-group-grouped {
            flex-direction: column;
            gap: 10px;
        }

        .form-group input[name="name"] {
            width: 100%;
        }

        .form-group input[name="quantity"],
        .form-group input[name="price"],
        .form-group input[name="bundle_quantity"],
        .form-group input[name="bundle_price"] {
            width: 100%;
        }

        .professional-form .action-buttons {
            margin-left: 0;
        }
    }

    .company-selector {
        margin-bottom: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }
</style>

<div class="container">
    <h2><i class="fas fa-tools me-2"></i>Hardware Settings</h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Company Selector -->
    <div class="company-selector">
        <label for="company-select">Select Company:</label>
        <select id="company-select" onchange="window.location.href='?page=settings_hardware&company_id=' + this.value">
            <?php while ($company = $companies->fetch_assoc()): ?>
                <option value="<?= $company['id'] ?>" <?= $company['id'] == $selected_company_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($company['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h3><?= $edit_hardware ? 'Edit Hardware' : 'Add New Hardware' ?></h3>
        <form method="POST" class="professional-form">
            <?php if ($edit_hardware): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_hardware['id'] ?>">
            <?php endif; ?>
            
            <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
            
            <div class="form-group-grouped">
                <div class="form-group">
                    <label for="name">Hardware Name:</label>
                    <input type="text" id="name" name="name" value="<?= $edit_hardware ? htmlspecialchars($edit_hardware['name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" value="<?= $edit_hardware ? $edit_hardware['quantity'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Price:</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?= $edit_hardware ? $edit_hardware['price'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bundle_quantity">Bundle Quantity:</label>
                    <input type="number" id="bundle_quantity" name="bundle_quantity" value="<?= $edit_hardware ? $edit_hardware['bundle_quantity'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bundle_price">Bundle Price:</label>
                    <input type="number" id="bundle_price" name="bundle_price" step="0.01" value="<?= $edit_hardware ? $edit_hardware['bundle_price'] : '' ?>" required>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save me-1"></i><?= $edit_hardware ? 'Update Hardware' : 'Add Hardware' ?>
                </button>
                <?php if ($edit_hardware): ?>
                    <a href="?page=settings_hardware&company_id=<?= $selected_company_id ?>" class="btn btn-cancel">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Hardware Table -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Bundle Quantity</th>
                <th>Bundle Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($hw = $hardware->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($hw['name']) ?></td>
                    <td><?= $hw['quantity'] ?></td>
                    <td>Rs <?= number_format($hw['price'] ?? 0, 2) ?></td>
                    <td><?= $hw['bundle_quantity'] ?></td>
                    <td>Rs <?= number_format($hw['bundle_price'] ?? 0, 2) ?></td>
                    <td>
                        <a href="?page=settings_hardware&company_id=<?= $selected_company_id ?>&edit=<?= $hw['id'] ?>" class="btn btn-edit">Edit</a>
                        <a href="?page=settings_hardware&company_id=<?= $selected_company_id ?>&delete=<?= $hw['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this hardware?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>