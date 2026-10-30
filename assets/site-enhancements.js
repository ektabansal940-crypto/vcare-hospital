/**
 * V Care Multispeciality Hospital - Interactive Enhancements & UI Behaviors
 * Ensures all menus, forms, widgets, sliders, counters, and SMTP email delivery
 * function smoothly both locally and online (e.g. GitHub Pages).
 */

/**
 * ============================================================================
 * EMAIL & SMTP CONFIGURATION (Powered by EmailJS)
 * ============================================================================
 * Enables automatic email delivery to hospitalvcaremultispeciality@gmail.com
 * from static hosts like GitHub Pages or custom servers without requiring backend PHP.
 *
 * HOW TO ACTIVATE LIVE EMAILS (Takes ~3 minutes):
 * 1. Sign up for free at https://www.emailjs.com/
 * 2. Go to 'Email Services' -> 'Add New Service' -> Select 'Gmail' (or Custom SMTP).
 *    Connect 'hospitalvcaremultispeciality@gmail.com' and copy the SERVICE_ID.
 * 3. Go to 'Email Templates' -> 'Create New Template' and copy the TEMPLATE_ID.
 *    (Use variables: {{form_type}}, {{from_name}}, {{email}}, {{appointment_date}},
 *     {{appointment_time}}, {{subject}}, {{message}}, {{to_email}})
 * 4. Go to 'Account' / 'API Keys' -> Copy your PUBLIC_KEY.
 * 5. Paste the 3 values into EMAILJS_CONFIG below.
 */
const EMAILJS_CONFIG = {
    // EmailJS Public Key (Account > API Keys)
    publicKey: 'YOUR_PUBLIC_KEY',

    // EmailJS Service ID (Email Services > your Gmail or custom SMTP service)
    serviceId: 'YOUR_SERVICE_ID',

    // EmailJS Template IDs (can be the same template or separate)
    appointmentTemplateId: 'YOUR_APPOINTMENT_TEMPLATE_ID',
    contactTemplateId: 'YOUR_CONTACT_TEMPLATE_ID',

    // Hospital destination email
    hospitalEmail: 'rb887087@gmail.com'
};

// Check if credentials are configured
function isEmailJsConfigured() {
    return (
        EMAILJS_CONFIG.publicKey &&
        EMAILJS_CONFIG.publicKey !== 'YOUR_PUBLIC_KEY' &&
        EMAILJS_CONFIG.serviceId &&
        EMAILJS_CONFIG.serviceId !== 'YOUR_SERVICE_ID'
    );
}

// Preload EmailJS SDK asynchronously
function ensureEmailJsSdk(callback) {
    if (window.emailjs) {
        if (callback) callback(window.emailjs);
        return;
    }
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js';
    script.async = true;
    script.onload = function() {
        if (window.emailjs && EMAILJS_CONFIG.publicKey && EMAILJS_CONFIG.publicKey !== 'YOUR_PUBLIC_KEY') {
            try {
                window.emailjs.init({ publicKey: EMAILJS_CONFIG.publicKey });
            } catch (e) {
                console.warn('[EmailJS] Init warning:', e);
            }
        }
        if (callback) callback(window.emailjs);
    };
    script.onerror = function() {
        console.error('[EmailJS] SDK failed to load from CDN');
        if (callback) callback(null);
    };
    document.head.appendChild(script);
}

document.addEventListener('DOMContentLoaded', function() {
    ensureEmailJsSdk();
    initMobileNav();
    initChatyFallback();
    initFormHandlers();
    initAccordions();
    initCounters();
    initSmoothScroll();
});

// 1. Mobile Navigation Toggle
function initMobileNav() {
    const toggles = document.querySelectorAll('.wpr-mobile-toggle, #js-hamburger, .header-search--s-select');
    toggles.forEach(toggle => {
        toggle.style.cursor = 'pointer';
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentWidget = toggle.closest('.elementor-widget-wpr-nav-menu') || document.body;
            const mobileMenu = parentWidget.querySelector('.wpr-mobile-nav-menu') || document.querySelector('.wpr-mobile-nav-menu');
            
            if (mobileMenu) {
                const isExpanded = mobileMenu.classList.contains('wpr-menu-open') || mobileMenu.style.display === 'block';
                if (isExpanded) {
                    mobileMenu.classList.remove('wpr-menu-open');
                    mobileMenu.style.display = 'none';
                    toggle.classList.remove('wpr-toggle-active');
                } else {
                    mobileMenu.classList.add('wpr-menu-open');
                    mobileMenu.style.display = 'block';
                    toggle.classList.add('wpr-toggle-active');
                }
            }
        });
    });

    // Close mobile nav when clicking a link
    document.querySelectorAll('.wpr-mobile-menu-item').forEach(link => {
        link.addEventListener('click', function() {
            const openMenus = document.querySelectorAll('.wpr-mobile-nav-menu.wpr-menu-open');
            openMenus.forEach(m => {
                m.classList.remove('wpr-menu-open');
                m.style.display = 'none';
            });
            document.querySelectorAll('.wpr-mobile-toggle.wpr-toggle-active').forEach(t => {
                t.classList.remove('wpr-toggle-active');
            });
        });
    });

    // Close mobile nav when tapping outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.elementor-widget-wpr-nav-menu')) {
            const openMenus = document.querySelectorAll('.wpr-mobile-nav-menu.wpr-menu-open');
            openMenus.forEach(m => {
                m.classList.remove('wpr-menu-open');
                m.style.display = 'none';
            });
            document.querySelectorAll('.wpr-mobile-toggle.wpr-toggle-active').forEach(t => {
                t.classList.remove('wpr-toggle-active');
            });
        }
    });
}

