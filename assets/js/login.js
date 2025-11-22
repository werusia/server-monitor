/**
 * Login page accessibility and UX enhancements.
 * Handles focus management, keyboard navigation, and form validation feedback.
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const passwordInput = document.getElementById('login_form_password');
        const loginForm = document.getElementById('login-form');
        const submitButton = document.getElementById('login-submit-button');
        const errorAlert = document.getElementById('login-error');

        if (!passwordInput || !loginForm) {
            return;
        }

        // Set focus on password input when page loads (if no error)
        if (!errorAlert) {
            setTimeout(() => {
                passwordInput.focus();
            }, 100);
        } else {
            // If there's an error, focus on the error alert first for screen readers
            errorAlert.focus();
            // Then move focus to password input after a short delay
            setTimeout(() => {
                passwordInput.focus();
            }, 300);
        }

        // Handle form submission - prevent double submission
        let isSubmitting = false;
        loginForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Basic client-side validation
            if (!passwordInput.value.trim()) {
                e.preventDefault();
                passwordInput.classList.add('is-invalid');
                passwordInput.focus();
                
                // Create or update error message
                let feedback = passwordInput.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.setAttribute('role', 'alert');
                    passwordInput.parentElement.appendChild(feedback);
                }
                feedback.textContent = 'Hasło jest wymagane.';
                
                return false;
            }

            // Disable submit button to prevent double submission
            isSubmitting = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Logowanie...';
            }
        });

        // Remove invalid state when user starts typing
        passwordInput.addEventListener('input', function() {
            if (this.classList.contains('is-invalid') && this.value.trim()) {
                this.classList.remove('is-invalid');
                const feedback = this.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.remove();
                }
            }
        });

        // Handle Enter key in password field (already handled by form, but ensure focus management)
        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !this.value.trim()) {
                e.preventDefault();
                this.classList.add('is-invalid');
                this.focus();
            }
        });

        // Handle alert dismissal - return focus to form
        const alertCloseButtons = document.querySelectorAll('.alert .btn-close');
        alertCloseButtons.forEach(button => {
            button.addEventListener('click', function() {
                setTimeout(() => {
                    passwordInput.focus();
                }, 100);
            });
        });

        // Handle Escape key to close alerts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const visibleAlert = document.querySelector('.alert:not(.d-none)');
                if (visibleAlert) {
                    const closeButton = visibleAlert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }
            }
        });
    }
})();

