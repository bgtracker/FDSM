<?php
/**
 * IBAN Validation Test Script
 * Use this script to test the IBAN validation functionality
 * Access: http://yoursite.com/test_iban.php
 */

// Test IBANs for validation
$test_ibans = [
    'DE89370400440532013000', // Valid German IBAN
    'GB29NWBK60161331926819', // Valid UK IBAN
    'FR1420041010050500013M02606', // Valid French IBAN
    'ES9121000418450200051332', // Valid Spanish IBAN
    'INVALID123456789', // Invalid IBAN
    '', // Empty IBAN
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBAN Validation Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4">
                    <i class="fas fa-university me-2"></i>
                    IBAN Validation Test
                </h1>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Manual IBAN Testing</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="test_iban" class="form-label">Enter IBAN to Test</label>
                            <input type="text" class="form-control" id="test_iban" 
                                   placeholder="e.g., DE89370400440532013000" maxlength="34">
                            <div class="form-text">
                                <span id="validation-status"></span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="validateManualIBAN()">
                            <i class="fas fa-check me-1"></i>Validate IBAN
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Automated Test Results</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Testing multiple IBANs automatically...</p>
                        <div id="test-results">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                                <p>Running IBAN validation tests...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">API Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>API Used:</strong> openiban.com (Free IBAN Validation Service)</p>
                        <p><strong>Endpoint:</strong> <code>https://openiban.com/validate/{IBAN}</code></p>
                        <p><strong>Features:</strong></p>
                        <ul>
                            <li>IBAN format validation</li>
                            <li>Checksum verification</li>
                            <li>Bank information lookup (where available)</li>
                            <li>Cross-origin requests supported</li>
                        </ul>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This is a free service. For production systems with high volume, 
                            consider upgrading to a paid IBAN validation service for guaranteed uptime and support.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test IBANs
        const testIbans = <?php echo json_encode($test_ibans); ?>;
        
        // Manual IBAN validation
        function validateManualIBAN() {
            const iban = document.getElementById('test_iban').value.trim();
            const statusDiv = document.getElementById('validation-status');
            
            if (!iban) {
                statusDiv.innerHTML = '<span class="text-warning">Please enter an IBAN to test</span>';
                return;
            }
            
            validateIBAN(iban, statusDiv);
        }

        // IBAN validation function
        function validateIBAN(iban, statusElement) {
            const cleanIban = iban.replace(/\s/g, '').toUpperCase();
            
            statusElement.innerHTML = '<i class="fas fa-spinner fa-spin text-primary me-1"></i>Validating...';
            
            fetch(`https://openiban.com/validate/${cleanIban}`)
                .then(response => response.json())
                .then(data => {
                    if (data.valid === true) {
                        let result = '<i class="fas fa-check-circle text-success me-1"></i>Valid IBAN';
                        if (data.bankData && data.bankData.name) {
                            result += ` - ${data.bankData.name}`;
                        }
                        if (data.bankData && data.bankData.bic) {
                            result += ` (BIC: ${data.bankData.bic})`;
                        }
                        statusElement.innerHTML = result;
                    } else {
                        statusElement.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i>Invalid IBAN';
                    }
                })
                .catch(error => {
                    console.error('Validation error:', error);
                    statusElement.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>API Error - Could not validate';
                });
        }

        // Format IBAN input
        document.getElementById('test_iban').addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').toUpperCase();
            let formatted = '';
            for (let i = 0; i < value.length; i += 4) {
                if (i > 0) formatted += ' ';
                formatted += value.substr(i, 4);
            }
            this.value = formatted;
        });

        // Run automated tests
        function runAutomatedTests() {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '<h6>Test Results:</h6>';
            
            let completedTests = 0;
            
            testIbans.forEach((iban, index) => {
                const testDiv = document.createElement('div');
                testDiv.className = 'border p-3 mb-2 rounded';
                testDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>Test ${index + 1}:</strong> ${iban || '(Empty IBAN)'}
                        </div>
                        <div id="result-${index}">
                            <i class="fas fa-spinner fa-spin text-primary"></i>
                        </div>
                    </div>
                `;
                resultsDiv.appendChild(testDiv);
                
                if (iban.trim() === '') {
                    document.getElementById(`result-${index}`).innerHTML = 
                        '<span class="badge bg-secondary">Skipped (Empty)</span>';
                    completedTests++;
                } else {
                    // Delay each request to avoid overwhelming the API
                    setTimeout(() => {
                        fetch(`https://openiban.com/validate/${iban.replace(/\s/g, '')}`)
                            .then(response => response.json())
                            .then(data => {
                                const resultElement = document.getElementById(`result-${index}`);
                                if (data.valid === true) {
                                    resultElement.innerHTML = '<span class="badge bg-success">Valid</span>';
                                } else {
                                    resultElement.innerHTML = '<span class="badge bg-danger">Invalid</span>';
                                }
                                completedTests++;
                                
                                if (completedTests === testIbans.length) {
                                    const summaryDiv = document.createElement('div');
                                    summaryDiv.className = 'alert alert-info mt-3';
                                    summaryDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>All tests completed!</strong>';
                                    resultsDiv.appendChild(summaryDiv);
                                }
                            })
                            .catch(error => {
                                document.getElementById(`result-${index}`).innerHTML = 
                                    '<span class="badge bg-warning">API Error</span>';
                                completedTests++;
                            });
                    }, index * 1000); // 1 second delay between requests
                }
            });
        }

        // Start automated tests when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(runAutomatedTests, 1000);
        });
    </script>
</body>
</html>