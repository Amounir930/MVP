(function () {
    // 1. Detect backend URL from the script source itself
    const script = document.currentScript || document.querySelector('script[src*="widget-loader.js"]');
    const backendUrl = script ? new URL(script.src).origin : window.location.origin;

    // 2. Identify the Product ID from Salla page context
    function getSallaProductId() {
        // Method A: Check global window.salla config
        if (window.salla && window.salla.config && window.salla.config.product_id) {
            return window.salla.config.product_id;
        }
        
        // Method B: Check salla-product-detail custom element attributes
        const productElement = document.querySelector('salla-product-detail');
        if (productElement && productElement.getAttribute('product-id')) {
            return productElement.getAttribute('product-id');
        }

        // Method C: Check meta tags
        const metaProductId = document.querySelector('meta[property="og:product:id"]') || 
                              document.querySelector('meta[property="product:id"]') ||
                              document.querySelector('meta[name="product-id"]');
        if (metaProductId && metaProductId.getAttribute('content')) {
            return metaProductId.getAttribute('content');
        }

        // Method D: Parse from product page URL (e.g. /p12345)
        const urlMatch = window.location.pathname.match(/\/p(\d+)/);
        if (urlMatch && urlMatch[1]) {
            return urlMatch[1];
        }

        return null;
    }

    // 3. Find the location to insert the widget
    function getInsertionTarget() {
        // A. Custom widget container set by merchant
        const customContainer = document.getElementById('conversion-trust-reviews-widget');
        if (customContainer) return { element: customContainer, position: 'inside' };

        // B. Look for related products slider/list custom elements to insert BEFORE them (places widget nicely above them)
        const beforeTargets = [
            'salla-products-slider',
            'salla-products-list',
            '.related-products',
            '#related-products',
            'div.related-products-slider',
            'salla-comments',
            '#comments'
        ];
        for (const selector of beforeTargets) {
            const el = document.querySelector(selector);
            if (el) {
                // Find the closest section/container wrapper to place it ABOVE the heading/title
                const wrapper = el.closest('section') || 
                                el.closest('div[class*="related"]') || 
                                el.closest('.related-products') || 
                                el.parentElement || 
                                el;
                return { element: wrapper, position: 'beforebegin' };
            }
        }

        // C. Look for standard Salla product elements to insert after them
        const sallaDetail = document.querySelector('salla-product-detail');
        if (sallaDetail) return { element: sallaDetail, position: 'afterend' };

        // D. Search for "Add to Cart" button (إضافة للسلة) and insert after its main card/section
        let addBtn = null;
        const allButtons = document.querySelectorAll('button, a, div, span');
        for (const btn of allButtons) {
            const txt = (btn.innerText || btn.textContent || '').trim();
            if (txt.includes('إضافة للسلة') || txt.includes('اضافة للسلة') || txt.includes('أضف للسلة')) {
                addBtn = btn;
                break;
            }
        }
        if (addBtn) {
            const cardWrapper = addBtn.closest('.product-single') || 
                                addBtn.closest('.product-container') ||
                                addBtn.closest('.product-detail') ||
                                addBtn.closest('section') ||
                                addBtn.parentElement?.parentElement?.parentElement;
            if (cardWrapper && cardWrapper !== document.body) {
                return { element: cardWrapper, position: 'afterend' };
            }
        }

        // E. Fallback: common selectors
        const targets = [
            'div.product-single',
            'div.product-details',
            'section.product-detail',
            'div.salla-product-detail-container',
            'main.main-content'
        ];

        for (const selector of targets) {
            const el = document.querySelector(selector);
            if (el) return { element: el, position: 'afterend' };
        }

        // F. Fallback: Append to body
        return { element: document.body, position: 'beforeend' };
    }

    // 4. Initialize Widget Loader
    const productId = getSallaProductId();
    if (!productId) {
        console.warn('[ConversionTrust] Salla Product ID not detected on this page.');
        return;
    }

    console.info(`[ConversionTrust] Initializing widget for Product ID: ${productId} using backend: ${backendUrl}`);

    // Fetch review data
    fetch(`${backendUrl}/api/v1/widget/data?product_id=${productId}`)
        .then(response => {
            if (!response.ok) throw new Error('Failed to fetch widget reviews data');
            return response.json();
        })
        .then(data => {
            if (!data.reviews || data.reviews.length === 0) {
                console.info('[ConversionTrust] No approved reviews found for this product. Widget will not be displayed.');
                return;
            }

            // Wait for target elements to load in DOM before rendering to avoid race conditions
            const startTime = Date.now();
            function checkDOMAndRender() {
                const targetSelectors = [
                    'salla-products-slider',
                    'salla-products-list',
                    'salla-add-product-button',
                    '.related-products',
                    'div.product-single'
                ];

                let hasElement = false;
                for (const selector of targetSelectors) {
                    if (document.querySelector(selector)) {
                        hasElement = true;
                        break;
                    }
                }

                if (hasElement || (Date.now() - startTime > 4000)) {
                    renderWidget(data);
                    injectStructuredData(data);
                } else {
                    setTimeout(checkDOMAndRender, 50);
                }
            }

            checkDOMAndRender();
        })
        .catch(err => {
            console.error('[ConversionTrust] Error loading product reviews widget:', err);
        });

    // 5. Render the custom reviews widget inside Shadow DOM
    function renderWidget(data) {
        // Hide Salla's native comments section to avoid duplicate widgets
        const hideNativeStyle = document.createElement('style');
        hideNativeStyle.id = 'ct-hide-native-comments';
        hideNativeStyle.textContent = 'salla-comments, #comments, .salla-comments-wrapper, .comments-container { display: none !important; }';
        document.head.appendChild(hideNativeStyle);

        const target = getInsertionTarget();
        if (!target || !target.element) return;

        // Create widget element container
        const widgetWrapper = document.createElement('div');
        widgetWrapper.id = 'conversion-trust-widget-root';
        widgetWrapper.style.margin = '40px auto';
        widgetWrapper.style.maxWidth = '1200px';
        widgetWrapper.style.width = '100%';
        widgetWrapper.style.padding = '0 15px';
        widgetWrapper.style.boxSizing = 'border-box';

        // Insert into target position
        if (target.position === 'inside') {
            target.element.innerHTML = '';
            target.element.appendChild(widgetWrapper);
        } else if (target.position === 'afterend') {
            target.element.parentNode.insertBefore(widgetWrapper, target.element.nextSibling);
        } else if (target.position === 'beforebegin') {
            target.element.parentNode.insertBefore(widgetWrapper, target.element);
        } else {
            target.element.appendChild(widgetWrapper);
        }

        // Attach Shadow DOM for CSS isolation
        const shadow = widgetWrapper.attachShadow({ mode: 'open' });

        // Add font & icons stylesheets
        const fontLink = document.createElement('link');
        fontLink.rel = 'stylesheet';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap';
        shadow.appendChild(fontLink);

        // Render template and styles
        const average = data.rating_stats.average || 0;
        const totalCount = data.rating_stats.count || 0;

        // Construct stars HTML
        function renderStars(rating, size = '16px') {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += `<span style="color: #f59e0b; font-size: ${size}; margin: 0 1px;">★</span>`;
                } else {
                    stars += `<span style="color: #e5e7eb; font-size: ${size}; margin: 0 1px;">★</span>`;
                }
            }
            return stars;
        }

        // Render reviews HTML
        let reviewsHtml = '';
        data.reviews.forEach(review => {
            const dateStr = new Date(review.created_at).toLocaleDateString('ar-SA', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Media attachment HTML
            let mediaHtml = '';
            if (review.media_url) {
                const absoluteMediaUrl = review.media_url.startsWith('/') 
                    ? `${backendUrl}${review.media_url}` 
                    : review.media_url;

                if (review.media_type === 'image' || review.media_url.match(/\.(jpeg|jpg|gif|png)$/i)) {
                    mediaHtml = `
                        <div class="ct-review-media">
                            <a href="${absoluteMediaUrl}" target="_blank">
                                <img src="${absoluteMediaUrl}" alt="Review attachment" class="ct-media-img" />
                            </a>
                        </div>
                    `;
                } else if (review.media_type === 'video' || review.media_url.match(/\.(mp4|webm|ogg|mov)$/i)) {
                    mediaHtml = `
                        <div class="ct-review-media">
                            <video controls class="ct-media-video">
                                <source src="${absoluteMediaUrl}" />
                            </video>
                        </div>
                    `;
                }
            }

            // Custom questions answers
            let answersHtml = '';
            if (review.answers && review.answers.length > 0) {
                let answersItems = '';
                review.answers.forEach(ans => {
                    if (ans.response && ans.type !== 'media' && !ans.response.startsWith('/uploads/') && !ans.response.match(/\.(jpeg|jpg|gif|png|mp4|webm|ogg|mov)$/i)) {
                        answersItems += `
                            <div class="ct-qa-item">
                                <div class="ct-qa-q">${ans.text}</div>
                                <div class="ct-qa-a">${ans.response}</div>
                            </div>
                        `;
                    }
                });

                if (answersItems) {
                    const ansCount = review.answers.filter(a => a.response && a.type !== 'media' && !a.response.startsWith('/uploads/') && !a.response.match(/\.(jpeg|jpg|gif|png|mp4|webm|ogg|mov)$/i)).length;
                    answersHtml = `
                        <div class="ct-qa-container">
                            <details class="ct-qa-details">
                                <summary class="ct-qa-summary">
                                    <span>إجابات إضافية من العميل (${ansCount})</span>
                                    <svg class="ct-qa-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                                </summary>
                                <div class="ct-qa-list">${answersItems}</div>
                            </details>
                        </div>
                    `;
                }
            }

            // Customer avatar initial or image
            const initial = review.customer && review.customer.name ? review.customer.name.charAt(0) : 'ع';
            const customerName = review.customer && review.customer.name ? review.customer.name : 'عميل متجر';
            let avatarHtml = `<div class="ct-reviewer-avatar">${initial}</div>`;
            if (review.customer && review.customer.avatar_url) {
                avatarHtml = `<img class="ct-reviewer-avatar-img" src="${review.customer.avatar_url}" alt="${customerName}" />`;
            }

            // Merchant reply HTML
            let replyHtml = '';
            if (review.reply) {
                const replyDateStr = new Date(review.replied_at || review.created_at).toLocaleDateString('ar-SA', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                replyHtml = `
                    <div class="ct-merchant-reply">
                        <div class="ct-reply-header">
                            <span class="ct-reply-badge">الرد الرسمي للمتجر</span>
                            <span class="ct-reply-date">${replyDateStr}</span>
                        </div>
                        <p class="ct-reply-text">${review.reply}</p>
                    </div>
                `;
            }

            reviewsHtml += `
                <div class="ct-review-card">
                    <div class="ct-review-header">
                        <div class="ct-reviewer-info">
                            ${avatarHtml}
                            <div>
                                <div class="ct-reviewer-name">
                                    ${customerName}
                                    <span class="ct-verified-badge" title="مشتري موثق">
                                        <svg class="ct-shield-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        مشتري موثق
                                    </span>
                                </div>
                                <div class="ct-review-date">${dateStr}</div>
                            </div>
                        </div>
                        <div class="ct-review-stars">
                            ${renderStars(review.rating)}
                        </div>
                    </div>
                    <div class="ct-review-body">
                        ${review.comment ? `<p class="ct-review-text">${review.comment}</p>` : ''}
                        ${mediaHtml}
                        ${answersHtml}
                        ${replyHtml}
                    </div>
                </div>
            `;
        });

        // Widget CSS Styling (isolated inside shadow DOM)
        const styles = `
            :host {
                display: block;
                font-family: 'Cairo', 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                color: #1f2937;
                direction: rtl;
                text-align: right;
                box-sizing: border-box;
            }
            .ct-widget-container {
                background: #ffffff;
                border: 1px solid #f3f4f6;
                border-radius: 24px;
                padding: 30px;
                box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04);
            }
            .ct-widget-header {
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid #f3f4f6;
                padding-bottom: 24px;
                margin-bottom: 30px;
                gap: 20px;
            }
            @media (max-width: 768px) {
                .ct-widget-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
            .ct-header-left {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .ct-avg-number {
                font-size: 48px;
                font-weight: 700;
                color: #111827;
                line-height: 1;
            }
            .ct-avg-details {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .ct-stars-row {
                display: flex;
                align-items: center;
            }
            .ct-reviews-count {
                font-size: 14px;
                color: #6b7280;
                font-weight: 500;
            }
            .ct-header-title {
                font-size: 20px;
                font-weight: 700;
                color: #111827;
            }
            .ct-reviews-list {
                display: flex;
                flex-direction: column;
                gap: 24px;
            }
            .ct-review-card {
                background: #fafafa;
                border: 1px solid #f3f4f6;
                border-radius: 20px;
                padding: 24px;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .ct-review-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.02);
            }
            .ct-review-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                margin-bottom: 16px;
                gap: 16px;
            }
            .ct-reviewer-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .ct-reviewer-avatar {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: linear-gradient(135deg, #10b981, #14b8a6);
                color: #ffffff;
                font-weight: 700;
                font-size: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.2);
            }
            .ct-reviewer-avatar-img {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                object-fit: cover;
                border: 1px solid #f3f4f6;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            .ct-reviewer-name {
                font-size: 15px;
                font-weight: 600;
                color: #111827;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .ct-verified-badge {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                font-size: 11px;
                font-weight: 700;
                color: #047857;
                background: #ecfdf5;
                padding: 2px 8px;
                border-radius: 9999px;
            }
            .ct-shield-icon {
                width: 11px;
                height: 11px;
            }
            .ct-review-date {
                font-size: 12px;
                color: #9ca3af;
                margin-top: 2px;
            }
            .ct-review-stars {
                display: flex;
                align-items: center;
            }
            .ct-review-body {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .ct-review-text {
                font-size: 14px;
                line-height: 1.6;
                color: #374151;
                margin: 0;
                white-space: pre-wrap;
            }
            .ct-review-media {
                margin-top: 8px;
                display: flex;
            }
            .ct-media-img {
                max-height: 150px;
                max-width: 100%;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                object-fit: cover;
                transition: transform 0.2s ease;
                cursor: zoom-in;
            }
            .ct-media-img:hover {
                transform: scale(1.02);
            }
            .ct-media-video {
                max-height: 150px;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                outline: none;
            }
            .ct-qa-container {
                background: #ffffff;
                border: 1px solid #f3f4f6;
                border-radius: 12px;
                padding: 10px 14px;
                margin-top: 10px;
            }
            .ct-qa-details {
                width: 100%;
            }
            .ct-qa-summary {
                list-style: none;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 12px;
                font-weight: 700;
                color: #4b5563;
                cursor: pointer;
                user-select: none;
                outline: none;
            }
            .ct-qa-summary::-webkit-details-marker {
                display: none;
            }
            .ct-qa-arrow {
                transition: transform 0.2s ease;
            }
            .ct-qa-details[open] .ct-qa-arrow {
                transform: rotate(180deg);
            }
            .ct-qa-list {
                margin-top: 12px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                border-right: 2px solid #10b981;
                padding-right: 12px;
            }
            .ct-qa-item {
                font-size: 12px;
            }
            .ct-qa-q {
                font-weight: 700;
                color: #4b5563;
                margin-bottom: 4px;
            }
            .ct-qa-a {
                color: #1f2937;
                background: #f9fafb;
                padding: 8px;
                border-radius: 8px;
                line-height: 1.5;
            }
            .ct-merchant-reply {
                background: #f3f4f6;
                border-radius: 12px;
                padding: 14px 16px;
                margin-top: 14px;
                border-right: 3px solid #4f46e5;
            }
            .ct-reply-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
            }
            .ct-reply-badge {
                font-size: 11px;
                font-weight: 700;
                color: #4f46e5;
                background: #eeebff;
                padding: 2px 8px;
                border-radius: 9999px;
            }
            .ct-reply-date {
                font-size: 11px;
                color: #9ca3af;
            }
            .ct-reply-text {
                font-size: 13px;
                line-height: 1.5;
                color: #374151;
                margin: 0;
                white-space: pre-wrap;
            }
            .ct-watermark {
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 24px;
                padding-top: 16px;
                border-top: 1px solid #f3f4f6;
                font-size: 11px;
                color: #9ca3af;
                font-weight: 600;
            }
            .ct-watermark-link {
                color: #4f46e5;
                text-decoration: none;
                margin-right: 4px;
                font-weight: 700;
            }
            .ct-watermark-link:hover {
                text-decoration: underline;
            }
        `;

        const styleElement = document.createElement('style');
        styleElement.textContent = styles;
        shadow.appendChild(styleElement);

        // Watermark HTML logic
        const showWatermark = data.show_watermark !== false;
        let watermarkHtml = '';
        if (showWatermark) {
            watermarkHtml = `
                <div class="ct-watermark">
                    <span>موثق عبر</span>
                    <a class="ct-watermark-link" href="${backendUrl}" target="_blank">Conversion Trust</a>
                </div>
            `;
        }

        // Build HTML template
        const template = `
            <div class="ct-widget-container">
                <div class="ct-widget-header">
                    <div class="ct-header-left">
                        <div class="ct-avg-number">${average.toFixed(1)}</div>
                        <div class="ct-avg-details">
                            <div class="ct-stars-row">
                                ${renderStars(average, '20px')}
                            </div>
                            <div class="ct-reviews-count">بناءً على ${totalCount} تقييم</div>
                        </div>
                    </div>
                    <div class="ct-header-title">تقييمات وآراء العملاء</div>
                </div>

                <div class="ct-reviews-list">
                    ${reviewsHtml}
                </div>

                ${watermarkHtml}
            </div>
        `;

        const contentContainer = document.createElement('div');
        contentContainer.innerHTML = template;
        shadow.appendChild(contentContainer);
    }

    // Inject JSON-LD Structured Data (Review Schema) for Google search snippets
    function injectStructuredData(data) {
        try {
            const oldSchema = document.getElementById('conversion-trust-reviews-schema');
            if (oldSchema) {
                oldSchema.remove();
            }

            const product = data.product || {};
            const stats = data.rating_stats || {};
            const reviewsList = data.reviews || [];

            if (!stats.count || stats.count === 0) return;

            const schemaJson = {
                "@context": "https://schema.org",
                "@type": "Product",
                "name": product.name || document.title,
                "image": product.image_url || undefined,
                "url": product.product_url || window.location.href,
                "aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": stats.average.toString(),
                    "reviewCount": stats.count.toString(),
                    "bestRating": "5",
                    "worstRating": "1"
                }
            };

            if (reviewsList.length > 0) {
                schemaJson.review = reviewsList.slice(0, 10).map(r => ({
                    "@type": "Review",
                    "author": {
                        "@type": "Person",
                        "name": r.customer ? r.customer.name : "عميل متجر"
                    },
                    "datePublished": r.created_at ? r.created_at.split('T')[0] : new Date().toISOString().split('T')[0],
                    "reviewBody": r.comment || "تقييم رائع",
                    "reviewRating": {
                        "@type": "Rating",
                        "ratingValue": r.rating.toString(),
                        "bestRating": "5",
                        "worstRating": "1"
                    }
                }));
            }

            const script = document.createElement('script');
            script.type = 'application/ld+json';
            script.id = 'conversion-trust-reviews-schema';
            script.textContent = JSON.stringify(schemaJson);
            document.head.appendChild(script);

            console.info('[ConversionTrust] Structured data (Review Schema) injected successfully for Google SEO.');
        } catch (e) {
            console.error('[ConversionTrust] Failed to inject structured data schema:', e);
        }
    }
})();