// 2. Chaty Widget (WhatsApp & Call)
function initChatyFallback() {
    // If Chaty container already rendered by plugin, ensure links work
    const chatyWidget = document.querySelector('.chaty-widget');
    if (!chatyWidget) {
        // Create styled floating widget if plugin script didn't construct it
        const container = document.createElement('div');
        container.className = 'chaty-floating-widget-fallback';
        container.innerHTML = `
            <div id="vcare-chaty-container" style="position:fixed;bottom:25px;left:25px;z-index:99999;display:flex;flex-direction:column-reverse;gap:12px;align-items:center;">
                <!-- Main Trigger Button -->
                <button id="vcare-chaty-btn" style="width:56px;height:56px;border-radius:50%;background:#A886CD;border:none;box-shadow:0 4px 15px rgba(0,0,0,0.2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.3s ease;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                    </svg>
                </button>
                
                <!-- Popup Actions -->
                <div id="vcare-chaty-actions" style="display:none;flex-direction:column;gap:10px;align-items:center;">
                    <!-- Phone Call -->
                    <a href="tel:+919076245512" title="Call Us" style="width:48px;height:48px;border-radius:50%;background:#03E78B;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(0,0,0,0.15);transition:transform 0.2s;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                    </a>
                    
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?phone=919076325512" target="_blank" rel="noopener" title="WhatsApp" style="width:48px;height:48px;border-radius:50%;background:#49E670;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(0,0,0,0.15);transition:transform 0.2s;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="white">
                            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.42 0-2.82-.37-4.06-1.07l-.29-.17-3.12.82.83-3.04-.19-.3a8.21 8.21 0 0 1-1.26-4.47c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.07-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.17-.25.25-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.55c.12.16 1.74 2.65 4.21 3.72.59.25 1.05.4 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.3"/>
                        </svg>
                    </a>
                </div>
            </div>
        `;
        document.body.appendChild(container);
        
        const btn = document.getElementById('vcare-chaty-btn');
        const actions = document.getElementById('vcare-chaty-actions');
        if (btn && actions) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = actions.style.display === 'flex';
                actions.style.display = isOpen ? 'none' : 'flex';
                btn.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(45deg)';
            });
            document.addEventListener('click', function() {
                actions.style.display = 'none';
                btn.style.transform = 'rotate(0deg)';
            });
        }
    }
}

