/**
 * form-validation.js — Client-Side Form Validation
 * Virginia Market Square
 *
 * Phase 7, Task 7.1
 *
 * Provides real-time validation feedback on all site forms using
 * Bootstrap's is-valid / is-invalid classes and custom feedback divs.
 *
 * Forms covered:
 *   1. Login        (login.php)
 *   2. Register     (register.php)      — includes password strength meter
 *   3. Contact      (contact.php)
 *   4. Vendor Apply (vendor-apply.php)
 *   5. Checkout     (customer/checkout.php)
 *   6. Vendor Product Add/Edit (vendor-portal/products.php)
 *
 * How it works:
 *   - On DOMContentLoaded, detects which form is on the page by checking
 *     the form's action attribute or known field names.
 *   - Attaches blur + input listeners to required fields.
 *   - Shows inline error messages beneath each field.
 *   - Prevents form submission if any required fields fail validation.
 *   - Server-side validation (PHP) remains the authoritative safety net.
 *
 * No jQuery. No frameworks. Vanilla ES6+.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ─── Utility Functions ──────────────────────────────────────────────────

    /**
     * Show a validation error on a field.
     * Uses Bootstrap's is-invalid class + a .invalid-feedback div.
     */
    function showError(field, message) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');

        // Create or update feedback div
        let feedback = field.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.classList.add('invalid-feedback');
            field.parentNode.insertBefore(feedback, field.nextSibling);
        }
        feedback.textContent = message;
    }

    /**
     * Show a field as valid.
     * Uses Bootstrap's is-valid class.
     */
    function showValid(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');

        // Remove any existing error message
        const feedback = field.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = '';
        }
    }

    /**
     * Clear validation state (neither valid nor invalid).
     * Used on initial load so fields don't start green.
     */
    function clearState(field) {
        field.classList.remove('is-valid', 'is-invalid');
    }

    /**
     * Basic email format check.
     * Not exhaustive — server-side filter_var() is the real check.
     */
    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    /**
     * US ZIP code format (5 digits or 5+4).
     */
    function isValidZip(value) {
        return /^\d{5}(-\d{4})?$/.test(value);
    }

    /**
     * Phone number — loose check, allows common US formats.
     * Optional field, so empty is valid.
     */
    function isValidPhone(value) {
        if (value === '') return true; // phone is optional everywhere
        return /^[\d\s\-\(\)\+\.]{7,20}$/.test(value);
    }

    // ─── Password Strength ──────────────────────────────────────────────────

    /**
     * Evaluate password strength and return { score, label, color }.
     * Rules match the PHP-side hint: 8+ chars, uppercase, number, special char.
     */
    function getPasswordStrength(password) {
        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        const levels = [
            { label: 'Too short', color: '#dc3545' },   // 0
            { label: 'Weak',      color: '#dc3545' },   // 1
            { label: 'Fair',      color: '#ffc107' },   // 2
            { label: 'Good',      color: '#6ba547' },   // 3
            { label: 'Strong',    color: '#2d5016' },   // 4
            { label: 'Very strong', color: '#2d5016' },  // 5
        ];

        return { score, ...levels[score] };
    }

    /**
     * Create and insert a password strength meter below a password field.
     * Returns an update function that recalculates on each keystroke.
     */
    function createStrengthMeter(passwordField) {
        // Build the meter HTML
        const wrapper = document.createElement('div');
        wrapper.classList.add('password-strength-meter', 'mt-1');
        wrapper.innerHTML = `
            <div class="d-flex gap-1 mb-1">
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
            </div>
            <small class="strength-label text-muted"></small>
        `;

        // Insert after the field (but before any existing form-text hint)
        const formText = passwordField.parentNode.querySelector('.form-text');
        if (formText) {
            passwordField.parentNode.insertBefore(wrapper, formText);
        } else {
            passwordField.parentNode.appendChild(wrapper);
        }

        const bars  = wrapper.querySelectorAll('.strength-bar');
        const label = wrapper.querySelector('.strength-label');

        // Return the updater function
        return function update() {
            const strength = getPasswordStrength(passwordField.value);

            bars.forEach((bar, i) => {
                if (i < strength.score) {
                    bar.style.backgroundColor = strength.color;
                } else {
                    bar.style.backgroundColor = '#e9ecef';
                }
            });

            if (passwordField.value.length > 0) {
                label.textContent = strength.label;
                label.style.color = strength.color;
            } else {
                label.textContent = '';
            }
        };
    }


    // ─── Field Validator Builder ─────────────────────────────────────────────

    /**
     * Attach validation to a single field.
     *
     * @param {HTMLElement} field    - The input/select/textarea element
     * @param {Function}    validate - Returns error message string, or '' if valid
     * @param {string}      event    - Which event triggers validation ('blur' or 'input')
     */
    function attachValidator(field, validate, event = 'blur') {
        if (!field) return;

        // Validate on the specified event
        field.addEventListener(event, () => {
            const value = field.value.trim();
            const error = validate(value, field);
            if (error) {
                showError(field, error);
            } else if (value !== '' || field.hasAttribute('required')) {
                // Only show green check if the field has been touched and has content
                if (value !== '') {
                    showValid(field);
                } else {
                    clearState(field);
                }
            }
        });

        // Also validate on input for immediate feedback on fixing errors
        if (event === 'blur') {
            field.addEventListener('input', () => {
                // Only re-validate on input if the field was previously marked invalid
                if (field.classList.contains('is-invalid')) {
                    const value = field.value.trim();
                    const error = validate(value, field);
                    if (!error && value !== '') {
                        showValid(field);
                    } else if (!error) {
                        clearState(field);
                    }
                }
            });
        }
    }


    // ─── Form-Level Submit Handler ──────────────────────────────────────────

    /**
     * Attach a submit handler that runs all validators and prevents
     * submission if any fail. Also adds a brief spinner to the submit button.
     *
     * @param {HTMLFormElement} form
     * @param {Array}          validators - Array of { field, validate } objects
     */
    function attachSubmitHandler(form, validators) {
        form.addEventListener('submit', (e) => {
            let hasError = false;

            validators.forEach(({ field, validate }) => {
                if (!field) return;
                const value = field.value.trim();
                const error = validate(value, field);
                if (error) {
                    showError(field, error);
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();

                // Scroll to the first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                return;
            }

            // Show loading state on submit button to prevent double-clicks
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                const originalText = btn.innerHTML;
                btn.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Processing...
                `;
                // Re-enable after 5 seconds in case of network issues
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }, 5000);
            }
        });
    }


    // ═══════════════════════════════════════════════════════════════════════
    // FORM DETECTION & SETUP
    // Each form is identified by its action URL or unique field names.
    // ═══════════════════════════════════════════════════════════════════════

    const forms = document.querySelectorAll('form');

    forms.forEach((form) => {
        const action = form.getAttribute('action') || '';

        // ── 1. Login Form ──────────────────────────────────────────────────
        if (action.includes('login.php')) {
            const email    = form.querySelector('input[name="email"]');
            const password = form.querySelector('input[name="password"]');

            // Skip if these aren't login fields (the register page also
            // has an email field, but its action is register.php)
            if (!email || !password) return;

            const validators = [];

            if (email) {
                const v = (val) => {
                    if (!val) return 'Email is required.';
                    if (!isValidEmail(val)) return 'Please enter a valid email address.';
                    return '';
                };
                attachValidator(email, v);
                validators.push({ field: email, validate: v });
            }

            if (password) {
                const v = (val) => {
                    if (!val) return 'Password is required.';
                    return '';
                };
                attachValidator(password, v);
                validators.push({ field: password, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

        // ── 2. Register Form ───────────────────────────────────────────────
        if (action.includes('register.php')) {
            const fullName = form.querySelector('input[name="full_name"]');
            const email    = form.querySelector('input[name="email"]');
            const phone    = form.querySelector('input[name="phone"]');
            const password = form.querySelector('input[name="password"]');

            // Vendor-specific fields (may not exist if customer tab is active)
            const vendorName    = form.querySelector('input[name="vendor_name"]');
            const businessEmail = form.querySelector('input[name="business_email"]');

            const validators = [];

            if (fullName) {
                const v = (val) => {
                    if (!val) return 'Full name is required.';
                    if (val.length < 2) return 'Name must be at least 2 characters.';
                    return '';
                };
                attachValidator(fullName, v);
                validators.push({ field: fullName, validate: v });
            }

            if (email) {
                const v = (val) => {
                    if (!val) return 'Email is required.';
                    if (!isValidEmail(val)) return 'Please enter a valid email address.';
                    return '';
                };
                attachValidator(email, v);
                validators.push({ field: email, validate: v });
            }

            if (phone) {
                const v = (val) => {
                    if (!isValidPhone(val)) return 'Please enter a valid phone number.';
                    return '';
                };
                attachValidator(phone, v);
                validators.push({ field: phone, validate: v });
            }

            if (password) {
                // Password strength meter
                const updateMeter = createStrengthMeter(password);
                password.addEventListener('input', updateMeter);

                const v = (val) => {
                    if (!val) return 'Password is required.';
                    if (val.length < 8) return 'Password must be at least 8 characters.';
                    if (!/[A-Z]/.test(val)) return 'Include at least one uppercase letter.';
                    if (!/[0-9]/.test(val)) return 'Include at least one number.';
                    if (!/[^A-Za-z0-9]/.test(val)) return 'Include at least one special character (!@#$%...).';
                    return '';
                };
                attachValidator(password, v, 'input');
                validators.push({ field: password, validate: v });
            }

            // Vendor fields — only validate if they're visible on the page
            if (vendorName) {
                const v = (val) => {
                    // Only required if vendor tab is active
                    if (!vendorName.closest('form').querySelector('input[name="user_type"][value="vendor"]')) return '';
                    if (!val) return 'Business name is required.';
                    return '';
                };
                attachValidator(vendorName, v);
                validators.push({ field: vendorName, validate: v });
            }

            if (businessEmail) {
                const v = (val) => {
                    if (val && !isValidEmail(val)) return 'Please enter a valid email address.';
                    return '';
                };
                attachValidator(businessEmail, v);
                validators.push({ field: businessEmail, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

        // ── 3. Contact Form ────────────────────────────────────────────────
        if (action.includes('contact.php')) {
            const name    = form.querySelector('input[name="name"]');
            const email   = form.querySelector('input[name="email"]');
            const phone   = form.querySelector('input[name="phone"]');
            const message = form.querySelector('textarea[name="message"]');

            const validators = [];

            if (name) {
                const v = (val) => {
                    if (!val) return 'Your name is required.';
                    return '';
                };
                attachValidator(name, v);
                validators.push({ field: name, validate: v });
            }

            if (email) {
                const v = (val) => {
                    if (!val) return 'Email is required.';
                    if (!isValidEmail(val)) return 'Please enter a valid email address.';
                    return '';
                };
                attachValidator(email, v);
                validators.push({ field: email, validate: v });
            }

            if (phone) {
                const v = (val) => {
                    if (!isValidPhone(val)) return 'Please enter a valid phone number.';
                    return '';
                };
                attachValidator(phone, v);
                validators.push({ field: phone, validate: v });
            }

            if (message) {
                const v = (val) => {
                    if (!val) return 'Please enter a message.';
                    if (val.length < 10) return 'Message should be at least 10 characters.';
                    return '';
                };
                attachValidator(message, v);
                validators.push({ field: message, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

        // ── 4. Vendor Application Form ─────────────────────────────────────
        if (action.includes('vendor-apply.php')) {
            const appName  = form.querySelector('input[name="applicant_name"]');
            const appEmail = form.querySelector('input[name="applicant_email"]');
            const appPhone = form.querySelector('input[name="applicant_phone"]');
            const bizName  = form.querySelector('input[name="business_name"]');
            const miles    = form.querySelector('input[name="miles_from_virginia"]');

            const validators = [];

            if (appName) {
                const v = (val) => {
                    if (!val) return 'Your name is required.';
                    return '';
                };
                attachValidator(appName, v);
                validators.push({ field: appName, validate: v });
            }

            if (appEmail) {
                const v = (val) => {
                    if (!val) return 'Email is required.';
                    if (!isValidEmail(val)) return 'Please enter a valid email address.';
                    return '';
                };
                attachValidator(appEmail, v);
                validators.push({ field: appEmail, validate: v });
            }

            if (appPhone) {
                const v = (val) => {
                    if (!isValidPhone(val)) return 'Please enter a valid phone number.';
                    return '';
                };
                attachValidator(appPhone, v);
                validators.push({ field: appPhone, validate: v });
            }

            if (bizName) {
                const v = (val) => {
                    if (!val) return 'Business name is required.';
                    return '';
                };
                attachValidator(bizName, v);
                validators.push({ field: bizName, validate: v });
            }

            if (miles) {
                const v = (val) => {
                    if (val === '') return ''; // optional field
                    const num = parseFloat(val);
                    if (isNaN(num) || num < 0) return 'Miles must be 0 or greater.';
                    if (num > 50) return 'Must be within 50 miles of Virginia, MN.';
                    return '';
                };
                attachValidator(miles, v);
                validators.push({ field: miles, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

        // ── 5. Checkout Shipping Form ──────────────────────────────────────
        if (action.includes('checkout.php') && form.querySelector('input[name="ship_name"]')) {
            const shipName  = form.querySelector('input[name="ship_name"]');
            const shipAddr1 = form.querySelector('input[name="ship_address1"]');
            const shipCity  = form.querySelector('input[name="ship_city"]');
            const shipState = form.querySelector('input[name="ship_state"]');
            const shipZip   = form.querySelector('input[name="ship_zip"]');

            const validators = [];

            if (shipName) {
                const v = (val) => {
                    if (!val) return 'Full name is required.';
                    return '';
                };
                attachValidator(shipName, v);
                validators.push({ field: shipName, validate: v });
            }

            if (shipAddr1) {
                const v = (val) => {
                    if (!val) return 'Street address is required.';
                    return '';
                };
                attachValidator(shipAddr1, v);
                validators.push({ field: shipAddr1, validate: v });
            }

            if (shipCity) {
                const v = (val) => {
                    if (!val) return 'City is required.';
                    return '';
                };
                attachValidator(shipCity, v);
                validators.push({ field: shipCity, validate: v });
            }

            if (shipState) {
                const v = (val) => {
                    if (!val) return 'State is required.';
                    return '';
                };
                attachValidator(shipState, v);
                validators.push({ field: shipState, validate: v });
            }

            if (shipZip) {
                const v = (val) => {
                    if (!val) return 'ZIP code is required.';
                    if (!isValidZip(val)) return 'Enter a valid ZIP (e.g. 55792 or 55792-1234).';
                    return '';
                };
                attachValidator(shipZip, v);
                validators.push({ field: shipZip, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

        // ── 6. Vendor Product Add/Edit Form ────────────────────────────────
        if (action.includes('products.php') && form.querySelector('input[name="product_name"]')) {
            const prodName   = form.querySelector('input[name="product_name"]');
            const categoryId = form.querySelector('select[name="category_id"]');
            const price      = form.querySelector('input[name="price"]');
            const stock      = form.querySelector('input[name="stock_quantity"]');

            const validators = [];

            if (prodName) {
                const v = (val) => {
                    if (!val) return 'Product name is required.';
                    return '';
                };
                attachValidator(prodName, v);
                validators.push({ field: prodName, validate: v });
            }

            if (categoryId) {
                const v = (val) => {
                    if (!val || val === '' || val === '0') return 'Please select a category.';
                    return '';
                };
                attachValidator(categoryId, v, 'change');
                // Also listen for blur on selects
                categoryId.addEventListener('blur', () => {
                    const error = v(categoryId.value);
                    if (error) showError(categoryId, error);
                    else if (categoryId.value) showValid(categoryId);
                });
                validators.push({ field: categoryId, validate: v });
            }

            if (price) {
                const v = (val) => {
                    if (!val) return 'Price is required.';
                    const num = parseFloat(val);
                    if (isNaN(num) || num <= 0) return 'Price must be greater than $0.00.';
                    return '';
                };
                attachValidator(price, v);
                validators.push({ field: price, validate: v });
            }

            if (stock) {
                const v = (val) => {
                    if (val === '') return 'Stock quantity is required.';
                    const num = parseInt(val, 10);
                    if (isNaN(num) || num < 0) return 'Stock cannot be negative.';
                    return '';
                };
                attachValidator(stock, v);
                validators.push({ field: stock, validate: v });
            }

            attachSubmitHandler(form, validators);
        }

    }); // end forms.forEach

}); // end DOMContentLoaded