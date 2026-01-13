// Mock Contracts Data (Fix for PHP dependency)
// IMPORTANT: This object contains nested JSON strings for 'risk_factors' and 'recommendations' 
// to simulate complex data from an an AI analysis service.
const MOCK_CONTRACTS_DATA = [
    { contract_name: 'Client Services Agreement', case_id: 'C-001', risk_level: 'High', risk_score: 85, upload_date: '2023-05-20', file_path: 'contract_c001.pdf', analysis_summary: 'Significant exposure in termination and liability clauses.', risk_factors: '[{"category": "legal_risk", "factor": "Ambiguous termination clause", "weight": 40}, {"category": "financial_risk", "factor": "High liability cap", "weight": 45}]', recommendations: '["Redraft Clause 7.1 to clearly define termination conditions.", "Lower the liability cap to 50% of annual service fee."]' },
    { contract_name: 'Vendor Supply Contract', case_id: 'C-002', risk_level: 'Medium', risk_score: 55, upload_date: '2023-06-25', file_path: 'contract_c002.pdf', analysis_summary: 'Standard contract with minor intellectual property concerns regarding derived work.', risk_factors: '[{"category": "ip_risk", "factor": "Ownership unclear on derived work", "weight": 55}]', recommendations: '["Add a clause clarifying IP ownership for all derivative materials."]' },
    { contract_name: 'Employment NDA', case_id: 'E-010', risk_level: 'Low', risk_score: 20, upload_date: '2023-07-10', file_path: 'contract_e010.pdf', analysis_summary: 'Standard, low-risk non-disclosure agreement.', risk_factors: '[]', recommendations: '["No major changes needed. Standardize for all new hires."]' },
    { contract_name: 'Joint Venture Agreement', case_id: 'C-003', risk_level: 'High', risk_score: 92, upload_date: '2023-08-01', file_path: 'contract_c003.pdf', analysis_summary: 'Extremely high commitment with open-ended duration and no defined exit strategy.', risk_factors: '[{"category": "strategic_risk", "factor": "Open-ended duration", "weight": 50}, {"category": "financial_risk", "factor": "Uncapped capital calls", "weight": 42}]', recommendations: '["Define a hard stop date or periodic review for termination.", "Cap the capital commitment amount."]' },
    { contract_name: 'Lease Agreement', case_id: 'F-005', risk_level: 'Medium', risk_score: 40, upload_date: '2023-09-15', file_path: 'contract_f005.pdf', analysis_summary: 'Standard commercial lease with a few negotiable points in the maintenance section.', risk_factors: '[]', recommendations: '["Ensure maintenance responsibilities are fully covered by tenant."]' }
];

