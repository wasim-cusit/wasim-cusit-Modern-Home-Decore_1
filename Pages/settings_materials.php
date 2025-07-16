<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $length = $_POST['length'];
    $price_per_foot = $_POST['price_per_foot'];
    $bundle_quantity = $_POST['bundle_quantity'];
    $bundle_price = $_POST['bundle_price'];
    $company_id = $_POST['company_id'];

    if (isset($_POST['edit_id'])) {
        // Update existing material
        $edit_id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE materials SET name = ?, length = ?, price_per_foot = ?, bundle_quantity = ?, bundle_price = ?, company_id = ? WHERE id = ?");
        $stmt->bind_param("sddddii", $name, $length, $price_per_foot, $bundle_quantity, $bundle_price, $company_id, $edit_id);
        $stmt->execute();
        $success = "Material updated successfully!";
    } else {
        // Insert new material
        $stmt = $conn->prepare("INSERT INTO materials (name, length, price_per_foot, bundle_quantity, bundle_price, company_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddddi", $name, $length, $price_per_foot, $bundle_quantity, $bundle_price, $company_id);
        $stmt->execute();
        $success = "Material added successfully!";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $success = "Material deleted successfully!";
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name");

// Get selected company from session or default to first
$selected_company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : 1;
if (isset($_GET['company_id'])) {
    $selected_company_id = $_GET['company_id'];
    $_SESSION['selected_company_id'] = $selected_company_id;
}

// Fetch materials for selected company
$materials = $conn->query("SELECT * FROM materials WHERE company_id = $selected_company_id ORDER BY name");

// Handle edit
$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM materials WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $edit_id, $selected_company_id);
    $stmt->execute();
    $edit_material = $stmt->get_result()->fetch_assoc();
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

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 10px;
        align-items: center;
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
        width: 180px;
        box-sizing: border-box;
    }

    .company-selector {
        margin-bottom: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    .single-line-form {
        flex-wrap: nowrap;
        gap: 20px;
    }

    .single-line-form .form-group {
        margin-bottom: 0;
    }

    .single-line-form .action-buttons {
        margin-left: 10px;
    }

    .professional-form {
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

    .form-group input[name="name"] {
        width: 260px;
    }

    .form-group input[name="length"],
    .form-group input[name="price_per_foot"],
    .form-group input[name="bundle_quantity"],
    .form-group input[name="bundle_price"] {
        width: 120px;
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
    }
</style>

<div class="container">
    <h2><i class="fas fa-cogs me-2"></i>Materials Settings</h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Company Selector -->
    <div class="company-selector">
        <label for="company-select">Select Company:</label>
        <select id="company-select" onchange="window.location.href='?page=settings_materials&company_id=' + this.value">
            <?php while ($company = $companies->fetch_assoc()): ?>
                <option value="<?= $company['id'] ?>" <?= $company['id'] == $selected_company_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($company['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h3><?= $edit_material ? 'Edit Material' : 'Add New Material' ?></h3>
        <form method="POST" class="professional-form">
            <?php if ($edit_material): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_material['id'] ?>">
            <?php endif; ?>
            
            <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
            
            <div class="form-group-grouped">
                <div class="form-group">
                    <label for="name">Material Name:</label>
                    <input type="text" id="name" name="name" value="<?= $edit_material ? htmlspecialchars($edit_material['name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="length">Length (ft):</label>
                    <input type="number" id="length" name="length" step="0.01" value="<?= $edit_material ? $edit_material['length'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price_per_foot">Price per Foot:</label>
                    <input type="number" id="price_per_foot" name="price_per_foot" step="0.01" value="<?= $edit_material ? $edit_material['price_per_foot'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bundle_quantity">Bundle Quantity:</label>
                    <input type="number" id="bundle_quantity" name="bundle_quantity" value="<?= $edit_material ? $edit_material['bundle_quantity'] : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bundle_price">Bundle Price:</label>
                    <input type="number" id="bundle_price" name="bundle_price" step="0.01" value="<?= $edit_material ? $edit_material['bundle_price'] : '' ?>" required>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save me-1"></i><?= $edit_material ? 'Update Material' : 'Add Material' ?>
                </button>
                <?php if ($edit_material): ?>
                    <a href="?page=settings_materials&company_id=<?= $selected_company_id ?>" class="btn btn-cancel">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Materials Table -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Length (ft)</th>
                <th>Price per Foot</th>
                <th>Bundle Quantity</th>
                <th>Bundle Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($material = $materials->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($material['name']) ?></td>
                    <td><?= $material['length'] ?></td>
                    <td>Rs <?= number_format($material['price_per_foot'] ?? 0, 2) ?></td>
                    <td><?= $material['bundle_quantity'] ?></td>
                    <td>Rs <?= number_format($material['bundle_price'] ?? 0, 2) ?></td>
                    <td>
                        <a href="?page=settings_materials&company_id=<?= $selected_company_id ?>&edit=<?= $material['id'] ?>" class="btn btn-edit">Edit</a>
                        <a href="?page=settings_materials&company_id=<?= $selected_company_id ?>&delete=<?= $material['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this material?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>