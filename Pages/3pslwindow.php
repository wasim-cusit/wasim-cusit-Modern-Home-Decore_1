<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../quotation_handler.php');

// Debug output
echo "<!-- Session: " . print_r($_SESSION, true) . " -->";
echo "<!-- GET: " . print_r($_GET, true) . " -->";

// Check if required parameters are passed via GET
$required_params = ['company_id', 'product_type', 'sub_type', 'client_id'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param])) {
        die('<div class="alert alert-danger">Missing required parameter: ' . $param . '</div>');
    }
}

$company_id = (int)$_GET['company_id'];
$product_type = $_GET['product_type'];
$sub_type = $_GET['sub_type'];
$client_id = (int)$_GET['client_id'];

// Set client ID in JavaScript scope
echo "<script>window.currentClientId = $client_id;</script>";
echo "<script>window.currentClientId = $client_id; window.currentCompanyId = $company_id;</script>";

// Database connection
try {
    // Verify client belongs to selected company
    $stmt = $conn->prepare("SELECT id, name FROM clients WHERE id = ? AND company_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $client_id, $company_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();

    if (!$client) {
        throw new Exception("Client not found or doesn't belong to selected company");
    }

    // Fetch all prices
    $prices = ['materials' => [], 'hardware' => [], 'additional' => []];

    // Fetch material prices
    $stmt = $conn->prepare("SELECT name, price_per_foot FROM materials WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $prices['materials'][$row['name']] = $row['price_per_foot'];
                $prices['additional'][$row['name']] = $row['price_per_foot'];
            }
        }
        $stmt->close();
    }

    // Fetch hardware prices
    $stmt = $conn->prepare("SELECT name, price FROM hardware WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $prices['hardware'][$row['name']] = $row['price'];
            }
        }
        $stmt->close();
    }

    // Default glass price
    $glass_price_per_sqft = 200;
    $conn->close();

} catch (Exception $e) {
    die('<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>3PSL Window Calculator</title>
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }
    .calculator-container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      overflow: hidden;
      transition: all 0.3s ease;
      margin: 20px 0;
      /* You can increase or decrease the max-width below to control the form width */
      max-width: 1600px;
      margin-left: auto;
      margin-right: auto;
    }
    .calculator-container:hover {
      box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }
    .calculator-header {
      background: linear-gradient(to right, #4b6cb7, #182848);
      color: white;
      padding: 20px;
      margin-bottom: 20px;
    }
    .form-control, .form-select {
      border-radius: 8px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      font-size: 0.98rem;
      transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
      border-color: #4b6cb7;
      box-shadow: 0 0 0 0.25rem rgba(75, 108, 183, 0.25);
    }
    .input-group-unit {
      width: 90px;
    }
    .btn-calculate {
      background: linear-gradient(to right, #4b6cb7, #182848);
      border: none;
      padding: 8px 18px;
      font-weight: 600;
      font-size: 1rem;
      letter-spacing: 1px;
      color: #fff !important;
      border-radius: 8px;
      transition: all 0.3s;
      min-width: 120px;
    }
    .btn-calculate:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      background: linear-gradient(to right, #182848, #4b6cb7);
      color: #fff !important;
    }
    .results-container {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
      border-left: 5px solid #4b6cb7;
      width: 100%;
      overflow-x: auto;
    }
    .results-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }
    .result-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .result-section {
      margin-bottom: 15px;
    }
    .section-title {
      color: #4b6cb7;
      border-bottom: 1px solid #4b6cb7;
      padding-bottom: 5px;
      margin-bottom: 10px;
      font-size: 16px;
    }
    .result-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }
    .result-label {
      font-weight: 600;
      color: #555;
    }
    .result-value {
      text-align: right;
    }
    .price-value {
      color: #28a745;
      font-weight: bold;
    }
    .result-total {
      background: #f0f7ff;
      padding: 10px;
      border-radius: 6px;
      margin-top: 15px;
    }
    .total-item {
      font-weight: bold;
    }
    .grand-total {
      font-size: 18px;
      color: #182848;
    }
    .quotation-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    .sketch-container {
      margin-top: 30px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 10px;
    }
    .alert-quotation {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      min-width: 300px;
    }
    .result-note {
      font-size: 0.8rem;
      color: #666;
      font-style: italic;
    }
    .no-length {
      color: #999;
    }
    .window-part {
      cursor: pointer;
      transition: all 0.3s;
    }
    .window-part:hover {
      opacity: 0.8;
    }
    @media (max-width: 768px) {
      .calculator-container {
        margin: 10px;
      }
      .quotation-buttons {
        flex-direction: column;
        align-items: center;
      }
      .results-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
  <body>
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12">
          <div class="calculator-container animate__animated animate__fadeIn">
            <div class="calculator-header text-center">
              <h2 class="animate__animated animate__fadeInDown">3PSL Window Calculator</h2>
              <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs</p>
            </div>
            <div class="p-4">
              <form id="windowCalcForm" autocomplete="off">
                <div class="row g-3 align-items-end">
                  <!-- Width -->
                <div class="col-md-4 mb-3">
                    <label for="width" class="form-label"><i class="fa-solid fa-arrows-left-right me-1"></i>Width</label>
                    <div class="input-group">
                      <input type="number" class="form-control" id="width" placeholder="Enter width" step="0.01" min="0.01">
                      <select class="form-select input-group-unit" id="widthUnit">
                        <option value="ft">feet</option>
                        <option value="in">inches</option>
                        <option value="cm">centimeters</option>
                        <option value="mm">millimeters</option>
                      </select>
                    </div>
                  </div>
                  <!-- Height -->
                  <div class="col-md-4 mb-3">
                    <label for="height" class="form-label"><i class="fa-solid fa-arrows-up-down me-1"></i>Height</label>
                    <div class="input-group">
                      <input type="number" class="form-control" id="height" placeholder="Enter height" step="0.01" min="0.01">
                      <select class="form-select input-group-unit" id="heightUnit">
                        <option value="ft">feet</option>
                        <option value="in">inches</option>
                        <option value="cm">centimeters</option>
                        <option value="mm">millimeters</option>
                      </select>
                    </div>
                  </div>
                  <!-- quantity -->
                  <div class="col-md-2 mb-3">
                    <label for="quantity" class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Quantity</label>
                    <input type="number" class="form-control" id="quantity" placeholder="Enter quantity" min="1" value="1">
                  </div>
                  <div class="col-md-2 mb-3 d-grid">
                    <button type="button" id="calculateBtn" class="btn btn-calculate btn-lg mt-md-0 mt-3">
                      <i class="fas fa-calculator me-2"></i>Calculate
                    </button>
                  </div>
                </div>
              </form>
              <div class="results-container mt-4" id="output" style="display: none;"></div>
              <div class="sketch-container text-center" id="sketch" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Database prices from PHP
    const prices = <?php echo json_encode($prices); ?>;
    const glassPricePerSqft = <?php echo $glass_price_per_sqft; ?>;
    
    // Utility functions
    function convertToFeet(value, unit) {
      const conversions = {
        'in': 12,
        'cm': 30.48,
        'mm': 304.8,
        'ft': 1
      };
      return value / (conversions[unit] || 1);
    }
    
    function formatCurrency(amount) {
      return 'Rs. ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function formatUnit(value, unit) {
      switch(unit) {
        case 'in': return `${value} in`;
        case 'cm': return `${value} cm`;
        case 'mm': return `${value} mm`;
        default: return `${value} ft`;
      }
    }
    
    function showError(message) {
      const output = document.getElementById("output");
      output.innerHTML = `
        <div class="alert alert-danger" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>${message}
        </div>
      `;
      output.style.display = "block";
      document.getElementById("sketch").style.display = "none";
    }
    
    function createResultRow(label, value, amount, isTotal = false, extraClass = '') {
      const amountDisplay = amount ? `<span class="price-value ${extraClass}">${amount}</span>` : '';
      return `
        <div class="result-item ${isTotal ? 'total-item' : ''}">
          <span class="result-label">${label}</span>
          <span class="result-value">${value} ${amountDisplay}</span>
        </div>
      `;
    }
    
    // Main calculation function for 3PSL windows
    function calculate() {
      // Get input values
      const heightValue = parseFloat(document.getElementById("height").value);
      const heightUnit = document.getElementById("heightUnit").value;
      const widthValue = parseFloat(document.getElementById("width").value);
      const widthUnit = document.getElementById("widthUnit").value;
      const quantity = parseInt(document.getElementById("quantity").value);
      
      // Validate inputs
      if (isNaN(heightValue)) return showError("Please enter a valid height");
      if (isNaN(widthValue)) return showError("Please enter a valid width");
      if (isNaN(quantity) || quantity < 1) return showError("Please enter a valid quantity (minimum 1)");
      
      // Convert to feet for calculations
      const heightFt = convertToFeet(heightValue, heightUnit);
      const widthFt = convertToFeet(widthValue, widthUnit);
      
      if (heightFt <= 0 || widthFt <= 0) return showError("Height and width must be positive values");
      
      // 3PSL specific calculations (keeping your original formulas)
      const perimeter = (heightFt + widthFt) * 2;
      const area = heightFt * widthFt;
      const totalArea = area * quantity;
      
      // Material lengths (3PSL specific)
      const frameLength = (perimeter / 19) * quantity;
      const sashLength = frameLength * 1.8;
      const netSashLength = sashLength / 2;
      const beadingLength = sashLength;
      const interlockLength = sashLength / 3;
      
      // Material requirements
      const steel = ((frameLength + sashLength + netSashLength) * 19) / 8;
      const net = (totalArea / 3) * 2;
      const netRubber = (netSashLength * 19) / 80;
      const burshi = (widthFt * 4 + heightFt * 7) * quantity;
      
      // Calculate costs
      const calculateCost = (length, material) => length * (prices.materials[material] || 0);
      
      const frameCost = calculateCost(frameLength * 19, 'Frame');
      const sashCost = calculateCost(sashLength * 19, 'Sash');
      const netSashCost = calculateCost(netSashLength * 19, 'Net Sash');
      const beadingCost = calculateCost(beadingLength * 19, 'Beading');
      const interlockCost = calculateCost(interlockLength * 19, 'Interlock');
      
      const steelCost = steel * (prices.additional['Steel'] || 0);
      const netCost = net * (prices.additional['Net'] || 0);
      const netRubberCost = netRubber * (prices.additional['Net Rubber'] || 0);
      const burshiCost = burshi * (prices.additional['Burshi'] || 0);
      
      // Hardware calculations for 3PSL
      const hardwareItems = {
        'Locks': quantity * 2,
        'Dummy': quantity * 2,
        'Boofer': quantity * 4,
        'Stopper': quantity * 4,
        'Double Wheel': quantity * 4,
        'Net Wheel': quantity * 4
      };
      
      // Screws and other items
      const otherHardware = {
        'Fitting Screw': quantity * 30,
        'Self Screw': quantity * 70,
        'Sada Screw': quantity * 40,
        'Rawal Plug': quantity * 30,
        'Silicon White': quantity * 2,
        'Hole Caps': quantity * 30,
        'Water Caps': quantity * 2
      };
      
      // Calculate hardware costs
      let totalHardwareCost = 0;
      const hardwareCosts = {};
      
      [...Object.entries(hardwareItems), ...Object.entries(otherHardware)].forEach(([name, qty]) => {
        const cost = qty * (prices.hardware[name] || 0);
        hardwareCosts[name] = cost;
        totalHardwareCost += cost;
      });
      
      // Glass calculation
      const glassCost = totalArea * glassPricePerSqft;
      
      // Calculate totals
      const totalMaterialCost = frameCost + sashCost + netSashCost + beadingCost + interlockCost + 
                               steelCost + netCost + netRubberCost + burshiCost;
      const grandTotal = totalMaterialCost + totalHardwareCost + glassCost;
      
      // Generate output HTML (horizontal card layout)
      const outputHTML = `
        <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>3PSL Window Calculation Results</h5>
        <div class="results-grid">
          <!-- Window Details Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Window Details</h6>
              ${createResultRow('Quantity', quantity, '')}
              ${createResultRow('Height', `${heightValue} ${heightUnit} (${heightFt.toFixed(2)} ft)`, '')}
              ${createResultRow('Width', `${widthValue} ${widthUnit} (${widthFt.toFixed(2)} ft)`, '')}
              ${createResultRow('Total Area', `${totalArea.toFixed(2)} sft`, '')}
            </div>
          </div>
          <!-- Main Materials Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Main Materials</h6>
              ${createResultRow('Frame', frameLength.toFixed(2) + ' lengths', formatCurrency(frameCost))}
              ${createResultRow('Sash', sashLength.toFixed(2) + ' lengths', formatCurrency(sashCost))}
              ${createResultRow('Net Sash', netSashLength.toFixed(2) + ' lengths', formatCurrency(netSashCost))}
              ${createResultRow('Beading', beadingLength.toFixed(2) + ' lengths', formatCurrency(beadingCost))}
              ${createResultRow('Interlock', interlockLength.toFixed(2) + ' lengths', formatCurrency(interlockCost))}
            </div>
          </div>
          <!-- Material Requirements Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Material Requirements</h6>
              ${createResultRow('Steel', steel.toFixed(2) + ' kg', formatCurrency(steelCost))}
              ${createResultRow('Net', net.toFixed(2) + ' sft', formatCurrency(netCost))}
              ${createResultRow('Net Rubber', netRubber.toFixed(2), formatCurrency(netRubberCost))}
              ${createResultRow('Burshi', burshi.toFixed(2) + ' ft', formatCurrency(burshiCost))}
            </div>
          </div>
          <!-- Hardware Items Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Hardware Items</h6>
              ${Object.entries(hardwareItems).map(([name, qty]) => 
                createResultRow(name, qty + ' pcs', formatCurrency(hardwareCosts[name]))).join('')}
              ${Object.entries(otherHardware).map(([name, qty]) => 
                createResultRow(name, qty + (name === 'Self Screw' ? ' kg' : ' pcs'), formatCurrency(hardwareCosts[name]))).join('')}
            </div>
          </div>
          <!-- Glass Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Glass</h6>
              ${createResultRow('6mm Plain Glass', totalArea.toFixed(2) + ' sft', formatCurrency(glassCost))}
            </div>
          </div>
          <!-- Cost Summary Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Cost Summary</h6>
              <div class="result-total">
                ${createResultRow('Total Materials Cost', '', formatCurrency(totalMaterialCost), true)}
                ${createResultRow('Total Hardware Cost', '', formatCurrency(totalHardwareCost), true)}
                ${createResultRow('Glass Cost', '', formatCurrency(glassCost), true)}
                ${createResultRow('Grand Total', '', formatCurrency(grandTotal), true, 'grand-total')}
              </div>
            </div>
          </div>
        </div>
      `;
      
      // Display results
     function prepareFullCalculation(heightFt, widthFt, area) {
    return {
        dimensions: {
            height: heightFt,
            width: widthFt,
            quantity: quantity,
            area: heightFt * widthFt * quantity
        },
        materials: {
            frame: { length: frameLength.toFixed(2), cost: frameCost },
            sash: { length: sashLength.toFixed(2), cost: sashCost },
            netSash: { length: netSashLength.toFixed(2), cost: netSashCost },
            beading: { length: beadingLength.toFixed(2), cost: beadingCost },
            interlock: { length: interlockLength.toFixed(2), cost: interlockCost },
            steel: { quantity: steel.toFixed(2), cost: steelCost },
            net: { area: net.toFixed(2), cost: netCost },
            netRubber: { quantity: netRubber.toFixed(2), cost: netRubberCost },
            burshi: { length: burshi.toFixed(2), cost: burshiCost }
        },
        hardware: {
            locks: { quantity: quantity * 2, cost: hardwareCosts['Locks'] || 0 },
            dummy: { quantity: quantity * 2, cost: hardwareCosts['Dummy'] || 0 },
            boofer: { quantity: quantity * 4, cost: hardwareCosts['Boofer'] || 0 },
            stopper: { quantity: quantity * 4, cost: hardwareCosts['Stopper'] || 0 },
            doubleWheel: { quantity: quantity * 4, cost: hardwareCosts['Double Wheel'] || 0 },
            netWheel: { quantity: quantity * 4, cost: hardwareCosts['Net Wheel'] || 0 },
            sadaScrew: { quantity: quantity * 40, cost: hardwareCosts['Sada Screw'] || 0 },
            fittingScrew: { quantity: quantity * 30, cost: hardwareCosts['Fitting Screw'] || 0 },
            selfScrew: { quantity: quantity * 70, cost: hardwareCosts['Self Screw'] || 0 },
            rawalPlug: { quantity: quantity * 30, cost: hardwareCosts['Rawal Plug'] || 0 },
            siliconWhite: { quantity: quantity * 2, cost: hardwareCosts['Silicon White'] || 0 },
            holeCaps: { quantity: quantity * 30, cost: hardwareCosts['Hole Caps'] || 0 },
            waterCaps: { quantity: quantity * 2, cost: hardwareCosts['Water Caps'] || 0 }
        },
        totals: {
            materials: totalMaterialCost,
            hardware: totalHardwareCost,
            glass: glassCost,
            grandTotal: grandTotal
        }
    };
}

const output = document.getElementById("output");
output.innerHTML = outputHTML;
output.style.display = "block";

const quoteBtnContainer = document.createElement('div');
quoteBtnContainer.className = 'quotation-buttons';

const getCalculationData = () => {
    const heightValue = parseFloat(document.getElementById("height").value);
    const heightUnit = document.getElementById("heightUnit").value;
    const widthValue = parseFloat(document.getElementById("width").value);
    const widthUnit = document.getElementById("widthUnit").value;

    let heightFt = convertToFeet(heightValue, heightUnit);
    let widthFt = convertToFeet(widthValue, widthUnit);
    let area = heightFt * widthFt * quantity;

    return {
        area,
        quantity,
        totalCost: grandTotal,
        height: heightFt,
        width: widthFt,
        unit: 'ft',
        _source: '3psl_calculator',
        original: {
            height: heightValue,
            width: widthValue,
            unit: heightUnit
        },
        fullData: prepareFullCalculation(heightFt, widthFt, area)
    };
};

const addButton = document.createElement('button');
addButton.className = 'btn btn-success btn-lg';
addButton.id = 'addToQuotationBtn';
addButton.innerHTML = `<i class="fas fa-plus me-2"></i>Add to Quotation`;
addButton.style.fontWeight = '600';
addButton.style.padding = '12px 24px';

function showToast(message, type = 'info') {
    alert(`${type.toUpperCase()}: ${message}`);
}

addButton.addEventListener('click', function() {
    const calcData = getCalculationData();
    const fullData = calcData.fullData;

    const quoteFormData = new FormData();
    quoteFormData.append('action', 'add_item');
    quoteFormData.append('window_type', '3PSL');
    quoteFormData.append('description', '3PSL Window');
    quoteFormData.append('unit', 'Sft');
    quoteFormData.append('area', calcData.area);
    quoteFormData.append('rate', calcData.totalCost / calcData.area);
    quoteFormData.append('amount', calcData.totalCost);
    quoteFormData.append('quantity', calcData.quantity);
    quoteFormData.append('height', calcData.height);
    quoteFormData.append('width', calcData.width);
    quoteFormData.append('client_id', window.currentClientId);
    // Save original values and unit
    quoteFormData.append('height_original', calcData.original.height);
    quoteFormData.append('width_original', calcData.original.width);
    quoteFormData.append('unit_original', calcData.original.unit);

    fetch('quotation_handler.php', {
        method: 'POST',
        body: quoteFormData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Added to quotation!', 'success');
        } else {
            showToast('Error: ' + (data.error || 'Failed to add'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'danger');
    });

    const saveFormData = new FormData();
    saveFormData.append('action', 'save_calculation');
    saveFormData.append('client_id', window.currentClientId);
    saveFormData.append('company_id', window.currentCompanyId);
    saveFormData.append('window_type', '3PSL');
    saveFormData.append('height', fullData.dimensions.height);
    saveFormData.append('width', fullData.dimensions.width);
    saveFormData.append('quantity', fullData.dimensions.quantity);
    saveFormData.append('total_area', fullData.dimensions.area);
    saveFormData.append('frame_length', fullData.materials.frame.length);
    saveFormData.append('sash_length', fullData.materials.sash.length);
    saveFormData.append('net_sash_length', fullData.materials.netSash.length);
    saveFormData.append('beading_length', fullData.materials.beading.length);
    saveFormData.append('interlock_length', fullData.materials.interlock.length);
    saveFormData.append('steel_quantity', fullData.materials.steel.quantity);
    saveFormData.append('net_area', fullData.materials.net.area);
    saveFormData.append('net_rubber_quantity', fullData.materials.netRubber.quantity);
    saveFormData.append('burshi_length', fullData.materials.burshi.length);
    saveFormData.append('locks', fullData.hardware.locks.quantity);
    saveFormData.append('dummy', fullData.hardware.dummy.quantity);
    saveFormData.append('boofer', fullData.hardware.boofer.quantity);
    saveFormData.append('stopper', fullData.hardware.stopper.quantity);
    saveFormData.append('double_wheel', fullData.hardware.doubleWheel.quantity);
    saveFormData.append('net_wheel', fullData.hardware.netWheel.quantity);
    saveFormData.append('sada_screw', fullData.hardware.sadaScrew.quantity);
    saveFormData.append('fitting_screw', fullData.hardware.fittingScrew.quantity);
    saveFormData.append('self_screw', fullData.hardware.selfScrew.quantity);
    saveFormData.append('rawal_plug', fullData.hardware.rawalPlug.quantity);
    saveFormData.append('silicon_white', fullData.hardware.siliconWhite.quantity);
    saveFormData.append('hole_caps', fullData.hardware.holeCaps.quantity);
    saveFormData.append('water_caps', fullData.hardware.waterCaps.quantity);
    // Add individual cost values
    saveFormData.append('frame_cost', fullData.materials.frame.cost);
    saveFormData.append('sash_cost', fullData.materials.sash.cost);
    saveFormData.append('net_sash_cost', fullData.materials.netSash.cost);
    saveFormData.append('beading_cost', fullData.materials.beading.cost);
    saveFormData.append('interlock_cost', fullData.materials.interlock.cost);
    saveFormData.append('steel_cost', fullData.materials.steel.cost);
    saveFormData.append('net_cost', fullData.materials.net.cost);
    saveFormData.append('net_rubber_cost', fullData.materials.netRubber.cost);
    saveFormData.append('burshi_cost', fullData.materials.burshi.cost);
    saveFormData.append('locks_cost', fullData.hardware.locks.cost);
    saveFormData.append('dummy_cost', fullData.hardware.dummy.cost);
    saveFormData.append('boofer_cost', fullData.hardware.boofer.cost);
    saveFormData.append('stopper_cost', fullData.hardware.stopper.cost);
    saveFormData.append('double_wheel_cost', fullData.hardware.doubleWheel.cost);
    saveFormData.append('net_wheel_cost', fullData.hardware.netWheel.cost);
    saveFormData.append('sada_screw_cost', fullData.hardware.sadaScrew.cost);
    saveFormData.append('fitting_screw_cost', fullData.hardware.fittingScrew.cost);
    saveFormData.append('self_screw_cost', fullData.hardware.selfScrew.cost);
    saveFormData.append('rawal_plug_cost', fullData.hardware.rawalPlug.cost);
    saveFormData.append('silicon_white_cost', fullData.hardware.siliconWhite.cost);
    saveFormData.append('hole_caps_cost', fullData.hardware.holeCaps.cost);
    saveFormData.append('water_caps_cost', fullData.hardware.waterCaps.cost);
    saveFormData.append('material_cost', fullData.totals.materials);
    saveFormData.append('hardware_cost', fullData.totals.hardware);
    saveFormData.append('glass_cost', fullData.totals.glass);
    saveFormData.append('total_cost', fullData.totals.grandTotal);
    console.log('Saving data to save_window_calculation.php...', Object.fromEntries(saveFormData));
    console.log('Cost values being sent:', {
        frame_cost: fullData.materials.frame.cost,
        sash_cost: fullData.materials.sash.cost,
        net_sash_cost: fullData.materials.netSash.cost,
        beading_cost: fullData.materials.beading.cost,
        interlock_cost: fullData.materials.interlock.cost,
        steel_cost: fullData.materials.steel.cost,
        net_cost: fullData.materials.net.cost,
        net_rubber_cost: fullData.materials.netRubber.cost,
        burshi_cost: fullData.materials.burshi.cost,
        locks_cost: fullData.hardware.locks.cost,
        dummy_cost: fullData.hardware.dummy.cost,
        boofer_cost: fullData.hardware.boofer.cost,
        stopper_cost: fullData.hardware.stopper.cost,
        double_wheel_cost: fullData.hardware.doubleWheel.cost,
        net_wheel_cost: fullData.hardware.netWheel.cost,
        sada_screw_cost: fullData.hardware.sadaScrew.cost,
        fitting_screw_cost: fullData.hardware.fittingScrew.cost,
        self_screw_cost: fullData.hardware.selfScrew.cost,
        rawal_plug_cost: fullData.hardware.rawalPlug.cost,
        silicon_white_cost: fullData.hardware.siliconWhite.cost,
        hole_caps_cost: fullData.hardware.holeCaps.cost,
        water_caps_cost: fullData.hardware.waterCaps.cost
    });

    fetch('./Pages/save_window_calculation.php', {
        method: 'POST',
        body: saveFormData
    })
    .then(res => {
        console.log('Response status:', res.status);
        console.log('Response headers:', res.headers);
        
        // First check if the response is JSON
        const contentType = res.headers.get('content-type');
        console.log('Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            return res.text().then(text => {
                console.log('Non-JSON response:', text);
                throw new Error(`Invalid response: ${text}`);
            });
        }
        return res.json();
    })
    .then(data => {
    if (data.success) {
        alert("Saved successfully"); // Show success message
        window.location.reload();    // Refresh the page as-is (same URL & params)
    } else {
        throw new Error(data.error || 'Save failed');
    }
})
    .catch(error => {
        console.error("Error:", error.message);
        alert("Error: " + error.message);
    });
});

quoteBtnContainer.appendChild(addButton);
output.appendChild(quoteBtnContainer);
// generateWindowSketch(heightFt, heightUnit, widthFt, widthUnit);
output.scrollIntoView({ behavior: 'smooth' });
      
      // Generate 3PSL window sketch
      const svgWidth = 400;
      const svgHeight = 300;
      const windowWidth = svgWidth - 100;
      const windowHeight = svgHeight - 100;
      const centerPanelRatio = 0.5;  // 50% for center panel
      const sidePanelRatio = 0.25;   // 25% for each side panel

      const centerPanelWidth = windowWidth * centerPanelRatio;
      const sidePanelWidth = windowWidth * sidePanelRatio;

      // Calculate positions
      const frameX = 50;
      const frameY = 50;
      const leftPanelX = frameX;
      const centerPanelX = frameX + sidePanelWidth;
      const rightPanelX = frameX + sidePanelWidth + centerPanelWidth;

      // Arrow positions
      const arrowY = frameY + windowHeight - 20;
      const leftArrowX1 = frameX + 20;
      const leftArrowX2 = frameX + sidePanelWidth - 20;
      const rightArrowX1 = frameX + windowWidth - 20;
      const rightArrowX2 = frameX + sidePanelWidth + centerPanelWidth + 20;

      // Dimension line positions
      const dimLineY = frameY + windowHeight + 10;
      const leftDimTextX = frameX + sidePanelWidth/2;
      const centerDimTextX = frameX + sidePanelWidth + centerPanelWidth/2;
      const rightDimTextX = frameX + sidePanelWidth + centerPanelWidth + sidePanelWidth/2;

      const svg = `
      <svg width="${svgWidth}" height="${svgHeight}" style="border:1px solid #ddd; background: white; border-radius: 8px;" xmlns="http://www.w3.org/2000/svg">
        <!-- Main window frame -->
        <rect x="${frameX}" y="${frameY}" width="${windowWidth}" height="${windowHeight}" fill="none" stroke="#4b6cb7" stroke-width="3"/>
        
        <!-- Center panel (50% of width) -->
        <rect x="${centerPanelX}" y="${frameY}" width="${centerPanelWidth}" height="${windowHeight}" fill="#f0f0f0" stroke="#182848" stroke-width="2"/>
        <text x="${centerPanelX + centerPanelWidth/2}" y="${frameY + windowHeight/2}" font-size="14" fill="red" text-anchor="middle">FIX</text>
        
        <!-- Left movable panel (25% of width) -->
        <rect x="${leftPanelX}" y="${frameY}" width="${sidePanelWidth}" height="${windowHeight}" fill="#e6f3ff" stroke="#4b6cb7" stroke-width="2" class="window-part"/>
        <text x="${leftPanelX + sidePanelWidth/2}" y="${frameY + windowHeight/2}" font-size="14" fill="#182848" text-anchor="middle">← MOVE</text>
        
        <!-- Right movable panel (25% of width) -->
        <rect x="${rightPanelX}" y="${frameY}" width="${sidePanelWidth}" height="${windowHeight}" fill="#e6f3ff" stroke="#4b6cb7" stroke-width="2" class="window-part"/>
        <text x="${rightPanelX + sidePanelWidth/2}" y="${frameY + windowHeight/2}" font-size="14" fill="#182848" text-anchor="middle">MOVE →</text>
        
        <!-- Vertical dividers -->
        <line x1="${centerPanelX}" y1="${frameY}" x2="${centerPanelX}" y2="${frameY + windowHeight}" stroke="#182848" stroke-width="2" stroke-dasharray="5,5"/>
        <line x1="${rightPanelX}" y1="${frameY}" x2="${rightPanelX}" y2="${frameY + windowHeight}" stroke="#182848" stroke-width="2" stroke-dasharray="5,5"/>
        
        <!-- Dimensions -->
        <text x="25" y="${frameY + windowHeight/2}" font-size="12" fill="#4b6cb7" transform="rotate(-90 25,${frameY + windowHeight/2})">Height: ${heightFt.toFixed(2)} ft</text>
        <text x="${frameX + windowWidth/2}" y="${frameY - 10}" font-size="12" fill="#4b6cb7" text-anchor="middle">Width: ${widthFt.toFixed(2)} ft</text>

        <!-- Arrows indicating movement -->
        <path d="M ${leftArrowX1} ${arrowY} L ${leftArrowX2} ${arrowY} L ${leftArrowX2 - 10} ${arrowY - 5} M ${leftArrowX2} ${arrowY} L ${leftArrowX2 - 10} ${arrowY + 5}" fill="none" stroke="#182848" stroke-width="1.5"/>
        <path d="M ${rightArrowX1} ${arrowY} L ${rightArrowX2} ${arrowY} L ${rightArrowX2 + 10} ${arrowY - 5} M ${rightArrowX2} ${arrowY} L ${rightArrowX2 + 10} ${arrowY + 5}" fill="none" stroke="#182848" stroke-width="1.5"/>
        <text x="${leftPanelX + sidePanelWidth/2 - 15}" y="${arrowY - 10}" font-size="10" fill="#666">Slide</text>
        <text x="${rightPanelX + sidePanelWidth/2 - 15}" y="${arrowY - 10}" font-size="10" fill="#666">Slide</text>
        
        <!-- Panel width indicators -->
        <line x1="${leftPanelX}" y1="${dimLineY}" x2="${centerPanelX}" y2="${dimLineY}" stroke="#666" stroke-width="1"/>
        <line x1="${centerPanelX}" y1="${dimLineY}" x2="${rightPanelX}" y2="${dimLineY}" stroke="#666" stroke-width="1"/>
        <line x1="${rightPanelX}" y1="${dimLineY}" x2="${rightPanelX + sidePanelWidth}" y2="${dimLineY}" stroke="#666" stroke-width="1"/>
        <text x="${leftDimTextX}" y="${dimLineY + 15}" font-size="10" fill="#666">${Math.round(sidePanelRatio * 100)}%</text>
        <text x="${centerDimTextX}" y="${dimLineY + 15}" font-size="10" fill="#666">${Math.round(centerPanelRatio * 100)}%</text>
        <text x="${rightDimTextX}" y="${dimLineY + 15}" font-size="10" fill="#666">${Math.round(sidePanelRatio * 100)}%</text>
      </svg>
      `;

      document.getElementById("sketch").innerHTML = svg;
      document.getElementById("sketch").style.display = "block";

      // Scroll to results
      output.scrollIntoView({ behavior: 'smooth' });
    }
    
document.getElementById('calculateBtn').addEventListener('click', calculate);

['height', 'width', 'quantity'].forEach(id => {
    document.getElementById(id).addEventListener('keypress', function(e) {
        if (e.key === 'Enter') calculate();
    });
});

    </script>

<script src="quotation.js"></script>
</body>
</html>