document.addEventListener('DOMContentLoaded', function () {
    const pinInputs = document.querySelectorAll('#loginScreen .pin-digit');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const loginScreen = document.getElementById('loginScreen');
    const dashboard = document.getElementById('dashboard');
    const logoutBtn = document.getElementById('backDashboardBtn');

    // Correct PIN (in a real application, this would be stored securely)
    const correctPIN = '1234';

    // Focus on first PIN input
    pinInputs[0]?.focus();

    // Move to next input when a digit is entered
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            // Only allow numbers and max 1 digit (ADDED VALIDATION)
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 1);

            if (this.value.length === 1 && index < pinInputs.length - 1) {
                pinInputs[index + 1].focus();
            }

            // Hide error message on input change
            errorMessage.style.display = 'none';
        });

        // Allow backspace to move to previous input
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                pinInputs[index - 1].focus();
            }
        });
    });

    // Login functionality
    loginBtn?.addEventListener('click', function () {
        const enteredPIN = Array.from(pinInputs).map(input => input.value).join('');

        if (enteredPIN === correctPIN) {
            // Successful login
            loginScreen.style.display = 'none';
            dashboard.style.display = 'block';

            // Show Loading Animation Overlay
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '1';
                // Restart animation
                const iframe = loader.querySelector('iframe');
                if (iframe) iframe.src = iframe.src;

                setTimeout(() => {
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 500);
                }, 3000);
            }
            // Activate default tab (Employees) to ensure correct visibility
            const defaultTab = document.querySelector('.nav-tab[data-target="employees"]');
            if (defaultTab) defaultTab.click();

            // Initialize dashboard data
            initializeDashboard();
        } else {
            // Failed login
            errorMessage.style.display = 'block';
            pinInputs.forEach(input => {
                input.value = '';
            });
            pinInputs[0]?.focus();
        }
    });

    // Logout functionality
    logoutBtn?.addEventListener('click', function () {
        dashboard.style.display = 'none';
        loginScreen.style.display = 'flex';

        // Clear PIN inputs
        pinInputs.forEach(input => {
            input.value = '';
        });
        pinInputs[0]?.focus();
        errorMessage.style.display = 'none';

        // Hide all content sections and reactivate login screen
        document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
    });

    // Navigation tabs
    const navTabs = document.querySelectorAll('.nav-tab');
    const contentSections = document.querySelectorAll('.content-section');

    navTabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');

            // Update active tab
            navTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Show corresponding content section
            contentSections.forEach(section => {
                section.classList.remove('active');
                section.style.display = 'none'; // Force hide
                if (section.id === targetId) {
                    section.classList.add('active');
                    // Use grid for risk_analysis to support the new dashboard design
                    section.style.display = (targetId === 'risk_analysis') ? 'grid' : 'block';

                    // Re-init chart if switching to risk analysis
                    if (targetId === 'risk_analysis' && typeof window.initRiskChart === 'function') {
                        setTimeout(() => window.initRiskChart(), 10);
                    }
                }
            });
        });
    });

    // Initialize dashboard with sample data
    function initializeDashboard() {
        // Sample data for other sections
        const documents = [
            { name: 'Employment Contract.pdf', case: 'C-001', date: '2023-05-20' },
            { name: 'Supplier Agreement.docx', case: 'C-002', date: '2023-06-25' }
        ];

        const billing = [
            { invoice: 'INV-001', client: 'Hotel Management', amount: '$2,500', dueDate: '2023-07-15', status: 'paid' },
            { invoice: 'INV-002', client: 'Restaurant Owner', amount: '$1,800', dueDate: '2023-08-05', status: 'pending' }
        ];

        const members = [
            { name: 'Robert Wilson', position: 'Senior Legal Counsel', email: 'robert@legalteam.com', phone: '(555) 111-2222' },
            { name: 'Emily Davis', position: 'Legal Assistant', email: 'emily@legalteam.com', phone: '(555) 333-4444' }
        ];

        // Populate tables with data
        populateTable('documentsTableBody', documents, 'document');
        populateTable('billingTableBody', billing, 'billing');
        populateTable('membersTableBody', members, 'member');
        // Contracts table uses the mock data - REMOVED
        // populateTable('contractsTableBody', MOCK_CONTRACTS_DATA, 'contract');

        // Find the logout button handler and update it:
        const backBtn = document.getElementById('backDashboardBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function (e) {
                e.preventDefault();
                // Trigger Loading Animation
                const loader = document.getElementById('loadingOverlay');
                if (loader) {
                    loader.style.display = 'block';
                    loader.style.opacity = '1';
                    const iframe = loader.querySelector('iframe');
                    if (iframe) iframe.src = iframe.src;

                    setTimeout(() => {
                        window.location.href = 'facilities-reservation.php';
                    }, 3000);
                } else {
                    window.location.href = 'facilities-reservation.php';
                }
            });
        }
        // Initialize risk analysis chart - REMOVED (Handled by PHP inline script)
        // initializeRiskChart();

        // Set up form handlers
        setupFormHandlers();
    }

    // Function to populate tables with data
    function populateTable(tableId, data, type) {
        const tableBody = document.getElementById(tableId);
        if (!tableBody) return;

        tableBody.innerHTML = '';

        data.forEach(item => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';

            if (type === 'document') {
                const docData = JSON.stringify({ id: item.id || 0, name: item.name, case_id: item.case || item.case_id, file_path: item.file_path || '', uploaded_at: item.date || item.uploaded_at || '' }).replace(/"/g, '&quot;');
                row.innerHTML = `
                        <td>${item.file_path ? `<a href="#" class="view-pdf-link" style="color:#2563eb; text-decoration:underline;" data-pdf-type="document" data-pdf-content="${docData}">${item.name}</a>` : item.name}</td>
                        <td>${item.case || item.case_id || 'N/A'}</td>
                        <td>${item.date || item.uploaded_at || 'N/A'}</td>
                        <td>
                            <button class="action-btn download-btn" 
                                data-pdf-type="document" 
                                data-pdf-content="${docData}"
                                style="background:linear-gradient(135deg, #059669 0%, #10b981 100%); color:#fff; border:none; border-radius:12px; padding:8px 16px; font-weight:700; box-shadow:0 4px 12px rgba(5,150,105,0.2); cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-file-pdf"></i> Download PDF
                            </button>
                        </td>`;
            } else if (type === 'billing') {
                const invData = JSON.stringify({ id: item.id || 0, invoice_number: item.invoice, client: item.client, amount: parseFloat((item.amount || '0').replace(/[^0-9.]/g, '')), due_date: item.dueDate, status: item.status }).replace(/"/g, '&quot;');
                const statusClass = `status-${item.status}`;
                row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">${item.invoice}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${item.client}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-center">${item.amount}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">${item.dueDate}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center"><span class="status-badge ${statusClass}">${item.status.toUpperCase()}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap space-x-2 text-center">
                            <button class="action-btn view-btn bg-blue-100 hover:bg-blue-200 text-blue-700 py-1 px-3 rounded-lg text-xs" 
                                data-type="invoice-view" data-invoice="${invData}">View</button>
                            <button class="action-btn download-btn bg-green-600 hover:bg-green-700 text-white py-1 px-3 rounded-lg text-xs" 
                                data-pdf-type="billing" data-pdf-content="${invData}">Download PDF</button>
                        </td>
                    `;
            } else if (type === 'member') {
                const empData = JSON.stringify({ id: item.id || 0, name: item.name, position: item.position, email: item.email, phone: item.phone }).replace(/"/g, '&quot;');
                row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.position}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-blue-600 hover:underline">${item.email}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${item.phone}</td>
                        <td class="px-6 py-4 whitespace-nowrap space-x-2 text-center">
                            <button class="action-btn view-btn bg-blue-100 hover:bg-blue-200 text-blue-700 py-1 px-3 rounded-lg text-xs" 
                                data-type="employee-view" data-emp="${empData}">View</button>
                            <button class="action-btn bg-yellow-100 hover:bg-yellow-200 text-yellow-700 py-1 px-3 rounded-lg text-xs" 
                                data-type="employee-edit" data-emp="${empData}">Edit</button>
                        </td>
                    `;
            } else if (type === 'contract') {
                const statusClass = `status-${item.risk_level.toLowerCase()}`;
                const contractDataString = JSON.stringify(item).replace(/"/g, '&quot;');
                row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">${item.file_path ? `<a href="#" class="view-pdf-link text-blue-600 hover:underline" data-pdf-type="contract" data-pdf-content="${contractDataString}">${item.contract_name || item.name}</a>` : (item.contract_name || item.name)}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${item.case_id}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center"><span class="status-badge ${statusClass}">${item.risk_level.toUpperCase()}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">${item.risk_score}/100</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">${item.upload_date || item.created_at}</td>
                        <td class="px-6 py-4 whitespace-nowrap space-x-2 text-center">
                            <button class="action-btn analyze-btn bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded-lg text-xs" 
                                data-type="contract-analyze" data-contract="${contractDataString}">AI Risk Analysis</button>
                            <button class="action-btn download-btn bg-green-600 hover:bg-green-700 text-white py-1 px-3 rounded-lg text-xs" 
                                data-pdf-type="contract" data-pdf-content="${contractDataString}">Download PDF</button>
                        </td>
                    `;
            }

            tableBody.appendChild(row);
        });
    }

    // Function to initialize risk analysis chart (FIXED: uses MOCK_CONTRACTS_DATA)
    function initializeRiskChart() {
        const ctx = document.getElementById('riskChart');
        if (!ctx) return;

        const contracts = MOCK_CONTRACTS_DATA; // <-- FIXED: Uses JavaScript mock data

        const riskCounts = { High: 0, Medium: 0, Low: 0 };

        contracts.forEach(contract => {
            if (riskCounts.hasOwnProperty(contract.risk_level)) {
                riskCounts[contract.risk_level]++;
            }
        });

        // Destroy previous chart instance if it exists (for re-initialization)
        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }

        const chartCtx = ctx.getContext('2d');

        const chart = new Chart(chartCtx, {
            type: 'bar',
            data: {
                labels: ['High Risk', 'Medium Risk', 'Low Risk'],
                datasets: [{
                    label: 'Number of Contracts',
                    data: [riskCounts.High, riskCounts.Medium, riskCounts.Low],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)', // Red for High
                        'rgba(245, 158, 11, 0.7)', // Yellow/Orange for Medium
                        'rgba(16, 185, 129, 0.7)' // Green for Low
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)'
                    ],
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: true, color: '#f3f4f6' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Contract Risk Distribution (Based on ' + contracts.length + ' files)',
                        font: { size: 16 }
                    }
                }
            }
        });

        // Display analysis results
        const totalContracts = contracts.length;
        const highRiskPercentage = totalContracts > 0 ? ((riskCounts.High / totalContracts) * 100).toFixed(1) : 0;
        const analysisResults = document.getElementById('analysisResults');
        if (analysisResults) {
            analysisResults.innerHTML = `
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Risk Analysis Summary</h3>
                    <div class="space-y-2 text-gray-700">
                        <p><strong>Total Contracts Analyzed:</strong> <span class="font-bold text-blue-600">${totalContracts}</span></p>
                        <p><strong>High Risk:</strong> <span class="font-bold text-red-500">${riskCounts.High} (${highRiskPercentage}%)</span></p>
                        <p><strong>Medium Risk:</strong> <span class="font-bold text-yellow-600">${riskCounts.Medium}</span></p>
                        <p><strong>Low Risk:</strong> <span class="font-bold text-green-600">${riskCounts.Low}</span></p>
                        <p class="pt-2 border-t mt-3"><strong>AI Recommendation:</strong> ${riskCounts.High > 0 ? '<span class="font-semibold text-red-500">Immediate review needed for high-risk files.</span>' : '<span class="font-semibold text-green-600">All contracts are within acceptable risk levels.</span>'}</p>
                    </div>
                `;
        }
    }

    // Enhanced Form Handlers
    function setupFormHandlers() {
        // Employee form is currently not fully implemented in HTML/JS, so focusing on contracts
        // Employee form handlers
        // ... (Employee form handlers and validation are omitted for brevity as they are not the source of the error)

        // Contract form handlers
        const addContractBtn = document.getElementById('addContractBtn');
        const contractForm = document.getElementById('contractForm');
        const cancelContractBtn = document.getElementById('cancelContractBtn');
        const contractFormData = document.getElementById('contractFormData');

        /*
        if (addContractBtn && contractForm) {
            addContractBtn.addEventListener('click', function () {
                contractForm.style.display = 'block';
                contractForm.scrollIntoView({ behavior: 'smooth' });
            });
        }
        */

        /*
        if (cancelContractBtn && contractForm) {
            cancelContractBtn.addEventListener('click', function () {
                contractForm.style.display = 'none';
                resetContractForm();
            });
        }
        */

        if (contractFormData) {
            contractFormData.addEventListener('submit', function (e) {
                // e.preventDefault(); // Removed to allow PHP submission
                if (validateContractForm()) {
                    console.log('SUCCESS: Form submitted with data:', new FormData(this));
                    // In a real app, this would send data to the server and re-initialize the dashboard
                    contractForm.style.display = 'none';
                    resetContractForm();
                    // For demonstration, we'll simulate an update
                    console.log('SIMULATION: Contract uploaded and sent for AI analysis.');
                } else {
                    console.error('FORM ERROR: Contract submission failed validation.');
                }
            });
        }

        // Client-side form validation for contracts
        function validateContractForm() {
            const name = document.getElementById('contractName').value.trim();
            const caseId = document.getElementById('contractCase').value.trim();
            const fileInput = document.getElementById('contractFile');
            const file = fileInput.files[0];

            clearContractErrors();

            let isValid = true;

            if (!name) {
                showError('contractName', 'Contract name is required');
                isValid = false;
            }

            if (!caseId) {
                showError('contractCase', 'Case ID is required');
                isValid = false;
            }

            if (!file) {
                showError('contractFile', 'Please select a file');
                isValid = false;
            } else if (file.size > 10 * 1024 * 1024) { // 10MB limit
                showError('contractFile', 'File size must be less than 10MB');
                isValid = false;
            } else if (!['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(file.type)) {
                showError('contractFile', 'Please upload a PDF, DOC, or DOCX file');
                isValid = false;
            }

            return isValid;
        }

        // Helper function to show errors
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add('error');

                let errorElement = field.parentNode.querySelector('.error-text');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'error-text';
                    field.parentNode.appendChild(errorElement);
                }
                errorElement.textContent = message;
            }
        }

        // Helper function to clear contract errors
        function clearContractErrors() {
            const fields = ['contractName', 'contractCase', 'contractFile'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('error');

                    const errorElement = field.parentNode.querySelector('.error-text');
                    if (errorElement) {
                        errorElement.remove();
                    }
                }
            });
        }

        // Reset contract form
        function resetContractForm() {
            document.getElementById('contractFormData')?.reset();
            clearContractErrors();
        }

        // FIX: Replaced alert() with console.log() and simulated UI behavior
        document.getElementById('addDocumentBtn')?.addEventListener('click', function () {
            console.log('SIMULATION: Document upload form would appear here.');
            // Show a simple confirmation message on the screen instead of alert()
            const button = this;
            button.textContent = 'Form Shown!';
            setTimeout(() => button.textContent = 'Add New Document', 1500);
        });

        document.getElementById('addInvoiceBtn')?.addEventListener('click', function () {
            console.log('SIMULATION: Invoice creation form would appear here.');
            const button = this;
            button.textContent = 'Form Shown!';
            setTimeout(() => button.textContent = 'Create New Invoice', 1500);
        });

        document.getElementById('addMemberBtn')?.addEventListener('click', function () {
            console.log('SIMULATION: Team member addition form would appear here.');
            const button = this;
            button.textContent = 'Form Shown!';
            setTimeout(() => button.textContent = 'Add New Member', 1500);
        });

        // FIX: Replaced confirm() with console.log and simulated UI behavior
        document.getElementById('exportPdfBtn')?.addEventListener('click', function () {
            const password = 'legal2025';
            console.log("SIMULATION: Download confirmation required. Password for Secured PDF: " + password);

            // --- Custom Modal Simulation (Replacing confirm) ---
            const modal = document.getElementById('detailsModal');
            document.getElementById('detailsTitle').innerText = 'Secure Report Export';
            document.getElementById('detailsBody').innerHTML = `
                    <div class="text-lg text-gray-800">
                        <p class="mb-2">Are you sure you want to download the Secured PDF Report?</p>
                        <p class="font-mono text-sm bg-gray-100 p-3 rounded-lg border">Password for the PDF: <strong class="text-blue-600">${password}</strong></p>
                        <p class="mt-4 text-sm text-gray-500">NOTE: This is a simulation. Downloading a .txt file containing the report data will be simulated upon confirmation.</p>
                    </div>
                `;
            document.getElementById('detailsModal').style.display = 'flex';

            // We'll simulate 'OK' by adding a temporary button to the modal footer
            const footer = modal.querySelector('.mt-4.pt-3.border-t.flex.justify-end');
            const originalCloseBtn = footer.querySelector('button');
            originalCloseBtn.textContent = 'Cancel';

            const confirmDownloadBtn = document.createElement('button');
            confirmDownloadBtn.textContent = 'Confirm Download';
            confirmDownloadBtn.className = 'bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition duration-200 ml-3';
            confirmDownloadBtn.addEventListener('click', function () {
                console.log("DOWNLOAD START: Simulating download of secured_legal_report.txt (Password: " + password + ")");

                // Actual file download simulation (creating a blob and downloading)
                const data = "Secured Legal Report (Password: legal2025)\n\n" + JSON.stringify(MOCK_CONTRACTS_DATA, null, 2);
                const blob = new Blob([data], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'secured_legal_report.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                // Clean up modal
                modal.style.display = 'none';
                footer.removeChild(confirmDownloadBtn);
                originalCloseBtn.textContent = 'Close';
            });
            footer.appendChild(confirmDownloadBtn);
        });
    }
});

// Global click listener for view, analyze, and modal close
document.addEventListener('click', function (e) {
    const detailsModal = document.getElementById('detailsModal');

    // Close modal button/overlay
    if (e.target.id === 'closeDetails' || (e.target.classList.contains('modal') && e.target.id === 'detailsModal')) {
        detailsModal.style.display = 'none';
        // Restore original close button in case a temp confirm button was added
        const footer = detailsModal.querySelector('.mt-4.pt-3.border-t.flex.justify-end');
        const confirmBtn = footer.querySelector('.bg-green-600');
        if (confirmBtn) {
            confirmBtn.remove();
            footer.querySelector('button').textContent = 'Close';
        }
        return;
    }

    // Analyze button handler for contracts with AI analysis (Premium Design Fix)
    if (e.target && e.target.classList.contains('analyze-btn')) {
        const contractDataString = e.target.getAttribute('data-contract');
        if (!contractDataString) return;

        // FIX: The replacement of " in the JSON string needs to be reversed
        const c = JSON.parse(contractDataString.replace(/&quot;/g, '"'));

        const detailsTitle = document.getElementById('detailsTitle');
        const detailsBody = document.getElementById('detailsBody');
        const detailsModal = document.getElementById('detailsModal');

        if (detailsTitle) detailsTitle.textContent = 'AI Risk Analysis';
        if (detailsModal) detailsModal.style.display = 'flex';

        // Initial Loading State
        if (detailsBody) {
            detailsBody.innerHTML = `<div style="padding:20px;text-align:center;color:#64748b;"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;margin-bottom:10px;"></i><br>Generating analysis report...</div>`;

            try {
                const score = c.risk_score ?? 'N/A';
                const level = c.risk_level ?? 'Unknown';
                const summary = c.analysis_summary || 'No analysis summary available.';

                // Parse lists safely
                const rf = (() => { try { return JSON.parse(c.risk_factors || '[]'); } catch { return []; } })();
                const rec = (() => { try { return JSON.parse(c.recommendations || '[]'); } catch { return []; } })();

                // Determine Icon and Color based on Level
                let iconClass = 'fa-check-circle';
                let colorClass = '#22c55e'; // Green
                let bgClass = '#dcfce7'; // Light Green
                let levelText = 'Low Risk';

                if (level === 'High') {
                    iconClass = 'fa-triangle-exclamation';
                    colorClass = '#ef4444'; // Red
                    bgClass = '#fee2e2'; // Light Red
                    levelText = 'High Risk';
                } else if (level === 'Medium') {
                    iconClass = 'fa-circle-exclamation';
                    colorClass = '#f59e0b'; // Amber
                    bgClass = '#fef3c7'; // Light Amber
                    levelText = 'Medium Risk';
                }

                // Build HTML
                detailsBody.innerHTML = `
                    <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                        <div style="width: 80px; height: 80px; background: ${bgClass}; border-radius: 50%; display: inline-grid; place-items: center; margin-bottom: 15px; margin-left: auto; margin-right: auto;">
                            <i class="fa-solid ${iconClass}" style="font-size: 36px; color: ${colorClass};"></i>
                        </div>
                        <h2 style="margin: 0; color: #1e293b; font-size: 1.75rem; font-weight: 800;">${levelText} Detected</h2>
                        <p style="margin: 5px 0 0; color: #64748b; font-size: 1.1rem;">Risk Score: <strong style="color: ${colorClass}; font-weight: 700;">${score}/100</strong></p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 25px; position: relative; overflow: hidden;">
                        <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: ${colorClass};"></div>
                        <h4 style="margin: 0 0 10px; color: #334155; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">Analysis Summary</h4>
                        <p style="margin: 0; color: #475569; line-height: 1.6; font-size: 0.95rem;">${summary}</p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div>
                            <h4 style="margin: 0 0 15px; color: #dc2626; display: flex; align-items: center; gap: 8px; font-size: 1rem; border-bottom: 2px solid #fee2e2; padding-bottom: 8px;">
                                <i class="fa-solid fa-bug"></i> Risk Factors
                            </h4>
                            <ul style="margin: 0; padding: 0; list-style: none;">
                                ${rf.length > 0 ? rf.map(r => `
                                    <li style="background: #fff; border: 1px solid #fee2e2; border-left: 3px solid #ef4444; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9rem; color: #4b5563; display: flex; align-items: flex-start; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <i class="fa-solid fa-circle-exclamation" style="color: #ef4444; margin-top: 3px; font-size: 0.8rem;"></i>
                                        <div>
                                            ${r.category ? `<strong style="display:block; font-size: 0.75rem; color: #ef4444; text-transform: uppercase;">${r.category.replace('_', ' ')}</strong>` : ''}
                                            ${r.factor || 'Unknown Factor'}
                                        </div>
                                    </li>
                                `).join('') : '<li style="color: #94a3b8; font-style: italic; text-align: center; padding: 10px;">No significant risks detected.</li>'}
                            </ul>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 15px; color: #059669; display: flex; align-items: center; gap: 8px; font-size: 1rem; border-bottom: 2px solid #dcfce7; padding-bottom: 8px;">
                                <i class="fa-solid fa-lightbulb"></i> Recommendations
                            </h4>
                            <ul style="margin: 0; padding: 0; list-style: none;">
                                ${rec.length > 0 ? rec.map(x => `
                                    <li style="background: #fff; border: 1px solid #dcfce7; border-left: 3px solid #22c55e; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9rem; color: #4b5563; display: flex; align-items: flex-start; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <i class="fa-solid fa-check" style="color: #22c55e; margin-top: 3px; font-size: 0.8rem;"></i>
                                        <span style="flex: 1;">${x}</span>
                                    </li>
                                `).join('') : '<li style="color: #94a3b8; font-style: italic; text-align: center; padding: 10px;">Standard review recommended.</li>'}
                            </ul>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 25px;">
                        <button id="downloadAnalysisBtn" type="button" style="display: inline-flex; align-items: center; gap: 10px; background: #3b82f6; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 700; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4); border: none; cursor: pointer;">
                            <i class="fa-solid fa-file-pdf" style="font-size: 1.1rem;"></i> Download Analysis Report
                        </button>
                    </div>

                    <div style="text-align:center; margin-top: 20px;">
                            <button type="button" onclick="document.getElementById('closeDetails').click()" style="background:none; border:none; color: #94a3b8; cursor:pointer; font-size:0.9rem; text-decoration:underline;">Close Analysis</button>
                    </div>
                `;

                // Add PDF Download Listener
                setTimeout(() => {
                    const dlBtn = document.getElementById('downloadAnalysisBtn');
                    if (dlBtn) {
                        dlBtn.addEventListener('click', function () {
                            const originalText = this.innerHTML;
                            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating PDF...';
                            this.style.opacity = '0.7';

                            // Hide buttons for PDF capture
                            this.style.display = 'none';
                            const closeBtn = document.querySelector('#detailsBody button[onclick*="closeDetails"]');
                            if (closeBtn) closeBtn.style.display = 'none';

                            const element = document.getElementById('detailsBody');
                            const opt = {
                                margin: [10, 10], // Top/Bottom margin
                                filename: `Risk_Analysis_${c.contract_name ? c.contract_name.replace(/[^a-z0-9]/gi, '_') : 'Report'}.pdf`,
                                image: { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2, useCORS: true, logging: false },
                                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                            };

                            html2pdf().set(opt).from(element).save().then(() => {
                                // Restore UI
                                dlBtn.style.display = 'inline-flex';
                                if (closeBtn) closeBtn.style.display = 'inline-block';
                                dlBtn.innerHTML = originalText;
                                dlBtn.style.opacity = '1';
                            }).catch(err => {
                                console.error(err);
                                dlBtn.style.display = 'inline-flex';
                                if (closeBtn) closeBtn.style.display = 'inline-block';
                                dlBtn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Error';
                            });
                        });
                    }
                }, 100);
            } catch (err) {
                console.error(err);
                detailsBody.innerHTML = `<div style="padding:20px;text-align:center;color:#ef4444;"><i class="fa-solid fa-circle-xmark" style="font-size:3rem;margin-bottom:15px;"></i><br>Unable to load analysis. Data might be corrupted.</div>`;
            }
        }
    }


});

// Real-time AI analysis preview
document.getElementById('contractDescription')?.addEventListener('input', function (e) {
    const description = e.target.value;
    const previewDiv = document.getElementById('aiAnalysisPreview');

    if (description.length > 50) {
        console.log('AI analyzing contract description...');
        // In a real implementation, this would call an API for real-time analysis
        // For demo: update a visible section with simulated analysis
        let preview = document.getElementById('analysisPreviewText');
        if (!preview) {
            preview = document.createElement('p');
            preview.id = 'analysisPreviewText';
            preview.className = 'text-sm text-gray-600 italic mt-2 p-2 bg-blue-50 rounded';
            document.getElementById('contractDescription').parentNode.appendChild(preview);
        }

        // Simple mock logic for preview
        let risk = description.toLowerCase().includes('liability') || description.toLowerCase().includes('breach') ? 'Medium/High' : 'Low';
        preview.innerHTML = `AI Preview: <span class="font-semibold text-blue-700">${risk} Risk</span> predicted. Focus on clause structure and duration.`;
    } else {
        document.getElementById('analysisPreviewText')?.remove();
    }
});

/* --- START: PIN modal & sensitive view handlers (moved to external JS) --- */
document.addEventListener('DOMContentLoaded', function () {
    const pinModal = document.getElementById('pinModal');
    const unlockPin = document.getElementById('unlockPin');
    const unlockBtn = document.getElementById('unlockBtn');
    const closePinModal = document.getElementById('closePinModal');
    const modalMessage = document.getElementById('modalMessage');
    const sensitiveResult = document.getElementById('sensitiveResult');
    let currentTarget = { id: 0, type: '' };

    // Open PIN modal for sensitive view buttons
    document.querySelectorAll('.view-sensitive').forEach(btn => {
        btn.addEventListener('click', function () {
            currentTarget.id = this.dataset.id || 0;
            currentTarget.type = this.dataset.type || '';
            if (unlockPin) unlockPin.value = '';
            if (modalMessage) modalMessage.textContent = '';
            if (sensitiveResult) { sensitiveResult.style.display = 'none'; sensitiveResult.textContent = ''; }
            if (pinModal) pinModal.style.display = 'block';
            if (unlockPin) unlockPin.focus();
        });
    });

    // Close PIN modal
    if (closePinModal) {
        closePinModal.addEventListener('click', function () {
            if (pinModal) pinModal.style.display = 'none';
        });
    }

    // Unlock and fetch sensitive data (POST to same PHP page expecting action=unlock)
    if (unlockBtn) {
        unlockBtn.addEventListener('click', function () {
            if (!currentTarget.type || !currentTarget.id) return;
            if (modalMessage) modalMessage.textContent = '';
            const pin = unlockPin ? unlockPin.value : '';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'unlock',
                    type: currentTarget.type,
                    id: currentTarget.id,
                    pin: pin
                })
            }).then(r => r.json()).then(resp => {
                if (!resp.success) {
                    if (modalMessage) modalMessage.textContent = resp.message || 'Unable to unlock';
                    if (sensitiveResult) sensitiveResult.style.display = 'none';
                } else {
                    if (modalMessage) modalMessage.textContent = '';
                    if (sensitiveResult) {
                        sensitiveResult.style.display = 'block';
                        sensitiveResult.textContent = JSON.stringify(resp.data, null, 2);
                    }
                }
            }).catch(() => {
                if (modalMessage) modalMessage.textContent = 'Request error';
            });
        });
    }

    // Ensure logout button redirects to facilities-reservation.php (if present)
    const logoutBtn = document.getElementById('backDashboardBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '../Modules/facilities-reservation.php';
        });
    }
});
/* --- END: PIN modal & sensitive view handlers --- */