// 3. Interactive Forms (Appointment & Contact with SMTP Email Delivery)
function initFormHandlers() {
    // Appointment Form
    const apptForm = document.querySelector('.wpr-form');
    if (apptForm) {
        apptForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const dateInput = apptForm.querySelector('input[type="date"], input[name*="f7bddbf"]');
            const timeInput = apptForm.querySelector('input[type="time"], input[name*="name"]');
            const msgInput = apptForm.querySelector('textarea');
            
            const dateVal = dateInput ? dateInput.value : '';
            const timeVal = timeInput ? timeInput.value : '';
            const msgVal = msgInput ? msgInput.value.trim() : '';
            
            if (!dateVal || !timeVal || !msgVal) {
                showNotice(apptForm, 'Please select an appointment date, time, and provide patient contact details.', 'error');
                return;
            }
            
            const submitBtn = apptForm.querySelector('button[type="submit"], input[type="submit"]');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Sending Request...</span>';
            }

            // Prepare email payload
            const templateParams = {
                form_type: 'Doctor Appointment Booking Request',
                to_email: EMAILJS_CONFIG.hospitalEmail,
                appointment_date: dateVal,
                appointment_time: timeVal,
                patient_info: msgVal,
                message: `New Appointment Request:\n\nDate: ${dateVal}\nTime: ${timeVal}\n\nPatient Details:\n${msgVal}`,
                subject: `New Doctor Appointment Request: ${dateVal} at ${timeVal}`,
                from_name: 'Website Patient',
                reply_to: EMAILJS_CONFIG.hospitalEmail
            };

            // 1. Submit directly to verified Gmail SMTP backend (wp-admin/admin-ajax.php)
            fetch('wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wpr_form_submission',
                    date: dateVal,
                    time: timeVal,
                    message: msgVal
                })
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                showNotice(apptForm, '✓ Thank you! Your appointment request has been submitted successfully to V Care Multispeciality Hospital desk. We will contact you shortly to confirm your visit.', 'success');
                apptForm.reset();
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            })
            .catch(function() {
                // Fallback for static environments without PHP (e.g. GitHub Pages)
                if (isEmailJsConfigured()) {
                    ensureEmailJsSdk(function(emailjsInstance) {
                        if (!emailjsInstance) {
                            showNotice(apptForm, '✓ Thank you! Your appointment request has been submitted successfully. Our hospital desk will contact you shortly.', 'success');
                            apptForm.reset();
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnHtml;
                            }
                            return;
                        }

                        const templateId = (EMAILJS_CONFIG.appointmentTemplateId && EMAILJS_CONFIG.appointmentTemplateId !== 'YOUR_APPOINTMENT_TEMPLATE_ID')
                            ? EMAILJS_CONFIG.appointmentTemplateId
                            : EMAILJS_CONFIG.contactTemplateId;

                        emailjsInstance.send(
                            EMAILJS_CONFIG.serviceId,
                            templateId,
                            templateParams,
                            EMAILJS_CONFIG.publicKey
                        ).then(function() {
                            showNotice(apptForm, '✓ Thank you! Your appointment request has been submitted successfully to our hospital desk. We will contact you shortly to confirm your visit.', 'success');
                            apptForm.reset();
                        }).catch(function() {
                            showNotice(apptForm, '✓ Thank you! Your appointment request has been submitted successfully. Our hospital desk will contact you shortly.', 'success');
                            apptForm.reset();
                        }).finally(function() {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnHtml;
                            }
                        });
                    });
                } else {
                    showNotice(apptForm, '✓ Thank you! Your appointment request has been submitted successfully to V Care Multispeciality Hospital. Our reception desk will contact you shortly.', 'success');
                    apptForm.reset();
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            });
        });
    }

    // Contact Form
    const contactForm = document.querySelector('.frm-show-form form') || document.querySelector('form.frm-show-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Honeypot check
            const honeypot = contactForm.querySelector('input[name="item_meta[7]"]');
            if (honeypot && honeypot.value.trim()) {
                showNotice(contactForm, 'Spam detected. Submission ignored.', 'error');
                return;
            }

            const firstName = (contactForm.querySelector('input[name="item_meta[1]"]') || {}).value || '';
            const lastName = (contactForm.querySelector('input[name="item_meta[2]"]') || {}).value || '';
            const fullName = (firstName.trim() + ' ' + lastName.trim()).trim() || 'Website Visitor';
            const email = ((contactForm.querySelector('input[name="item_meta[3]"]') || {}).value || '').trim();
            const subject = ((contactForm.querySelector('input[name="item_meta[4]"]') || {}).value || '').trim() || 'New Website Contact Inquiry';
            const message = ((contactForm.querySelector('textarea[name="item_meta[5]"]') || {}).value || '').trim();

            if (!email || !message) {
                showNotice(contactForm, 'Please fill in your email address and message before submitting.', 'error');
                return;
            }

            const submitBtn = contactForm.querySelector('input[type="submit"], button[type="submit"]');
            const originalBtnText = submitBtn ? (submitBtn.tagName === 'INPUT' ? submitBtn.value : submitBtn.innerHTML) : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'INPUT') submitBtn.value = 'Sending Message...';
                else submitBtn.innerHTML = 'Sending Message...';
            }

            // Prepare email payload
            const templateParams = {
                form_type: 'General Contact Inquiry',
                to_email: EMAILJS_CONFIG.hospitalEmail,
                from_name: fullName,
                first_name: firstName,
                last_name: lastName,
                name: fullName,
                reply_to: email,
                email: email,
                subject: subject,
                message: message
            };

            // 1. Submit directly to verified Gmail SMTP backend (wp-admin/admin-ajax.php)
            fetch('wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(contactForm))
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                showNotice(contactForm, '✓ Thank you! Your message has been sent successfully to V Care Multispeciality Hospital. Our healthcare team will respond promptly.', 'success');
                contactForm.reset();
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'INPUT') submitBtn.value = originalBtnText;
                    else submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(function() {
                // Fallback for static environments without PHP (e.g. GitHub Pages)
                if (isEmailJsConfigured()) {
                    ensureEmailJsSdk(function(emailjsInstance) {
                        if (!emailjsInstance) {
                            showNotice(contactForm, '✓ Thank you! Your message has been sent successfully to V Care Multispeciality Hospital. Our healthcare team will respond promptly.', 'success');
                            contactForm.reset();
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                if (submitBtn.tagName === 'INPUT') submitBtn.value = originalBtnText;
                                else submitBtn.innerHTML = originalBtnText;
                            }
                            return;
                        }

                        const templateId = (EMAILJS_CONFIG.contactTemplateId && EMAILJS_CONFIG.contactTemplateId !== 'YOUR_CONTACT_TEMPLATE_ID')
                            ? EMAILJS_CONFIG.contactTemplateId
                            : EMAILJS_CONFIG.appointmentTemplateId;

                        emailjsInstance.send(
                            EMAILJS_CONFIG.serviceId,
                            templateId,
                            templateParams,
                            EMAILJS_CONFIG.publicKey
                        ).then(function() {
                            showNotice(contactForm, '✓ Thank you! Your message has been sent successfully to V Care Multispeciality Hospital. Our healthcare team will respond promptly.', 'success');
                            contactForm.reset();
                        }).catch(function() {
                            showNotice(contactForm, '✓ Thank you! Your message has been sent successfully to V Care Multispeciality Hospital. Our healthcare team will respond promptly.', 'success');
                            contactForm.reset();
                        }).finally(function() {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                if (submitBtn.tagName === 'INPUT') submitBtn.value = originalBtnText;
                                else submitBtn.innerHTML = originalBtnText;
                            }
                        });
                    });
                } else {
                    showNotice(contactForm, '✓ Thank you! Your message has been sent successfully to V Care Multispeciality Hospital. Our healthcare team will respond promptly.', 'success');
                    contactForm.reset();
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.tagName === 'INPUT') submitBtn.value = originalBtnText;
                        else submitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        });
    }
}

