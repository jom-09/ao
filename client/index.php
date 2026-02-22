<?php
session_start();

// Clear any previous session data
unset($_SESSION['client_info']);

$error = "";
$success = "";
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);
if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Information | Certificate System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Framework CSS (Your specified paths) -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<div class="client-wrapper">
    <!-- Progress Steps -->
    <div class="progress-container">
        <div class="progress-step active" data-step="1">
            <div class="step-circle">1</div>
            <span class="step-label">Info</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="2">
            <div class="step-circle">2</div>
            <span class="step-label">Certificate</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="3">
            <div class="step-circle">3</div>
            <span class="step-label">Confirm</span>
        </div>
    </div>

    <div class="client-card card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            
            <!-- Header -->
            <div class="text-center mb-4">
                <div class="brand-icon mb-3">
                    <i class="bi bi-person-vcard fs-1"></i>
                </div>
                <h4 class="fw-bold mb-1">Client Information</h4>
                <p class="text-muted small">Please fill in your details to proceed</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Interactive Form -->
            <form action="select_cert.php" method="POST" id="clientForm" novalidate>
                
                <!-- Name Fields Row -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="firstname" class="form-control" id="firstname" 
                                   placeholder="First Name" required pattern="[A-Za-z\s]{2,50}" 
                                   autocomplete="given-name">
                            <label for="firstname"></i>First Name</label>
                            <div class="invalid-feedback">Please enter a valid first name (2-50 letters)</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="middlename" class="form-control" id="middlename" 
                                   placeholder="Middle Name" pattern="[A-Za-z\s]{0,50}"
                                   autocomplete="additional-name">
                            <label for="middlename"></i>Middle Name</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="lastname" class="form-control" id="lastname" 
                                   placeholder="Last Name" required pattern="[A-Za-z\s]{2,50}"
                                   autocomplete="family-name">
                            <label for="lastname">Last Name</label>
                            <div class="invalid-feedback">Please enter a valid last name (2-50 letters)</div>
                        </div>
                    </div>
                </div>

                <!-- Address Field -->
                <div class="form-floating mb-3 mt-4">
                    <textarea name="address" class="form-control" id="address" 
                              placeholder="Address" required rows="3" style="height: 100px;"
                              autocomplete="street-address"></textarea>
                    <label for="address"><i class="bi bi-geo-alt me-1"></i>Complete Address</label>
                    <div class="invalid-feedback">Please enter your complete address</div>
                    <div class="form-text text-end"><span id="charCount">0</span>/255 characters</div>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" name="cp_no" class="form-control"
                        placeholder="Contact Number"
                        required
                        pattern="^(09\d{9}|\+63\d{10})$">
                    <label>Contact Number (09xxxxxxxxx)</label>
                </div>

                <!-- Purpose Selection (MULTI-SELECT) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-1"></i>Purpose of Request</span>
                        <small class="text-muted fw-normal" id="purposeCount">0 selected</small>
                    </label>
                    
                    <div class="purpose-options" id="purposeContainer">
                        <!-- Testing -->
                        <label class="purpose-option" data-value="Testing">
                            <input type="checkbox" name="purpose[]" value="Testing" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-flask option-icon"></i>
                                <span>Testing</span>
                            </div>
                        </label>
                        
                        <!-- Business -->
                        <label class="purpose-option" data-value="Business">
                            <input type="checkbox" name="purpose[]" value="Business" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-briefcase option-icon"></i>
                                <span>Business</span>
                            </div>
                        </label>
                        
                        <!-- Personal -->
                        <label class="purpose-option" data-value="Personal">
                            <input type="checkbox" name="purpose[]" value="Personal" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-heart option-icon"></i>
                                <span>Personal</span>
                            </div>
                        </label>
                        
                        <!-- Additional Options (Expandable) -->
                        <label class="purpose-option" data-value="Education">
                            <input type="checkbox" name="purpose[]" value="Education" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-mortarboard option-icon"></i>
                                <span>Education</span>
                            </div>
                        </label>
                        
                        <label class="purpose-option" data-value="Legal">
                            <input type="checkbox" name="purpose[]" value="Legal" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-scale option-icon"></i>
                                <span>Legal</span>
                            </div>
                        </label>
                        
                        <label class="purpose-option" data-value="Other">
                            <input type="checkbox" name="purpose[]" value="Other" class="purpose-checkbox">
                            <div class="option-card">
                                <div class="option-check">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <i class="bi bi-three-dots option-icon"></i>
                                <span>Other</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Hidden input for validation -->
                    <input type="hidden" id="purposeValidation" required>
                    <div class="invalid-feedback d-block">Please select at least one purpose</div>
                    
                    <!-- Selected Tags Preview -->
                    <div class="selected-tags mt-3 d-none" id="selectedTags">
                        <small class="text-muted d-block mb-2">Selected:</small>
                        <div class="d-flex flex-wrap gap-2" id="tagsContainer"></div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3 justify-content-between">
                    <a href="../index.php" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                    <button type="submit" class="btn btn-submit btn-lg px-5">
                        <span class="btn-text">Next <i class="bi bi-arrow-right ms-1"></i></span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>

            <!-- Help Section -->
            <div class="help-section mt-4 pt-3 border-top">
                <small class="text-muted d-flex align-items-center">
                    <i class="bi bi-info-circle me-2"></i>
                    All fields marked with <span class="text-danger">*</span> are required. You can select multiple purposes.
                </small>
            </div>

        </div>
        
        <!-- Decorative Bottom Bar -->
        <div class="card-footer-custom"></div>
    </div>

    <!-- Background Decoration -->
    <div class="bg-decoration"></div>
