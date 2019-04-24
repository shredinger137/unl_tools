'use strict';

define(['plugins/headroom', 'plugins/body-scroll-lock', 'mustard/inert-polyfill'], function (Headroom, bodyScrollLock) {

    var disableBodyScroll = bodyScrollLock.disableBodyScroll;
    var enableBodyScroll = bodyScrollLock.enableBodyScroll;

    var initialized = false;

    var Plugin = {
        initialize: function initialize() {
            if (initialized) {
                return;
            }

            // grab an element
            var mobileActions = document.querySelectorAll('.hrjs');
            var main = document.querySelector('main');
            var footer = document.getElementById('dcf-footer');

            // construct an instance of Headroom, passing the element
            for (var i = 0; i < mobileActions.length; i++) {
                var headroom = new Headroom(mobileActions[i], {
                    "tolerance": {
                        up: 5,
                        down: 5
                    }
                });
                headroom.init();
            }

            var toggleButtons = document.querySelectorAll('.dcf-nav-toggle-btn-menu');
            var toggleIconOpen = document.getElementById('dcf-nav-toggle-icon-open-menu');
            var toggleIconClose = document.getElementById('dcf-nav-toggle-icon-close-menu');
            var toggleLabel = document.querySelector('.dcf-nav-toggle-label-menu');
            var mobileNav = document.getElementById('dcf-navigation');
            var modalParent = document.querySelector('.dcf-nav-menu.dcf-modal-parent');
            var mobileNavMenu = document.getElementById('dcf-nav-menu-child');
            var body = document.querySelector('body');
            var firstLink = mobileNav.querySelector('a');
            var closeSearchEvent = new CustomEvent('closeSearch');
            var closeIDMOptionsEvent = new CustomEvent('closeDropDownWidget', { detail: { type: 'idm-logged-in' } });

            // We need to keep track of the toggle button that activated the menu so that we can return focus to it when the menu is closed
            var activeToggleButton = null;

            function onKeyUp(e) {
                if (e.keyCode === 27) {
                    closeModal();
                }
            }

            function openModal() {
                if (window.matchMedia("(max-width: 56.12em)").matches) {
                    main.setAttribute('inert', '');
                    footer.setAttribute('inert', '');
                    disableBodyScroll(mobileNavMenu);
                }
                modalParent.classList.add('dcf-modal-open');
                for (var i = 0; i < toggleButtons.length; ++i) {
                    toggleButtons[i].setAttribute('aria-expanded', 'true');
                    toggleButtons[i].setAttribute('aria-label', 'close menu');
                }
                toggleIconOpen.classList.add('dcf-d-none');
                toggleIconClose.classList.remove('dcf-d-none');
                toggleLabel.textContent = 'Close';

                // Hide other mobile toggles
                document.dispatchEvent(closeSearchEvent);
                document.dispatchEvent(closeIDMOptionsEvent);

                firstLink.focus();
                document.addEventListener('keyup', onKeyUp);
            }

            function closeModal() {
                if (window.matchMedia("(max-width: 56.12em)").matches) {
                    main.removeAttribute('inert');
                    footer.removeAttribute('inert');
                }
                modalParent.classList.remove('dcf-modal-open');
                for (var i = 0; i < toggleButtons.length; ++i) {
                    toggleButtons[i].setAttribute('aria-expanded', 'false');
                    toggleButtons[i].setAttribute('aria-label', 'open menu');
                }
                toggleIconOpen.classList.remove('dcf-d-none');
                toggleIconClose.classList.add('dcf-d-none');
                toggleLabel.textContent = 'Menu';
                activeToggleButton.focus();
                document.removeEventListener('keyup', onKeyUp);

                // Allow body scroll when navigation is closed
                enableBodyScroll(mobileNavMenu);
            }

            // add an event listener for closeSearchEvent
            document.addEventListener('closeNavigation', function (e) {
                if (modalParent.classList.contains('dcf-modal-open')) {
                    closeModal();
                }
            });

            // add an event listener for resize
            window.addEventListener('resize', function (e) {
                if (window.matchMedia("(max-width: 56.12em)").matches && modalParent.classList.contains('dcf-modal-open')) {
                    main.setAttribute('inert', '');
                    footer.setAttribute('inert', '');
                    disableBodyScroll(mobileNavMenu);
                } else {
                    main.removeAttribute('inert');
                    footer.removeAttribute('inert');
                    enableBodyScroll(mobileNavMenu);
                }
            });

            var toggleButtonOnClick = function toggleButtonOnClick() {
                activeToggleButton = this;
                if (modalParent.classList.contains('dcf-modal-open')) {
                    closeModal();
                } else {
                    openModal();
                }
            };

            for (var _i = 0; _i < toggleButtons.length; _i++) {
                toggleButtons[_i].addEventListener('click', toggleButtonOnClick);
            }

            var primaryNavLi = document.querySelectorAll('.dcf-nav-menu-child>ul>li');
            var allPrimaryNavLi = Array.from(primaryNavLi);
            // Find any child <ul> in local navigation <li>
            var hasChild = allPrimaryNavLi.find(function (el) {
                return el.querySelector('ul');
            });
            var desktopBtn = document.getElementById('dcf-menu-toggle');
            // Show "desktop" menu toggle button if navigation contains children
            hasChild && desktopBtn.removeAttribute('hidden');
            hasChild && desktopBtn.setAttribute('aria-expanded', 'false');
            hasChild && desktopBtn.setAttribute('aria-label', 'open menu');
        }
    };

    return Plugin;
});