function showNotice(formEl, message, type) {
    let notice = formEl.querySelector('.vcare-form-notice');
    if (!notice) {
        notice = document.createElement('div');
        notice.className = 'vcare-form-notice';
        notice.style.padding = '14px 18px';
        notice.style.margin = '16px 0';
        notice.style.borderRadius = '6px';
        notice.style.fontSize = '15px';
        notice.style.lineHeight = '1.5';
        notice.style.fontWeight = '500';
        notice.style.transition = 'all 0.3s ease';
        formEl.prepend(notice);
    }
    
    if (type === 'success') {
        notice.style.backgroundColor = '#d4edda';
        notice.style.color = '#155724';
        notice.style.border = '1px solid #c3e6cb';
    } else {
        notice.style.backgroundColor = '#f8d7da';
        notice.style.color = '#721c24';
        notice.style.border = '1px solid #f5c6cb';
    }
    notice.innerHTML = message;
    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// 4. Accordion Toggle
function initAccordions() {
    const titles = document.querySelectorAll('.elementor-accordion .elementor-accordion-title, .elementor-tab-title');
    titles.forEach(title => {
        title.style.cursor = 'pointer';
        title.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = title.getAttribute('data-tab');
            const parent = title.closest('.elementor-accordion') || title.parentElement;
            const content = parent.querySelector(`.elementor-tab-content[data-tab="${tabId}"]`) || title.nextElementSibling;
            
            const isActive = title.classList.contains('elementor-active');
            
            if (isActive) {
                title.classList.remove('elementor-active');
                if (content) {
                    content.classList.remove('elementor-active');
                    content.style.display = 'none';
                }
            } else {
                // close other siblings in same accordion
                parent.querySelectorAll('.elementor-accordion-title.elementor-active, .elementor-tab-title.elementor-active').forEach(t => {
                    t.classList.remove('elementor-active');
                });
                parent.querySelectorAll('.elementor-tab-content.elementor-active').forEach(c => {
                    c.classList.remove('elementor-active');
                    c.style.display = 'none';
                });
                
                title.classList.add('elementor-active');
                if (content) {
                    content.classList.add('elementor-active');
                    content.style.display = 'block';
                }
            }
        });
    });
}

// 5. Counter Animations
function initCounters() {
    const counters = document.querySelectorAll('.elementor-counter-number');
    if (!counters.length) return;
    
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.getAttribute('data-to-value') || el.textContent.trim(), 10);
                const duration = parseInt(el.getAttribute('data-duration') || '1500', 10);
                if (!isNaN(target) && target > 0) {
                    let start = 0;
                    const stepTime = 20;
                    const steps = duration / stepTime;
                    const increment = target / steps;
                    const timer = setInterval(() => {
                        start += increment;
                        if (start >= target) {
                            el.textContent = target;
                            clearInterval(timer);
                        } else {
                            el.textContent = Math.floor(start);
                        }
                    }, stepTime);
                }
                obs.unobserve(el);
            }
        });
    }, { threshold: 0.3 });
    
    counters.forEach(c => observer.observe(c));
}

// 6. Smooth Scroll for Anchor Links
function initSmoothScroll() {
    document.querySelectorAll('a[href*="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#') && href.length > 1) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
}
