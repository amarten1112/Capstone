// Virginia Market Square — custom JavaScript
// Bootstrap handles most interactivity; add project-specific JS here.

document.addEventListener('DOMContentLoaded', () => {

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 7, TASK 7.2 — AJAX Cart Updates
    // Intercepts cart quantity and remove forms on customer/cart.php.
    // Scoped in an IIFE so it doesn't block other features on non-cart pages.
    // ═══════════════════════════════════════════════════════════════════════

    (function initCartAjax() {
        const cartContainer = document.getElementById('cart-items-container');
        if (!cartContainer) return;

        function showCartFlash(message, type = 'success') {
            const flash = document.getElementById('cart-flash');
            if (!flash) return;
            flash.className = `alert alert-${type}`;
            flash.textContent = message;
            flash.classList.remove('d-none');
            setTimeout(() => { flash.classList.add('d-none'); }, 3000);
        }

        function updateSidebar(subtotal, itemCount) {
            const labelEl    = document.getElementById('cart-summary-label');
            const subtotalEl = document.getElementById('cart-summary-subtotal');
            const totalEl    = document.getElementById('cart-summary-total');

            if (labelEl) {
                const s = itemCount !== 1 ? 's' : '';
                labelEl.textContent = `Subtotal (${itemCount} item${s})`;
            }
            if (subtotalEl) subtotalEl.textContent = `$${subtotal.toFixed(2)}`;
            if (totalEl)    totalEl.textContent    = `$${subtotal.toFixed(2)}`;

            const checkoutArea = document.getElementById('cart-checkout-area');
            if (!checkoutArea) return;

            if (subtotal > 0) {
                let link = checkoutArea.querySelector('a.btn-success');
                if (!link) {
                    checkoutArea.innerHTML = `
                        <a href="${checkoutArea.closest('[data-base-url]')?.dataset.baseUrl || ''}/customer/checkout.php"
                           class="btn btn-success btn-lg w-100">
                            Proceed to Checkout
                        </a>`;
                }
            }
        }

        function getBaseUrl() {
            const link = document.querySelector('a[href*="/products.php"]');
            if (link) return link.getAttribute('href').replace('/products.php', '');
            return '';
        }

        function checkEmptyCart() {
            const remaining = cartContainer.querySelectorAll('[data-cart-item]');
            if (remaining.length === 0) {
                const wrapper = document.getElementById('cart-wrapper');
                if (wrapper) {
                    wrapper.outerHTML = `
                        <div class="text-center py-5" id="cart-empty">
                            <h4 class="text-muted mb-3">Your cart is empty</h4>
                            <p class="text-muted">Browse our products and add something you love!</p>
                            <a href="${getBaseUrl()}/products.php" class="btn btn-success btn-lg">
                                Browse Products
                            </a>
                        </div>`;
                }
            }
        }

        function updateCartRow(cartId, newQty, lineTotal, stock) {
            const row = cartContainer.querySelector(`[data-cart-item="${cartId}"]`);
            if (!row) return;

            const qtyDisplay = row.querySelector('.cart-qty-display');
            if (qtyDisplay) {
                qtyDisplay.textContent = newQty;
                qtyDisplay.dataset.quantity = newQty;
            }

            const lineTotalEl = row.querySelector('.cart-line-total');
            if (lineTotalEl) {
                lineTotalEl.textContent = `$${lineTotal.toFixed(2)}`;
                lineTotalEl.style.transition = 'color 0.3s ease';
                lineTotalEl.style.color = 'var(--secondary-color)';
                setTimeout(() => { lineTotalEl.style.color = ''; }, 600);
            }

            const minusBtn = row.querySelector('.cart-btn-minus');
            if (minusBtn) {
                minusBtn.disabled = (newQty <= 1);
                minusBtn.value = Math.max(1, newQty - 1);
            }

            const plusBtn = row.querySelector('.cart-btn-plus');
            if (plusBtn) {
                plusBtn.disabled = (newQty >= stock);
                plusBtn.value = Math.min(stock, newQty + 1);
            }
        }

        function removeCartRow(cartId) {
            const row = cartContainer.querySelector(`[data-cart-item="${cartId}"]`);
            if (!row) return;

            row.style.transition = 'opacity 0.3s ease, max-height 0.4s ease, padding 0.4s ease';
            row.style.overflow = 'hidden';
            row.style.maxHeight = row.scrollHeight + 'px';
            row.offsetHeight;

            row.style.opacity = '0';
            row.style.maxHeight = '0';
            row.style.paddingTop = '0';
            row.style.paddingBottom = '0';

            setTimeout(() => {
                row.remove();
                const firstItem = cartContainer.querySelector('[data-cart-item]');
                if (firstItem) firstItem.classList.remove('border-top');
                checkEmptyCart();
            }, 400);
        }

        async function handleCartForm(form, clickedButton) {
            const formData = new FormData(form);
            if (clickedButton && clickedButton.name) {
                formData.set(clickedButton.name, clickedButton.value);
            }

            const row = form.closest('[data-cart-item]');
            const buttons = row ? row.querySelectorAll('button') : [];
            buttons.forEach(btn => btn.disabled = true);

            try {
                const response = await fetch(form.getAttribute('action'), {                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();

                if (data.success) {
                    if (data.action === 'remove') {
                        removeCartRow(data.cart_id);
                        showCartFlash(data.message, 'success');
                    } else if (data.action === 'update') {
                        updateCartRow(data.cart_id, data.quantity, data.line_total, data.stock);
                        showCartFlash(data.message, data.capped ? 'warning' : 'success');
                    }
                    updateSidebar(data.subtotal, data.item_count);
                } else {
                    showCartFlash(data.message || 'Something went wrong.', 'danger');
                    buttons.forEach(btn => btn.disabled = false);
                }
            } catch (err) {
                console.error('Cart AJAX error:', err);
                form.submit();
            }
        }

        cartContainer.addEventListener('click', (e) => {
            const button = e.target.closest('button[type="submit"]');
            if (!button) return;

            const form = button.closest('form');
            if (!form) return;

            if (!form.classList.contains('cart-qty-form') &&
                !form.classList.contains('cart-remove-form')) return;

            e.preventDefault();
            handleCartForm(form, button);
        });

    })(); // end cart AJAX


    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 7, TASK 7.3 — Mobile Menu & Interactive Elements
    // ═══════════════════════════════════════════════════════════════════════

    // ─── 1. Mobile navbar: auto-close on link click ─────────────────────
    // On mobile, the Bootstrap collapse menu stays open after tapping a
    // nav link, which feels broken. This closes it automatically.
    const navbarCollapse = document.getElementById('navbarNav');
    if (navbarCollapse) {
        navbarCollapse.addEventListener('click', (e) => {
            const link = e.target.closest('a.nav-link:not(.dropdown-toggle)');
            if (!link) return;

            // Only close if the menu is currently expanded (mobile view)
            if (navbarCollapse.classList.contains('show')) {
                const toggler = document.querySelector('.navbar-toggler');
                if (toggler) toggler.click();
            }
        });
    }

    // ─── 2. Back-to-top button ──────────────────────────────────────────
    // Creates a floating button that appears after scrolling 300px.
    // Smoothly scrolls back to top on click.
    (function initBackToTop() {
        const btn = document.createElement('button');
        btn.id = 'back-to-top';
        btn.setAttribute('aria-label', 'Back to top');
        btn.textContent = '\u2191'; // up arrow        
        document.body.appendChild(btn);

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    btn.classList.toggle('visible', window.scrollY > 300);
                    ticking = false;
                });
                ticking = true;
            }
        });

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();

    // ─── 3. Card hover lift effect ──────────────────────────────────────
    // Adds the card-hover-lift class to interactive cards site-wide.
    // The CSS for this class is in style.css.
    (function initCardHover() {
        const hoverCards = document.querySelectorAll(
            '.card.shadow-sm, .card.card-top-accent-green, .card.card-top-accent-earth, .vendor-card'
        );

        hoverCards.forEach(card => {
            // Don't add hover to cards inside forms or the cart list
            if (card.closest('form') || card.id === 'cart-items-container') return;
            card.classList.add('card-hover-lift');
        });
    })();

    // ─── 4. Smooth scroll for anchor links ──────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
            const targetId = anchor.getAttribute('href');
            if (targetId === '#') return;

            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ─── 5. Active nav-link highlighting ────────────────────────────────
    // Marks the current page's nav link as active. Bootstrap doesn't
    // do this automatically with separate PHP pages.
    (function highlightActiveNav() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;

            if (currentPath === href || currentPath.endsWith(href)) {
                link.classList.add('active');
            }
        });
    })();

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 7, TASK 7.4 — Image Handling & Optimization
    // ═══════════════════════════════════════════════════════════════════════

    // ─── 1. Lazy loading — add loading="lazy" to all images ─────────────
    // Browsers that support it will defer offscreen images automatically.
    // We add the attribute via JS so existing PHP files don't need editing.
    (function initLazyLoad() {
        document.querySelectorAll('img:not([loading])').forEach(img => {
            img.setAttribute('loading', 'lazy');
        });
    })();

    // ─── 2. Fade-in on image load ───────────────────────────────────────
    // Images start invisible and fade in once loaded. Gives a polished
    // feel especially as lazy-loaded images scroll into view.
    (function initImageFadeIn() {
        // Target product and vendor images — skip tiny icons and nav elements
        const images = document.querySelectorAll(
            '.card img, .product-detail-img, .vendor-detail-img, [data-cart-item] img'
        );

        images.forEach(img => {
            // If already loaded (cached), skip the animation
            if (img.complete && img.naturalWidth > 0) return;

            // Start hidden
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.4s ease';

            img.addEventListener('load', () => {
                img.style.opacity = '1';
            }, { once: true });
        });
    })();

    // ─── 3. Broken image fallback ───────────────────────────────────────
    // Replaces broken images with a clean placeholder instead of the
    // browser's ugly broken-image icon.
    (function initBrokenImageFallback() {
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function handleError() {
                // Prevent infinite loop if the fallback also fails
                this.removeEventListener('error', handleError);

                // Replace with a styled placeholder
                const wrapper = document.createElement('div');
                wrapper.style.cssText = `
                    width: ${this.width || 80}px;
                    height: ${this.height || 80}px;
                    background-color: #f0f4e8;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.375rem;
                    color: #6c757d;
                    font-size: 0.75rem;
                    text-align: center;
                    padding: 0.5rem;
                `;
                wrapper.textContent = 'Image not available';

                // Preserve the original dimensions from style attribute
                if (this.style.width) wrapper.style.width = this.style.width;
                if (this.style.height) wrapper.style.height = this.style.height;

                this.parentNode.replaceChild(wrapper, this);
            }, { once: true });
        });
    })();
}); // end DOMContentLoaded