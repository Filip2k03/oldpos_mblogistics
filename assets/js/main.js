 // assets/js/main.js - Embedded here for this single script update.
    // In a real application, this would be in assets/js/main.js
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle (assuming these elements exist globally in your header)
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // --- Register Page Logic (only runs if elements exist) ---
        function toggleRegionField() {
            const userTypeSelect = document.getElementById('user_type');
            const regionField = document.getElementById('region-field');
            const regionSelect = document.getElementById('region_id');

            if (!userTypeSelect || !regionField || !regionSelect) {
                return; // Exit if elements not found (e.g., not on register page)
            }

            const userType = userTypeSelect.value;
            regionSelect.value = ""; // Reset region selection
            regionSelect.disabled = false; // Enable by default

            if (userType === 'Myanmar') {
                regionField.classList.remove('hidden');
                for (let i = 0; i < regionSelect.options.length; i++) {
                    if (regionSelect.options[i].text.toLowerCase() === 'myanmar') {
                        regionSelect.value = regionSelect.options[i].value;
                        regionSelect.disabled = true;
                        break;
                    }
                }
            } else if (userType === 'Malay') {
                regionField.classList.remove('hidden');
                for (let i = 0; i < regionSelect.options.length; i++) {
                    if (regionSelect.options[i].text.toLowerCase() === 'malaysia') {
                        regionSelect.value = regionSelect.options[i].value;
                        regionSelect.disabled = true;
                        break;
                    }
                }
            } else {
                regionField.classList.add('hidden');
                regionSelect.disabled = false;
            }
        }

        if (document.getElementById('user_type')) {
            document.getElementById('user_type').addEventListener('change', toggleRegionField);
            toggleRegionField();
        }

    });