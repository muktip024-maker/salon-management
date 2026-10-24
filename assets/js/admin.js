/**
 * Salon Management System - Executive JavaScript & Complete Controls Validation
 * Pure Vanilla JavaScript (0 HTML5 tooltips / 0 jQuery)
 * Handles real-time validation across ALL form controls (Status, Date, Time, Services, Staff, Notes) & AJAX submissions.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 0. Disable HTML5 browser tooltips on all forms
    document.querySelectorAll('form').forEach(form => {
        form.setAttribute('novalidate', 'novalidate');
    });

    // 1. Executive Toast Notification System
    function showExecutiveToast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container-executive');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container-executive';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = `toast-executive ${type === 'danger' || type === 'error' ? 'toast-danger' : ''}`;
        
        const icon = type === 'danger' || type === 'error' 
            ? '<i class="fas fa-exclamation-triangle text-danger"></i>' 
            : '<i class="fas fa-check-circle text-success"></i>';

        toast.innerHTML = `${icon} <span>${message}</span>`;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // 2. Auto-open Book Appointment Modal if ?book=1 URL parameter exists
    if (window.location.search.includes('book=1')) {
        const modalElement = document.getElementById('addAppointmentModal');
        if (modalElement && typeof bootstrap !== 'undefined') {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        }
    }

    // 3. Sidebar Toggle for Mobile Devices
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // 4. Delete Action Confirmation
    const deleteButtons = document.querySelectorAll('.confirm-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this record? This action cannot be undone.';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 5. Dynamic Service Price Calculation & Live Selection Summary Chips
    function updateModalSelectionSummaries() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            // Services Selection Summary
            const srvCheckboxes = modal.querySelectorAll('input[name="services[]"]');
            const srvChipContainer = modal.querySelector('.selected-services-chips');
            const srvTotalDisplay = modal.querySelector('.appointment-total-display');

            if (srvCheckboxes.length > 0) {
                let total = 0;
                let selectedChipsHtml = '';
                let selectedCount = 0;

                srvCheckboxes.forEach(cb => {
                    const parentCard = cb.closest('.checkbox-item-card');
                    if (cb.checked) {
                        if (parentCard) parentCard.classList.add('is-checked');
                        const price = parseFloat(cb.getAttribute('data-price')) || 0;
                        total += price;
                        selectedCount++;
                        const srvNameEl = cb.closest('label').querySelector('.fw-semibold');
                        const srvName = srvNameEl ? srvNameEl.textContent.trim() : 'Service';
                        selectedChipsHtml += `<span class="chip-selected-item"><i class="fas fa-check-circle text-success me-1"></i>${srvName} <i class="fas fa-times remove-chip-btn" data-cb-id="${cb.id}"></i></span>`;
                    } else {
                        if (parentCard) parentCard.classList.remove('is-checked');
                    }
                });

                if (srvTotalDisplay) {
                    srvTotalDisplay.textContent = '₹' + total.toFixed(2);
                }

                if (srvChipContainer) {
                    if (selectedCount > 0) {
                        srvChipContainer.innerHTML = selectedChipsHtml;
                    } else {
                        srvChipContainer.innerHTML = '<span class="text-muted small">No services selected</span>';
                    }
                }
            }

            // Staff Selection Summary
            const stfCheckboxes = modal.querySelectorAll('input[name="staff[]"]');
            const stfChipContainer = modal.querySelector('.selected-staff-chips');
            const stfCountDisplay = modal.querySelector('.selected-staff-count');

            if (stfCheckboxes.length > 0) {
                let selectedChipsHtml = '';
                let selectedCount = 0;

                stfCheckboxes.forEach(cb => {
                    const parentCard = cb.closest('.checkbox-item-card');
                    if (cb.checked) {
                        if (parentCard) parentCard.classList.add('is-checked');
                        selectedCount++;
                        const stfNameEl = cb.closest('label').querySelector('.fw-semibold');
                        const stfName = stfNameEl ? stfNameEl.textContent.trim() : 'Staff';
                        selectedChipsHtml += `<span class="chip-selected-item chip-staff-selected"><i class="fas fa-user-circle me-1"></i>${stfName} <i class="fas fa-times remove-chip-btn" data-cb-id="${cb.id}"></i></span>`;
                    } else {
                        if (parentCard) parentCard.classList.remove('is-checked');
                    }
                });

                if (stfCountDisplay) {
                    stfCountDisplay.textContent = selectedCount + ' Selected';
                }

                if (stfChipContainer) {
                    if (selectedCount > 0) {
                        stfChipContainer.innerHTML = selectedChipsHtml;
                    } else {
                        stfChipContainer.innerHTML = '<span class="text-muted small">No staff assigned</span>';
                    }
                }
            }
        });
    }

    // Checkbox Change Event
    document.addEventListener('change', function(e) {
        if (e.target && (e.target.name === 'services[]' || e.target.name === 'staff[]')) {
            updateModalSelectionSummaries();
            const form = e.target.closest('form');
            if (form) {
                validateServiceCheckboxes(form);
                validateStaffCheckboxes(form);
            }
        }
    });

    // Remove Chip Click Event
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-chip-btn')) {
            const targetId = e.target.getAttribute('data-cb-id');
            if (targetId) {
                const cb = document.getElementById(targetId);
                if (cb) {
                    cb.checked = false;
                    updateModalSelectionSummaries();
                    const form = cb.closest('form');
                    if (form) {
                        validateServiceCheckboxes(form);
                        validateStaffCheckboxes(form);
                    }
                }
            }
        }
    });

    // Run summary on modal show
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', updateModalSelectionSummaries);
    });
    updateModalSelectionSummaries();

    // 6. Comprehensive Controls Real-Time Validation Functions
    function validateCustomField(field) {
        let isValid = true;
        let errorMsg = '';

        const name = field.name;
        const value = field.value ? field.value.trim() : '';

        // Clear previous error on this field
        field.classList.remove('is-invalid-custom');
        if (field.nextElementSibling && field.nextElementSibling.classList.contains('custom-error-msg')) {
            field.nextElementSibling.remove();
        }

        // 1. Select Controls (Customer, Status, Category, Appointment)
        if (field.tagName.toLowerCase() === 'select') {
            if (name === 'customer_id') {
                if (!value || value === '') {
                    isValid = false;
                    errorMsg = 'Please select a customer.';
                }
            } else if (name === 'status') {
                if (!value || value === '') {
                    isValid = false;
                    errorMsg = 'Please select a valid initial status.';
                }
            } else if (name === 'category_id') {
                if (!value || value === '') {
                    isValid = false;
                    errorMsg = 'Please select a service category.';
                }
            } else if (name === 'appointment_id') {
                if (!value || value === '') {
                    isValid = false;
                    errorMsg = 'Please select an appointment.';
                }
            } else if (field.hasAttribute('required')) {
                if (!value || value === '') {
                    isValid = false;
                    errorMsg = 'Please select an option.';
                }
            }
        }

        // 2. Date Controls
        if (name === 'appointment_date' || field.type === 'date') {
            if (!value) {
                isValid = false;
                errorMsg = 'Appointment date is required.';
            }
        }

        // 3. Time Controls
        if (name === 'appointment_time' || field.type === 'time') {
            if (!value) {
                isValid = false;
                errorMsg = 'Appointment time is required.';
            }
        }

        // 4. Text / Email / Phone / Password / Number Inputs & Textareas
        if (field.tagName.toLowerCase() === 'input' || field.tagName.toLowerCase() === 'textarea') {
            const type = field.type;

            if (name === 'name') {
                if (!value) {
                    isValid = false;
                    errorMsg = 'Full name is required.';
                }
            } else if (name === 'service_name') {
                if (!value) {
                    isValid = false;
                    errorMsg = 'Service name is required.';
                }
            } else if (name === 'category_name') {
                if (!value) {
                    isValid = false;
                    errorMsg = 'Category name is required.';
                }
            } else if (field.hasAttribute('required')) {
                if (!value) {
                    isValid = false;
                    errorMsg = 'This field cannot be blank.';
                }
            }

            // Email validation
            if (isValid && (type === 'email' || name === 'email')) {
                if (field.hasAttribute('required') || value !== '') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        errorMsg = 'Please enter a valid email address.';
                    }
                }
            }

            // Phone number validation
            if (isValid && (name === 'phone' || field.id === 'phone')) {
                if (field.hasAttribute('required') || value !== '') {
                    const phoneClean = value.replace(/[\s\-\(\)\+]/g, '');
                    if (!/^\d{7,15}$/.test(phoneClean)) {
                        isValid = false;
                        errorMsg = 'Please enter a valid 10-15 digit phone number.';
                    }
                }
            }

            // Password validation
            if (isValid && (type === 'password' || name === 'password' || name === 'new_password')) {
                if ((field.hasAttribute('required') && !value) || (value && value.length < 6)) {
                    isValid = false;
                    errorMsg = 'Password must be at least 6 characters.';
                }
            }

            // Price / Amount numeric validation
            if (isValid && (name === 'price' || name === 'amount')) {
                if (field.hasAttribute('required') || value !== '') {
                    const num = parseFloat(value);
                    if (isNaN(num) || num < 0) {
                        isValid = false;
                        errorMsg = 'Amount must be a valid non-negative number.';
                    }
                }
            }

            // Duration numeric validation
            if (isValid && name === 'duration') {
                if (field.hasAttribute('required') || value !== '') {
                    const dur = parseInt(value);
                    if (isNaN(dur) || dur <= 0) {
                        isValid = false;
                        errorMsg = 'Duration must be greater than 0 minutes.';
                    }
                }
            }

            // 5. Notes / Instructions validation
            if (isValid && name === 'notes') {
                if (value.length > 500) {
                    isValid = false;
                    errorMsg = 'Notes cannot exceed 500 characters.';
                }
            }
        }

        if (!isValid) {
            field.classList.add('is-invalid-custom');
            const errSpan = document.createElement('span');
            errSpan.className = 'custom-error-msg';
            errSpan.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMsg}`;
            field.after(errSpan);
        }

        return isValid;
    }

    function validateServiceCheckboxes(form) {
        const serviceBoxes = form.querySelectorAll('input[name="services[]"]');
        if (serviceBoxes.length > 0) {
            let anyChecked = false;
            serviceBoxes.forEach(cb => { if (cb.checked) anyChecked = true; });

            const listBox = serviceBoxes[0].closest('.select-list-box');
            if (listBox) {
                listBox.classList.remove('is-invalid-custom');
                if (listBox.nextElementSibling && listBox.nextElementSibling.classList.contains('custom-error-msg')) {
                    listBox.nextElementSibling.remove();
                }

                if (!anyChecked) {
                    listBox.classList.add('is-invalid-custom');
                    const errSpan = document.createElement('span');
                    errSpan.className = 'custom-error-msg';
                    errSpan.innerHTML = `<i class="fas fa-exclamation-circle"></i> Please select at least one service.`;
                    listBox.after(errSpan);
                    return false;
                }
            }
        }
        return true;
    }

    function validateStaffCheckboxes(form) {
        const staffBoxes = form.querySelectorAll('input[name="staff[]"]');
        if (staffBoxes.length > 0) {
            let anyChecked = false;
            staffBoxes.forEach(cb => { if (cb.checked) anyChecked = true; });

            const listBox = staffBoxes[0].closest('.select-list-box');
            if (listBox) {
                listBox.classList.remove('is-invalid-custom');
                if (listBox.nextElementSibling && listBox.nextElementSibling.classList.contains('custom-error-msg')) {
                    listBox.nextElementSibling.remove();
                }

                if (!anyChecked) {
                    listBox.classList.add('is-invalid-custom');
                    const errSpan = document.createElement('span');
                    errSpan.className = 'custom-error-msg';
                    errSpan.innerHTML = `<i class="fas fa-exclamation-circle"></i> Please assign at least one staff member.`;
                    listBox.after(errSpan);
                    return false;
                }
            }
        }
        return true;
    }

    function validateFormAllControls(form) {
        let isFormValid = true;

        const controls = form.querySelectorAll('input, select, textarea');
        controls.forEach(control => {
            if (control.type === 'checkbox' || control.type === 'hidden' || control.type === 'submit') return;
            if (!validateCustomField(control)) {
                isFormValid = false;
            }
        });

        if (!validateServiceCheckboxes(form)) {
            isFormValid = false;
        }

        if (!validateStaffCheckboxes(form)) {
            isFormValid = false;
        }

        return isFormValid;
    }

    // Attach Real-Time Input/Change/Blur Validation on all Controls
    document.querySelectorAll('form').forEach(form => {
        if (form.method.toLowerCase() === 'get') return;

        const controls = form.querySelectorAll('input, select, textarea');
        controls.forEach(control => {
            if (control.type === 'checkbox' || control.type === 'hidden' || control.type === 'submit') return;

            ['input', 'change', 'blur'].forEach(evt => {
                control.addEventListener(evt, function() {
                    validateCustomField(control);
                });
            });
        });

        // Form Submit Handler via AJAX
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!validateFormAllControls(form)) {
                showExecutiveToast('Please fix the errors highlighted in red.', 'danger');
                return false;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            let origBtnHtml = '';
            if (submitBtn) {
                origBtnHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
            }

            const formData = new FormData(form);

            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnHtml;
                }

                if (data.status === 'success') {
                    showExecutiveToast(data.message, 'success');

                    const openModal = form.closest('.modal');
                    if (openModal && typeof bootstrap !== 'undefined') {
                        const modalInstance = bootstrap.Modal.getInstance(openModal);
                        if (modalInstance) modalInstance.hide();
                    }

                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    showExecutiveToast(data.message || 'An error occurred.', 'danger');
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnHtml;
                }
                form.submit();
            });
        });
    });

    // 7. Auto-dismiss flash alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.display = 'none';
            }
        }, 5000);
    });
});
