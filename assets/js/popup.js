/**
 * Boost frontend popup.
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var popup = document.querySelector('[data-boost-popup]');
        if (!popup) {
            return;
        }

        var storageKey = popup.getAttribute('data-storage-key') || 'boost_popup_dismissed';
        // The default dismissal duration is 30 days; admins can change it in plugin settings.
        var storageDays = parseInt(popup.getAttribute('data-storage-days'), 10) || 30;
        var closeButtons = popup.querySelectorAll('.boost-popup__close');
        var secondaryButton = popup.querySelector('.boost-popup__button--secondary');
        var primaryButton = popup.querySelector('.boost-popup__button--primary');
        var card = popup.querySelector('.boost-popup__card');

        function getStoredChoice() {
            try {
                var stored = window.localStorage.getItem(storageKey);
                if (!stored) {
                    return false;
                }

                var data = JSON.parse(stored);
                if (data.expiresAt && Date.now() < data.expiresAt) {
                    return true;
                }

                window.localStorage.removeItem(storageKey);
                return false;
            } catch (error) {
                return false;
            }
        }

        function rememberChoice() {
            try {
                var duration = storageDays * 24 * 60 * 60 * 1000;
                window.localStorage.setItem(storageKey, JSON.stringify({
                    dismissedAt: Date.now(),
                    expiresAt: Date.now() + duration
                }));
            } catch (error) {}
        }

        function showPopup() {
            popup.hidden = false;
            popup.setAttribute('aria-hidden', 'false');
            window.requestAnimationFrame(function() {
                popup.classList.add('is-visible');
                var firstButton = popup.querySelector('button, a, input');
                if (firstButton) {
                    firstButton.focus({ preventScroll: true });
                }
            });
        }

        function closePopup(remember) {
            if (remember) {
                rememberChoice();
            }

            popup.classList.remove('is-visible');
            popup.setAttribute('aria-hidden', 'true');
            window.setTimeout(function() {
                popup.hidden = true;
            }, 160);
        }

        if (getStoredChoice()) {
            return;
        }

        showPopup();

        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                closePopup(false);
            });
        });

        if (secondaryButton) {
            secondaryButton.addEventListener('click', function() {
                closePopup(true);
            });
        }

        if (primaryButton) {
            primaryButton.addEventListener('click', function(event) {
                rememberChoice();

                if (primaryButton.tagName.toLowerCase() === 'button') {
                    event.preventDefault();
                    closePopup(false);
                }
            });
        }

        popup.addEventListener('click', function(event) {
            if (card && !card.contains(event.target)) {
                closePopup(false);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !popup.hidden) {
                closePopup(false);
            }
        });
    });
})();
