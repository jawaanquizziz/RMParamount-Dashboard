<?php
// --- API-STYLE REQUEST HANDLER ---
// This PHP code block acts as a mini-API for our JavaScript frontend.

// Handle POST requests (saving, editing, deleting data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include the database connection file.
    // The $conn variable is now available from this file.
    require_once 'db.php';

    $response = ['status' => 'error', 'message' => 'Invalid action'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    $type = $input['type'] ?? null;
    $data = $input['data'] ?? null;
    $id = $input['id'] ?? null;

    $tableName = $type ? $type . '_leases' : null;
    if ($type === 'online') {
        $tableName = 'online_registrations';
    }


    if ($tableName) {
        switch ($action) {
            case 'save_record':
                if (empty($data)) {
                    $response['message'] = "No data provided to save.";
                    break;
                }
                $columns = array_keys($data);
                $values = array_values($data);
                
                if ($id) { // Update existing record
                    $setPairs = [];
                    foreach ($data as $key => $val) {
                        $setPairs[] = "`$key` = ?";
                    }
                    $sql = "UPDATE `$tableName` SET " . implode(', ', $setPairs) . " WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    
                    // The types for bind_param (e.g., 'sssi' for string, string, string, integer)
                    $types = str_repeat('s', count($values)) . 'i';
                    
                    // The values to bind, including the id at the end
                    $bindValues = array_merge($values, [$id]);
                    
                    // Bind the parameters using the more robust call_user_func_array
                    $stmt->bind_param($types, ...$bindValues);

                } else { // Insert new record
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $sql = "INSERT INTO `$tableName` (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    $types = str_repeat('s', count($values));
                    $stmt->bind_param($types, ...$values);
                }

                if ($stmt->execute()) {
                    $response = ['status' => 'success'];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
                break;

            case 'delete_record':
                $sql = "DELETE FROM `$tableName` WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success'];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
                break;

            case 'bulk_add':
                 $records = $data;
                 $firstRecord = $records[0] ?? [];
                 $columns = array_keys($firstRecord);
                 $sql = "INSERT INTO `$tableName` (" . implode(', ', $columns) . ") VALUES ";
                 
                 $placeholders = [];
                 $values = [];
                 $types = '';

                 $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

                 foreach($records as $record) {
                     $placeholders[] = $rowPlaceholder;
                     foreach($record as $val) {
                         // Handle potential null values from date reformatting
                         $values[] = $val;
                     }
                     $types .= str_repeat('s', count($columns));
                 }
                 
                 $sql .= implode(', ', $placeholders);
                 $stmt = $conn->prepare($sql);
                 $stmt->bind_param($types, ...$values);

                 if ($stmt->execute()) {
                    $response = ['status' => 'success', 'count' => count($records)];
                 } else {
                    $response['message'] = $stmt->error;
                 }
                 $stmt->close();
                 break;
        }
    }

    $conn->close();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Stop script execution to prevent rendering HTML
}

// Handle GET requests (fetching data)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_records') {
    // Include the database connection file.
    require_once 'db.php';
    
    $type = $_GET['type'] ?? null;
    $tableName = $type ? $type . '_leases' : null;
    if ($type === 'online') {
        $tableName = 'online_registrations';
    }


    if ($tableName) {
        $result = $conn->query("SELECT * FROM `$tableName`");
        $data = [];
        if($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        header('Content-Type: application/json');
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }

    $conn->close();
    exit(); // Stop script execution
}

// If it's not an API request, the script continues and renders the HTML page below.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RM Paramount's Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .tab-active {
            border-color: #3b82f6;
            color: #3b82f6;
            background-color: #eff6ff;
        }
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        .calculator-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

    <div id="app" class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 flex-grow w-full">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">RM Paramount's Dashboard</h1>
            <p class="text-slate-500 mt-1">A simple tool for property calculations and record keeping.</p>
        </header>

        <!-- Tabs -->
        <div class="mb-6 border-b border-slate-200">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button data-tab="calculator" class="tab-button tab-active group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                    Stamp Duty Calculator
                </button>
                 <button data-tab="utility" class="tab-button text-slate-500 hover:text-slate-700 hover:border-slate-300 group inline-flex items-center py-4 px-1 border-b-2 border-transparent font-medium text-sm whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Utility & Area Calculators
                </button>
                <button data-tab="sbi" class="tab-button text-slate-500 hover:text-slate-700 hover:border-slate-300 group inline-flex items-center py-4 px-1 border-b-2 border-transparent font-medium text-sm whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    SBI Leases
                </button>
                <button data-tab="private" class="tab-button text-slate-500 hover:text-slate-700 hover:border-slate-300 group inline-flex items-center py-4 px-1 border-b-2 border-transparent font-medium text-sm whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Private Leases
                </button>
                <button data-tab="online" class="tab-button text-slate-500 hover:text-slate-700 hover:border-slate-300 group inline-flex items-center py-4 px-1 border-b-2 border-transparent font-medium text-sm whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    Online Registrations
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <main>
            <!-- Stamp Duty Calculator -->
            <div id="calculator-content" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
                        <h2 class="text-xl font-semibold mb-4">Leave & License Fee Calculator</h2>
                        <form id="calculator-form" class="space-y-4">
                            <div>
                                <label for="monthlyRent" class="block text-sm font-medium text-slate-700">Monthly Rent (₹)</label>
                                <input type="number" id="monthlyRent" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 33000" required>
                            </div>
                            <div>
                                <label for="refundableDeposit" class="block text-sm font-medium text-slate-700">Refundable Deposit (₹)</label>
                                <input type="number" id="refundableDeposit" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 165000" required>
                            </div>
                            <div>
                                <label for="nonRefundableDeposit" class="block text-sm font-medium text-slate-700">Non-Refundable Deposit (₹)</label>
                                <input type="number" id="nonRefundableDeposit" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 0" value="0" required>
                            </div>
                            <div>
                                <label for="period" class="block text-sm font-medium text-slate-700">Agreement Period (Months)</label>
                                <input type="number" id="period" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 36" required>
                            </div>
                        </form>
                    </div>
                    <div class="bg-blue-600 text-white p-6 rounded-lg shadow-sm flex flex-col">
                        <h2 class="text-xl font-semibold mb-4">Calculation Results</h2>
                        <div id="results" class="flex-grow space-y-4">
                            <div class="flex justify-between items-baseline">
                                <p class="text-blue-200">Stamp Duty (0.25%):</p>
                                <p id="stampDutyResult" class="text-3xl font-bold">₹0.00</p>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <p class="text-blue-200">Rounded Off Stamp Duty:</p>
                                <p id="roundedStampDutyResult" class="text-lg font-semibold">₹0</p>
                            </div>
                             <div class="flex justify-between items-baseline">
                                <p class="text-blue-200">Registration Fee:</p>
                                <p id="registrationFeeResult" class="text-lg font-semibold">₹1,000</p>
                            </div>
                            <div class="border-t border-blue-500 my-4"></div>
                            <div class="flex justify-between items-baseline">
                                <p class="text-blue-200 font-bold">Total Payable:</p>
                                <p id="totalPayableResult" class="text-3xl font-bold">₹1,000</p>
                            </div>
                             <div class="mt-auto pt-4 text-xs text-blue-300">
                                <p class="font-semibold">Calculation Breakdown:</p>
                                <p id="breakdown-taxable" class="font-mono">Taxable Amount: ₹0</p>
                                <p class="mt-2 opacity-80">*This is an estimate based on standard Maharashtra Leave & License agreement rules. Registration fee is fixed at ₹1000 for properties in municipal corporation areas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Utility Calculators -->
            <div id="utility-content" class="tab-content hidden">
                <div class="calculator-grid">
                    <!-- MSED Calculator -->
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
                        <h2 class="text-xl font-semibold mb-4">MSED Electricity Bill Calculator</h2>
                        <div class="space-y-4">
                             <div>
                                <label for="msedUnits" class="block text-sm font-medium text-slate-700">Units Consumed</label>
                                <input type="number" id="msedUnits" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 211">
                            </div>
                            <div id="msed-results" class="space-y-2 text-sm pt-2">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                    </div>

                    <!-- MGL Calculator -->
                     <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
                        <h2 class="text-xl font-semibold mb-4">MGL Gas Bill Calculator</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="mglCurrent" class="block text-sm font-medium text-slate-700">Current Reading</label>
                                    <input type="number" id="mglCurrent" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 373">
                                </div>
                                <div>
                                    <label for="mglPrevious" class="block text-sm font-medium text-slate-700">Previous Reading</label>
                                    <input type="number" id="mglPrevious" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="e.g., 299">
                                </div>
                            </div>
                            <div id="mgl-results" class="space-y-2 text-sm pt-2">
                                <!-- Results will be injected here -->
                            </div>
                        </div>
                    </div>

                    <!-- Floor Plan Calculator -->
                     <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200 col-span-1 md:col-span-2">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">Floor Plan Area Calculator</h2>
                            <button id="add-room-btn" class="flex items-center px-3 py-1.5 bg-slate-100 text-slate-700 text-sm rounded-md hover:bg-slate-200">
                               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                Add Room
                            </button>
                        </div>
                        <div id="fp-rooms-container" class="space-y-2">
                            <!-- Room rows will be added here -->
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-200 flex justify-end items-center">
                            <span class="text-slate-600 font-medium mr-4">Total Area:</span>
                            <span id="fp-total-area" class="text-2xl font-bold text-slate-800">0.00 sq. ft.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables (hidden by default) -->
            <div id="sbi-content" class="tab-content hidden"></div>
            <div id="private-content" class="tab-content hidden"></div>
            <div id="online-content" class="tab-content hidden"></div>
        </main>
    </div>

    <!-- Generic Modal -->
    <div id="form-modal" class="modal-backdrop">
        <div class="modal-content bg-white w-11/12 max-w-3xl rounded-lg shadow-xl p-6 relative">
            <button id="close-modal-btn" class="absolute top-4 right-4 text-slate-500 hover:text-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <h2 id="modal-title" class="text-xl font-semibold mb-4">Add/Edit Record</h2>
            <form id="modal-form" class="grid grid-cols-1 md:grid-cols-2 gap-4"></form>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="modal-cancel-btn" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">Cancel</button>
                <button id="modal-save-btn" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Save Record</button>
            </div>
        </div>
    </div>
    
    <!-- Bulk Add Modal -->
    <div id="bulk-add-modal" class="modal-backdrop">
        <div class="modal-content bg-white w-11/12 max-w-3xl rounded-lg shadow-xl p-6 relative">
            <button id="close-bulk-modal-btn" class="absolute top-4 right-4 text-slate-500 hover:text-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <h2 id="bulk-modal-title" class="text-xl font-semibold mb-4">Bulk Import Records from CSV</h2>
            <p class="text-sm text-slate-600 mb-2">Select a CSV file to upload. To prepare your data, export your Excel sheet as a CSV file. **The CSV file should not contain a header row.**</p>
            <p class="text-sm text-slate-600 mb-4">Ensure the columns in your CSV are in this exact order:</p>
            <div id="bulk-column-list" class="text-xs bg-slate-100 p-3 rounded-md mb-4 font-mono text-slate-700 overflow-x-auto">
                <!-- Column order list will be injected here -->
            </div>
            <input type="file" id="bulk-file-input" accept=".csv" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="bulk-cancel-btn" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200">Cancel</button>
                <button id="bulk-save-btn" type="button" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Import Records</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-5 right-5 bg-slate-900 text-white px-5 py-3 rounded-lg shadow-lg opacity-0 translate-y-10 transition-all duration-300">
        <p id="toast-message"></p>
    </div>

    <footer class="text-center p-4 text-sm text-slate-500">
        © 2025 RM Paramount Real Estate and Developers
    </footer>

    <script>
    // --- JAVASCRIPT APPLICATION LOGIC ---
    document.addEventListener('DOMContentLoaded', () => {
        // --- CONFIG & GLOBAL VARS ---
        const leaseFields = [
            { id: 'refNo', label: 'Ref No.' },
            { id: 'propertyName', label: 'Property Name', fullWidth: true },
            { id: 'propertyAddress', label: 'Property Address', fullWidth: true },
            { id: 'ownerName', label: 'Owner Name' },
            { id: 'ownerContact', label: 'Owner Contact' },
            { id: 'tenantName', label: 'Tenant Name' },
            { id: 'scale', label: 'Scale' },
            { id: 'tenantContact', label: 'Tenant Contact' },
            { id: 'months', label: 'Months', type: 'number' },
            { id: 'from_date', label: 'From Date', type: 'date' },
            { id: 'to_date', label: 'To Date', type: 'date' },
            { id: 'rent', label: 'Rent (₹)', type: 'number' },
            { id: 'deposit', label: 'Deposit (₹)', type: 'number' },
            { id: 'typeOfLease', label: 'Type of Lease', placeholder: 'e.g., NEW/RENEWAL' },
            { id: 'registrationStatus', label: 'Registration Status', placeholder: 'e.g., COMPLETED/PENDING' },
            { id: 'location', label: 'Location' },
            { id: 'registrationCompletionDate', label: 'Reg. Completion Date', type: 'date' },
            { id: 'scanStatus', label: 'Scan Status', placeholder: 'e.g., COMPLETED/PENDING' },
        ];

        const onlineRegFields = [
            { id: 'propertyName', label: 'Property Name', fullWidth: true },
            { id: 'stampDutyRegOwner', label: 'Stamp Duty/Reg (Owner)', type: 'number' },
            { id: 'stampDutyRegTenant', label: 'Stamp Duty/Reg (Tenant)', type: 'number' },
            { id: 'serviceChargeOwner', label: 'Service Charge (Owner)', type: 'number' },
            { id: 'serviceChargeTenant', label: 'Service Charge (Tenant)', type: 'number' },
            { id: 'visitChargesOwner', label: 'Visit Charges (Owner)', type: 'number' },
            { id: 'visitChargesTenant', label: 'Visit Charges (Tenant)', type: 'number' },
            { id: 'docChargesOwner', label: 'Doc. Charges (Owner)', type: 'number' },
            { id: 'docChargesTenant', label: 'Doc. Charges (Tenant)', type: 'number' },
        ];
        
        const tableConfigs = {
            sbi: { title: "SBI Lease Records", fields: leaseFields },
            private: { title: "Private Lease Records", fields: leaseFields },
            online: { title: "Online Registration Records", fields: onlineRegFields }
        };

        let currentModalContext = {};
        
        // --- INITIALIZATION ---
        initializeTabs();
        setupEventListeners();
        calculateStampDuty();
        calculateMSED();
        calculateMGL();
        addRoomRow();
        
        // --- EVENT LISTENERS ---
        function setupEventListeners() {
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', () => switchTab(button.dataset.tab));
            });
            document.getElementById('calculator-form').addEventListener('input', calculateStampDuty);
            document.getElementById('msedUnits').addEventListener('input', calculateMSED);
            document.getElementById('mglCurrent').addEventListener('input', calculateMGL);
            document.getElementById('mglPrevious').addEventListener('input', calculateMGL);
            document.getElementById('add-room-btn').addEventListener('click', addRoomRow);
            document.getElementById('fp-rooms-container').addEventListener('input', calculateFloorPlanArea);
            document.getElementById('close-modal-btn').addEventListener('click', closeModal);
            document.getElementById('modal-cancel-btn').addEventListener('click', closeModal);
            document.getElementById('close-bulk-modal-btn').addEventListener('click', closeBulkModal);
            document.getElementById('bulk-cancel-btn').addEventListener('click', closeBulkModal);
            document.getElementById('bulk-save-btn').addEventListener('click', handleBulkSave);
        }

        // --- CALCULATOR LOGIC (No changes from previous version) ---
        function calculateStampDuty() {
            const monthlyRent = parseFloat(document.getElementById('monthlyRent').value) || 0;
            const refundableDeposit = parseFloat(document.getElementById('refundableDeposit').value) || 0;
            const nonRefundableDeposit = parseFloat(document.getElementById('nonRefundableDeposit').value) || 0;
            const period = parseFloat(document.getElementById('period').value) || 0;
            if(period === 0) return;
            const totalRent = monthlyRent * period;
            const interestOnDeposit = refundableDeposit * 0.10 * (period / 12);
            const taxableAmount = totalRent + interestOnDeposit + nonRefundableDeposit;
            const stampDuty = taxableAmount * 0.0025;
            const roundedStampDuty = Math.ceil(stampDuty / 100) * 100;
            const registrationFee = 1000;
            const totalPayable = roundedStampDuty + registrationFee;
            document.getElementById('stampDutyResult').textContent = `₹${stampDuty.toFixed(2)}`;
            document.getElementById('roundedStampDutyResult').textContent = `₹${roundedStampDuty.toLocaleString('en-IN')}`;
            document.getElementById('registrationFeeResult').textContent = `₹${registrationFee.toLocaleString('en-IN')}`;
            document.getElementById('totalPayableResult').textContent = `₹${totalPayable.toLocaleString('en-IN')}`;
            document.getElementById('breakdown-taxable').textContent = `Taxable Amount: ₹${taxableAmount.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
        }
        function calculateMSED() {
            const units = parseFloat(document.getElementById('msedUnits').value) || 0;
            let remainingUnits = units;
            const tariffs = [{ limit: 100, rate: 4.71, fac: 0.40 }, { limit: 200, rate: 10.29, fac: 0.70 }, { limit: 200, rate: 14.55, fac: 0.90 }, { limit: 500, rate: 16.64, fac: 1.00 }, { limit: Infinity, rate: 16.64, fac: 1.00 }];
            let energyCharge = 0, facCharge = 0;
            for (const tariff of tariffs) {
                if (remainingUnits > 0) {
                    const unitsInSlab = Math.min(remainingUnits, tariff.limit);
                    energyCharge += unitsInSlab * tariff.rate;
                    facCharge += unitsInSlab * tariff.fac;
                    remainingUnits -= unitsInSlab;
                }
            }
            const fixedCharge = 138, wheelingCharge = units * 1.17, preDutyTotal = energyCharge + facCharge + fixedCharge + wheelingCharge, electricityDuty = preDutyTotal * 0.16, totalBill = preDutyTotal + electricityDuty;
            document.getElementById('msed-results').innerHTML = `<div class="flex justify-between border-t pt-2 mt-2"><span>Energy Charge:</span> <span class="font-medium">₹${energyCharge.toFixed(2)}</span></div><div class="flex justify-between"><span>Wheeling Charge:</span> <span class="font-medium">₹${wheelingCharge.toFixed(2)}</span></div><div class="flex justify-between"><span>FAC Charge:</span> <span class="font-medium">₹${facCharge.toFixed(2)}</span></div><div class="flex justify-between"><span>Fixed Charge:</span> <span class="font-medium">₹${fixedCharge.toFixed(2)}</span></div><div class="flex justify-between"><span>Electricity Duty (16%):</span> <span class="font-medium">₹${electricityDuty.toFixed(2)}</span></div><div class="flex justify-between text-lg font-bold border-t pt-2 mt-2"><span>Total Bill:</span> <span>₹${totalBill.toFixed(2)}</span></div>`;
        }
        function calculateMGL() {
            const current = parseFloat(document.getElementById('mglCurrent').value) || 0;
            const previous = parseFloat(document.getElementById('mglPrevious').value) || 0;
            const units = current > previous ? current - previous : 0;
            const gasRate = 46.60, otherCharges = 10.0, gasConsumptionCharge = units * gasRate, mvat = gasConsumptionCharge * 0.03, totalA = gasConsumptionCharge + mvat, gst = otherCharges * 0.18, totalB = otherCharges + gst, totalPayable = totalA + totalB, roundedTotal = Math.round(totalPayable);
            document.getElementById('mgl-results').innerHTML = `<div class="flex justify-between border-t pt-2 mt-2"><span>Units (SCM):</span> <span class="font-medium">${units.toFixed(2)}</span></div><div class="flex justify-between"><span>Gas Consumption:</span> <span class="font-medium">₹${gasConsumptionCharge.toFixed(2)}</span></div><div class="flex justify-between"><span>MVAT (3%):</span> <span class="font-medium">₹${mvat.toFixed(2)}</span></div><div class="flex justify-between"><span>Other + GST:</span> <span class="font-medium">₹${totalB.toFixed(2)}</span></div><div class="flex justify-between text-lg font-bold border-t pt-2 mt-2"><span>Total Bill (Rounded):</span> <span>₹${roundedTotal.toLocaleString('en-IN')}</span></div>`;
        }
        function addRoomRow() {
            const container = document.getElementById('fp-rooms-container');
            const newRow = document.createElement('div');
            newRow.className = 'grid grid-cols-8 gap-2 items-center room-row';
            newRow.innerHTML = `<input type="text" placeholder="Room Name" class="col-span-3 mt-1 block w-full px-2 py-1.5 text-sm bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><input type="number" placeholder="Length (ft)" class="fp-length col-span-2 mt-1 block w-full px-2 py-1.5 text-sm bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><input type="number" placeholder="Width (ft)" class="fp-width col-span-2 mt-1 block w-full px-2 py-1.5 text-sm bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><button class="remove-room-btn text-slate-400 hover:text-red-500 col-span-1 justify-self-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>`;
            container.appendChild(newRow);
            newRow.querySelector('.remove-room-btn').addEventListener('click', () => { newRow.remove(); calculateFloorPlanArea(); });
        }
        function calculateFloorPlanArea() {
            let totalArea = 0;
            document.querySelectorAll('.room-row').forEach(row => {
                const length = parseFloat(row.querySelector('.fp-length').value) || 0;
                const width = parseFloat(row.querySelector('.fp-width').value) || 0;
                totalArea += length * width;
            });
            document.getElementById('fp-total-area').textContent = `${totalArea.toFixed(2)} sq. ft.`;
        }

        // --- UI & TABS ---
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(button => button.classList.remove('tab-active'));
            document.getElementById(`${tabId}-content`).classList.remove('hidden');
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('tab-active');
        }

        function initializeTabs() {
            Object.keys(tableConfigs).forEach(key => {
                const config = tableConfigs[key];
                const container = document.getElementById(`${key}-content`);
                if (container) {
                    container.innerHTML = createTableHTML(config.title, key);
                    document.getElementById(`add-${key}-btn`).addEventListener('click', () => openModal(key));
                    if (key === 'sbi' || key === 'private') {
                        document.getElementById(`bulk-add-${key}-btn`).addEventListener('click', () => openBulkModal(key));
                    }
                    fetchAndRenderRecords(key);
                }
            });
        }
        
        // --- DYNAMIC HTML ---
        function createTableHTML(title, key) {
            const config = tableConfigs[key];
            const headers = config.fields.map(f => `<th class="p-3 text-sm font-semibold tracking-wide text-left">${f.label}</th>`).join('');
            const actionHeader = key !== 'sbi' ? '<th class="p-3 text-sm font-semibold tracking-wide text-left">Actions</th>' : '';
            const bulkAddButton = (key === 'sbi' || key === 'private') ? `<button id="bulk-add-${key}-btn" class="flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 ml-3"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>Bulk Add</button>` : '';
            const colspan = config.fields.length + (key !== 'sbi' ? 1 : 0);
            return `<div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200"><div class="flex justify-between items-center mb-4"><h2 class="text-xl font-semibold">${title}</h2><div class="flex items-center"><button id="add-${key}-btn" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>Add New</button>${bulkAddButton}</div></div><div class="overflow-auto rounded-lg shadow"><table class="w-full border-collapse bg-white"><thead class="bg-slate-100 border-b-2 border-slate-200"><tr>${headers}${actionHeader}</tr></thead><tbody id="${key}-table-body" class="divide-y divide-slate-100"><tr><td colspan="${colspan}" class="p-4 text-center text-slate-500">Loading records...</td></tr></tbody></table></div></div>`;
        }

        function createFormFields(fields, data = {}) {
            return fields.map(field => `<div class="${(field.fullWidth || (field.type === 'text' && field.id.includes('Address'))) ? 'md:col-span-2' : ''}"><label for="${field.id}" class="block text-sm font-medium text-slate-700">${field.label}</label><input type="${field.type || 'text'}" id="${field.id}" name="${field.id}" value="${data[field.id] || ''}" placeholder="${field.placeholder || ''}" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required></div>`).join('');
        }

        // --- MYSQL CRUD OPERATIONS (Replaces Firestore logic) ---
        async function fetchAndRenderRecords(key) {
            try {
                const response = await fetch(`index.php?action=get_records&type=${key}`);
                const records = await response.json();
                
                const config = tableConfigs[key];
                const tableBody = document.getElementById(`${key}-table-body`);
                if (!tableBody) return;

                const colspan = config.fields.length + (key !== 'sbi' ? 1 : 0);
                if (!records || records.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="p-4 text-center text-slate-500">No records found. Click 'Add New' to start.</td></tr>`;
                    return;
                }
                
                tableBody.innerHTML = '';
                records.forEach(record => {
                    const row = document.createElement('tr');
                    row.className = "hover:bg-slate-50";
                    let cells = '';
                    config.fields.forEach(field => {
                        cells += `<td class="p-3 text-sm text-slate-700 whitespace-nowrap">${record[field.id] || 'N/A'}</td>`;
                    });

                    if (key !== 'sbi') {
                        cells += `<td class="p-3 text-sm text-slate-700 whitespace-nowrap"><button data-key="${key}" data-id="${record.id}" class="edit-btn text-blue-500 hover:text-blue-700 mr-4">Edit</button><button data-key="${key}" data-id="${record.id}" class="delete-btn text-red-500 hover:text-red-700">Delete</button></td>`;
                    }
                    
                    row.innerHTML = cells;
                    tableBody.appendChild(row);
                });

                document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', handleEditClick));
                document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', handleDeleteClick));

            } catch (error) {
                console.error(`Error fetching ${key} records:`, error);
                const tableBody = document.getElementById(`${key}-table-body`);
                if (tableBody) {
                    const colspan = tableConfigs[key].fields.length + (key !== 'sbi' ? 1 : 0);
                    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="p-4 text-center text-red-500">Error loading data. Check database connection and console.</td></tr>`;
                }
            }
        }
        
        async function saveData(key, id, data) {
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_record', type: key, id: id, data: data })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    showToast(`Record ${id ? 'updated' : 'added'} successfully!`);
                    fetchAndRenderRecords(key);
                    closeModal();
                } else {
                    throw new Error(result.message || 'Failed to save record.');
                }
            } catch (error) {
                console.error("Error saving data:", error);
                showToast(`Error: ${error.message}`, true);
            }
        }

        async function deleteData(key, id) {
            if (!confirm('Are you sure you want to delete this record?')) return;
            try {
                 const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_record', type: key, id: id })
                });
                const result = await response.json();
                 if (result.status === 'success') {
                    showToast('Record deleted successfully.');
                    fetchAndRenderRecords(key);
                } else {
                    throw new Error(result.message || 'Failed to delete record.');
                }
            } catch (error) {
                console.error("Error deleting data:", error);
                showToast(`Error: ${error.message}`, true);
            }
        }
        
        // --- MODAL & FORM HANDLING ---
        function openModal(key, id = null, data = {}) {
            currentModalContext = { key, id };
            const config = tableConfigs[key];
            document.getElementById('modal-title').textContent = `${id ? 'Edit' : 'Add'} ${config.title.slice(0, -1)}`;
            document.getElementById('modal-form').innerHTML = createFormFields(config.fields, data);
            document.getElementById('form-modal').style.display = 'flex';
            document.getElementById('modal-save-btn').onclick = handleFormSubmit;
        }

        function closeModal() {
            document.getElementById('form-modal').style.display = 'none';
            currentModalContext = {};
        }
        
        function openBulkModal(key) {
            currentModalContext = { key };
            const config = tableConfigs[key];
            const columnList = document.getElementById('bulk-column-list');
            columnList.innerHTML = `<ol class="list-decimal list-inside">${config.fields.map(f => `<li>${f.label}</li>`).join('')}</ol>`;
            document.getElementById('bulk-add-modal').style.display = 'flex';
        }

        function closeBulkModal() {
            document.getElementById('bulk-add-modal').style.display = 'none';
            const fileInput = document.getElementById('bulk-file-input');
            if(fileInput) fileInput.value = '';
            currentModalContext = {};
        }

        // --- UTILITY ---
        function reformatDate(dateStr) {
            if (!dateStr || typeof dateStr !== 'string' || dateStr.trim() === '') return null;
            const parts = dateStr.split(/[-/]/);
            if (parts.length !== 3) return null;
            
            let day, month, year;
            
            // Handle DD-MM-YYYY or DD/MM/YYYY
            if(parts[2].length === 4) {
                [day, month, year] = parts;
            } 
            // Handle YYYY-MM-DD or YYYY/MM/DD
            else if (parts[0].length === 4) {
                [year, month, day] = parts;
            } else {
                 return null; // or handle other formats if necessary
            }

            if (isNaN(parseInt(day)) || isNaN(parseInt(month)) || isNaN(parseInt(year))) {
                return null;
            }
            // Pad day and month with a leading zero if they are single digit
            day = day.padStart(2, '0');
            month = month.padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        async function handleBulkSave() {
            const { key } = currentModalContext;
            if (!key) return;
            const config = tableConfigs[key];
            const fileInput = document.getElementById('bulk-file-input');
            const file = fileInput.files[0];

            if (!file) {
                showToast('Please select a CSV file to upload.', true);
                return;
            }

            const reader = new FileReader();
            reader.onload = async function(event) {
                const csvData = event.target.result;
                // **FIX**: Normalize line endings to prevent parsing errors
                const rows = csvData.replace(/\r/g, '').split('\n').filter(row => row.trim() !== '');

                const records = [];
                const expectedColumns = config.fields.length;

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    // Regex to handle columns that might be quoted (and contain commas)
                    const columns = row.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g) || [];
                    
                    // Take only the expected number of columns, ignoring any extra empty ones at the end
                    const relevantColumns = columns.slice(0, expectedColumns);
                    const cleanedColumns = relevantColumns.map(col => col.replace(/^"|"$/g, '').trim());

                    if (cleanedColumns.length < expectedColumns) {
                        showToast(`Error on row ${i + 1}: Found only ${cleanedColumns.length} columns, but expected ${expectedColumns}. Please check for missing commas.`, true);
                        fileInput.value = ''; 
                        return;
                    }

                    const record = {};
                    config.fields.forEach((field, index) => {
                        record[field.id] = cleanedColumns[index];
                    });
                    
                    // **FIX**: Reformat all potential date fields before sending
                    if (record.from_date) record.from_date = reformatDate(record.from_date);
                    if (record.to_date) record.to_date = reformatDate(record.to_date);
                    if (record.registrationCompletionDate) record.registrationCompletionDate = reformatDate(record.registrationCompletionDate);

                    records.push(record);
                }
                
                if (records.length > 0) {
                    showToast(`Importing ${records.length} records...`);
                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'bulk_add', type: key, data: records })
                        });
                        const result = await response.json();
                        if (result.status === 'success') {
                            showToast(`Successfully imported ${result.count} records!`, false);
                            fetchAndRenderRecords(key);
                            closeBulkModal();
                        } else {
                            throw new Error(result.message || 'Failed to import records.');
                        }
                    } catch (error) {
                        console.error("Bulk save error:", error);
                        showToast(`Error: ${error.message}`, true);
                    } finally {
                        fileInput.value = '';
                    }
                } else {
                    showToast('No valid records found to import.', true);
                    fileInput.value = '';
                }
            };
            reader.readAsText(file);
        }

        function handleFormSubmit() {
            const { key, id } = currentModalContext;
            const form = document.getElementById('modal-form');
            if (form.checkValidity()) {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                saveData(key, id, data);
            } else {
                form.reportValidity();
                showToast('Please fill out all required fields.', true);
            }
        }

        async function handleEditClick(event) {
            const { key, id } = event.target.dataset;
            const response = await fetch(`index.php?action=get_records&type=${key}`);
            const records = await response.json();
            const recordToEdit = records.find(r => r.id == id);
            if(recordToEdit) {
                 openModal(key, id, recordToEdit);
            }
        }

        function handleDeleteClick(event) {
            const { key, id } = event.target.dataset;
            deleteData(key, id);
        }

        // --- UTILITIES ---
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            toastMessage.textContent = message;
            toast.className = toast.className.replace(/bg-slate-900|bg-red-600/, isError ? 'bg-red-600' : 'bg-slate-900');
            toast.classList.remove('opacity-0', 'translate-y-10');
            toast.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-10');
            }, 4000);
        }
    });
    </script>
</body>
</html>