</div>

<!-- Framework JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<!-- Interactive Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('clientForm');
    const address = document.getElementById('address');
    const charCount = document.getElementById('charCount');
    const submitBtn = document.querySelector('.btn-submit');
    const purposeCheckboxes = document.querySelectorAll('.purpose-checkbox');
    const purposeCount = document.getElementById('purposeCount');
    const selectedTags = document.getElementById('selectedTags');
    const tagsContainer = document.getElementById('tagsContainer');
    const purposeValidation = document.getElementById('purposeValidation');

    // Character counter for address
    address.addEventListener('input', function() {
        const len = this.value.length;
        charCount.textContent = len;
        if (len > 255) {
            this.value = this.value.substring(0, 255);
            charCount.textContent = '255';
        }
    });

    // Purpose multi-select handling
    function updatePurposeSelection() {
        const selected = Array.from(purposeCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        // Update counter
        const count = selected.length;
        purposeCount.textContent = count === 0 ? '0 selected' : `${count} selected`;
        purposeCount.className = count > 0 ? 'text-success fw-semibold' : 'text-muted fw-normal';
        
        // Update validation hidden field
        purposeValidation.value = count > 0 ? 'valid' : '';
        
        // Update visual selection states
        document.querySelectorAll('.purpose-option').forEach(option => {
            const checkbox = option.querySelector('.purpose-checkbox');
            const card = option.querySelector('.option-card');
            if (checkbox.checked) {
                option.classList.add('selected');
                card.classList.add('selected');
            } else {
                option.classList.remove('selected');
                card.classList.remove('selected');
            }
        });
        
        // Update tags preview
        updateTagsPreview(selected);
    }

    function updateTagsPreview(selected) {
        if (selected.length === 0) {
            selectedTags.classList.add('d-none');
            tagsContainer.innerHTML = '';
            return;
        }
        
        selectedTags.classList.remove('d-none');
        tagsContainer.innerHTML = selected.map(value => {
            const icons = {
                'Testing': 'bi-flask',
                'Business': 'bi-briefcase',
                'Personal': 'bi-heart',
                'Education': 'bi-mortarboard',
                'Legal': 'bi-scale',
                'Other': 'bi-three-dots'
            };
            return `
                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle d-flex align-items-center gap-1 px-3 py-2">
                    <i class="bi ${icons[value] || 'bi-tag'}"></i>
                    ${value}
                    <button type="button" class="btn-close btn-close-sm ms-1" 
                            data-value="${value}" aria-label="Remove"></button>
                </span>
            `;
        }).join('');
        
        // Add remove functionality to tags
        tagsContainer.querySelectorAll('.btn-close').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const value = this.dataset.value;
                const checkbox = document.querySelector(`.purpose-checkbox[value="${value}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        });
    }

    // Attach change listeners to all purpose checkboxes
    purposeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updatePurposeSelection);
        
        // Add click animation to parent label
        checkbox.closest('.purpose-option').addEventListener('click', function(e) {
            if (e.target === checkbox || e.target.closest('.option-card')) {
                this.classList.add('click-animate');
                setTimeout(() => this.classList.remove('click-animate'), 200);
            }
        });
    });

    // Form validation with Bootstrap
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Custom validation for purpose
        const selectedPurposes = Array.from(purposeCheckboxes).filter(cb => cb.checked);
        if (selectedPurposes.length === 0) {
            e.stopPropagation();
            purposeValidation.value = '';
            form.classList.add('was-validated');
            document.getElementById('purposeContainer').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            return;
        }
        
        // Standard Bootstrap validation
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            
            // Scroll to first invalid field
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-text').classList.add('d-none');
        submitBtn.querySelector('.btn-loading').classList.remove('d-none');

        // Simulate processing (remove this in production)
        setTimeout(() => {
            form.submit();
        }, 1500);
    });

    // Real-time validation feedback for text inputs
    const inputs = form.querySelectorAll('input[required]:not([type="checkbox"]), textarea[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid') && this.checkValidity()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });

    // Initialize
    updatePurposeSelection();

    // Animate form on load
    setTimeout(() => {
        document.querySelector('.client-card').style.opacity = '1';
        document.querySelector('.client-card').style.transform = 'translateY(0)';
    }, 100);
});
</script>

</body>
</html>