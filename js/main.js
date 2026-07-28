jQuery(document).ready(function ($) {
    "use strict";

    /*--------------------------
        SCROLLSPY ACTIVE
    ---------------------------*/
    $('body').scrollspy({
        target: '.bs-example-js-navbar-scrollspy',
        offset: 95
    });


    /*--------------------------
        STICKY MAINMENU
    ---------------------------*/
    if ($(window).width() > 767 && $.fn.sticky) {
        try {
            $("#mainmenu-area").sticky({
                topSpacing: 0
            });
        } catch(e) {
            console.warn('Sticky plugin error:', e);
        }
    }


    /*-----------------------------
        SLIDER ACTIVE
    ------------------------------*/
    var mySlider = $('.pogoSlider').pogoSlider({
        pauseOnHover: false
    }).data('plugin_pogoSlider');


    /*----------------------------
        OPEN SEARCH FORM
    ----------------------------*/
    var $searchForm = $('.search-form');
    var $searchFormTrigger = $('.search-form-trigger');
    var $formOverlay = $('.search-form-overlay');
    $searchFormTrigger.on('click', function (event) {
        event.preventDefault();
        toggleSearch();
    });

    function toggleSearch(type) {
        if (type === "close") {
            //close serach 
            $searchForm.removeClass('is-visible');
            $searchFormTrigger.removeClass('search-is-visible');
        } else {
            //toggle search visibility
            $searchForm.toggleClass('is-visible');
            $searchFormTrigger.toggleClass('search-is-visible');
            if ($searchForm.hasClass('is-visible')) $searchForm.find('input[type="search"]').focus();
            $searchForm.hasClass('is-visible') ? $formOverlay.addClass('is-visible') : $formOverlay.removeClass('is-visible');
        }
    }


    /*------------------------------
        DATE PICKER ACTIVE (DISABLE PAST DATES)
    -------------------------------*/
    if ($.fn.datepicker) {
        $('[data-select="datepicker"], #inline-date').datepicker({
            startDate: new Date(),
            autoHide: true,
            format: 'yyyy-mm-dd'
        });
    }

    /*--------------------------------------------------
        SAMSUNG ALARM CLOCK CIRCULAR TIME PICKER ENGINE
    ---------------------------------------------------*/
    var $modal = $('#samsung-clock-modal');
    var $dial = $('#samsung-clock-dial');
    var selHour = 6;
    var selMin = '00';
    var selAmPm = 'PM';

    // Generate 12 Circular Ticks on Dial
    if ($dial.length) {
        $dial.find('.samsung-clock-tick').remove();
        var radius = 85;
        var center = 115;
        var hourNumbers = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

        hourNumbers.forEach(function(h, idx) {
            var angleDeg = (idx * 30) - 90;
            var angleRad = angleDeg * (Math.PI / 180);
            var x = Math.round(center + radius * Math.cos(angleRad) - 19);
            var y = Math.round(center + radius * Math.sin(angleRad) - 19);

            var $tick = $('<div class="samsung-clock-tick"></div>')
                .css({ left: x + 'px', top: y + 'px' })
                .text(h)
                .attr('data-hour', h);

            if (h === selHour) $tick.addClass('active');
            $dial.append($tick);
        });
    }

    function updateSamsungPreview() {
        var hStr = (selHour < 10 ? '0' : '') + selHour;
        $('#samsung-preview-hour').text(hStr);
        $('#samsung-preview-min').text(selMin);
        $('#samsung-preview-ampm').text(selAmPm);
    }

    // Open Modal on Single Field Click
    $(document).on('click focus', '#inline-time-display', function(e) {
        e.preventDefault();
        $('#samsung-step-hour').show();
        $('#samsung-step-minute').hide();
        $('#samsung-step-label').text('SELECT HOUR');
        updateSamsungPreview();
        $modal.addClass('active');
    });

    // Close Modal
    $(document).on('click', '#samsung-clock-close, .samsung-clock-picker-overlay', function(e) {
        if (e.target === this || $(e.target).hasClass('samsung-clock-close') || $(e.target).parent().hasClass('samsung-clock-close')) {
            $modal.removeClass('active');
        }
    });

    // AM/PM Toggle
    $(document).on('click', '.samsung-ampm-toggle .ampm-btn', function() {
        $('.samsung-ampm-toggle .ampm-btn').removeClass('active');
        $(this).addClass('active');
        selAmPm = $(this).data('ampm');
        updateSamsungPreview();
    });

    // Step 1: Select Hour on Circular Clock Face
    $(document).on('click', '.samsung-clock-tick', function() {
        $('.samsung-clock-tick').removeClass('active');
        $(this).addClass('active');
        selHour = parseInt($(this).data('hour'));
        updateSamsungPreview();

        // Transition to Step 2: Minutes
        $('#samsung-step-hour').fadeOut(150, function() {
            $('#samsung-step-minute').fadeIn(150);
            $('#samsung-step-label').text('SELECT MINUTE');
        });
    });

    // Back to Hours Step
    $(document).on('click', '#samsung-back-to-hours', function() {
        $('#samsung-step-minute').fadeOut(150, function() {
            $('#samsung-step-hour').fadeIn(150);
            $('#samsung-step-label').text('SELECT HOUR');
        });
    });

    // Step 2: Select Minute (:00 or :30) and Finalize
    $(document).on('click', '.samsung-minute-btn', function() {
        selMin = $(this).data('min') + '';
        updateSamsungPreview();

        // Calculate 24-hr format
        var hr24 = selHour;
        if (selAmPm === 'PM' && selHour < 12) hr24 += 12;
        if (selAmPm === 'AM' && selHour === 12) hr24 = 0;
        
        var hr24Str = (hr24 < 10 ? '0' : '') + hr24;
        var time24 = hr24Str + ':' + selMin;
        var displayStr = selHour + ':' + selMin + ' ' + selAmPm;

        // Set form field values
        $('#inline-time-display').val(displayStr);
        $('#inline-time').val(time24);

        // Auto close modal
        $modal.removeClass('active');
    });


    /*--------------------------
       HOME PARALLAX BACKGROUND
    ----------------------------*/
    $(window).stellar({
        responsive: true,
        positionProperty: 'position',
        horizontalScrolling: false
    });


    /*------------------------------
        VIDEO BLOG POPUP
    --------------------------------*/
    $('.blog-video-button').magnificPopup({
        disableOn: 700,
        type: 'iframe',
        mainClass: 'mfp-fade',
        removalDelay: 320,
        preloader: false
    });

    /*------------------------------
        GALLERY IMAGE POPUP
    --------------------------------*/
    $('.gallery-overlay a').magnificPopup({
        type: 'image',
        gallery: {
            enabled: true
        },
        mainClass: 'mfp-fade',
        removalDelay: 300
    });


    /*---------------------------
        SMOOTH SCROLL
    -----------------------------*/
    $('a.scrolltotop, .slider-area h3 a, .navbar-header a, ul#nav a').on('click', function (event) {
        var id = $(this).attr("href");
        var offset = 90;
        var target = $(id).offset().top - offset;
        $('html, body').animate({
            scrollTop: target
        }, 1500, "easeInOutExpo");
        event.preventDefault();
    });


    /*----------------------------
        SCROLL TO TOP
    ------------------------------*/
    $(window).on("scroll", function () {
        var $totalHeight = $(window).scrollTop();
        var $documentHeight = $(document).height() - $(window).height();
        var scrollProgress = ($totalHeight / $documentHeight) * 100;
        
        var $scrollToTop = $(".scrolltotop");
        
        if ($scrollToTop.length) {
            $scrollToTop[0].style.setProperty('--scroll-progress', scrollProgress + '%');
        }

        if ($totalHeight > 300) {
            $scrollToTop.fadeIn();
        } else {
            $scrollToTop.fadeOut();
        }
        
        if ($totalHeight + $(window).height() >= $(document).height() - 10) {
            $scrollToTop.css("bottom", "90px");
        } else {
            $scrollToTop.css("bottom", "20px");
        }
    });


    /*---------------------------
        MENU LIST MIXITUP FILTERING
    ----------------------------*/
    $('.food-menu-list').mixItUp();


    /*---------------------------
        MENU DISCOUNT SLIDER
    -----------------------------*/
    $('.menu-discount-offer').owlCarousel({
        merge: true,
        video: true,
        items: 1,
        smartSpeed: 1000,
        loop: true,
        nav: false,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: false,
        autoplayTimeout: 2000,
        margin: 15,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 1
            },
            1000: {
                items: 1
            }
        }
    });


    /*---------------------------
        TEAM SLIDER
    -----------------------------*/
    $('.team-slider').owlCarousel({
        merge: true,
        video: true,
        items: 1,
        smartSpeed: 1000,
        loop: true,
        nav: false,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: $(window).width() <= 767,
        autoplayTimeout: 2000,
        margin: 15,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 3
            },
            1000: {
                items: 4
            },
            1200: {
                items: 5
            }
        }
    });


    /*---------------------------
        BLOG POST SLIDER
    -----------------------------*/
    $('.post-slider').owlCarousel({
        merge: true,
        video: true,
        items: 1,
        smartSpeed: 2000,
        loop: true,
        nav: true,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: true,
        autoplayTimeout: 3000,
        margin: 15,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 1
            },
            1000: {
                items: 2
            },
            1200: {
                items: 3
            }
        }
    });

    /*---------------------------
        GALLERY SLIDER
    -----------------------------*/
    $('.gallery-slider').owlCarousel({
        merge: true,
        video: true,
        items: 1,
        smartSpeed: 1000,
        loop: true,
        nav: true,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: true,
        autoplayTimeout: 3000,
        margin: 15,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 2
            },
            1000: {
                items: 3
            },
            1200: {
                items: 4
            }
        }
    });


    /*---------------------------
        BLOG POST IMAGE SLIDER
    -----------------------------*/
    $('.blog-image-sldie').owlCarousel({
        merge: true,
        video: true,
        items: 1,
        smartSpeed: 1000,
        loop: true,
        animateIn: 'fadeIn',
        animateOut: 'fadeOut',
        nav: true,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: false,
        autoplayTimeout: 2000,
        margin: 15,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 1
            },
            1000: {
                items: 1
            }
        }
    });
    
    /*---------------------------
        BMENU SLIDER
    -----------------------------*/
    $('.food-menu-list.food-menu-slider').owlCarousel({
        smartSpeed: 1000,
        loop: true,
        nav: true,
        navText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
        autoplay: true,
        autoplayTimeout: 3000,
        margin: 30,
        responsiveClass: true,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 2
            },
            1000: {
                items: 3
            }
        }
    });

    
    /*----------------------------
        INSTAGRAM FEED ACTIVE
    -----------------------------*/
    if ($('#instagram').length && typeof Instafeed !== 'undefined') {
        try {
            var feed = new Instafeed({
                get: 'user',
                userId: 3287251940,
                accessToken: '3287251940.4ac71b3.d88be01ca9c94e2e8a2d923fe0a5169e',
                target: 'instagram',
                limit: 10, //max 60 images..
                resolution: 'standard_resolution',
                after: function () {
                    var el = document.getElementById('instagram');
                    if (el) {
                        if (el.classList)
                            el.classList.add('show');
                        else
                            el.className += ' ' + 'show';
                    }
                }
            });
            feed.run();
        } catch (e) {
            console.warn('Instafeed bypassed:', e);
        }
    }
    
    
    /*--------------------------
        ACTIVE WOW JS
    ----------------------------*/
    if (typeof WOW !== 'undefined') {
        try {
            new WOW().init();
        } catch(e) {
            console.warn('WOW init bypassed:', e);
        }
    }
    
    /*---------------------------
        MOBILE MENU ACCORDION
    -----------------------------*/
    $('.custom-card').on('click', function() {
        if ($(window).width() <= 767) {
            $(this).toggleClass('is-expanded');
        }
    });

    // Set minimum booking date to today
    var todayStr = new Date().toISOString().split('T')[0];
    $('#inline-date').attr('min', todayStr);

    /*---------------------------
        RESERVATION FORM SUBMIT
    -----------------------------*/
    $(document).on('submit', '#reservation-inline', async function(e) {
        e.preventDefault();
        var dateVal = $('#inline-date').val();
        var date = dateVal;
        var todayVal = new Date().toISOString().split('T')[0];

        if (dateVal && dateVal.includes('/')) {
            var parts = dateVal.split('/');
            if (parts.length === 3) {
                if (parts[0].length === 4) {
                    date = parts[0] + '-' + parts[1].padStart(2, '0') + '-' + parts[2].padStart(2, '0');
                } else {
                    date = parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
                }
            }
        }

        if (!date || date < todayVal) {
            alert('Past dates are not allowed. Please select today or a future date for your reservation.');
            return false;
        }

        var submitBtn = $(this).find('button[type="submit"]');
        var originalBtnHtml = submitBtn.html();
        
        submitBtn.html('<span class="btn-text">BOOKING...</span> <i class="fa-solid fa-spinner fa-spin"></i>');
        submitBtn.prop('disabled', true);

        var name = $('#inline-name').val();
        var email = $('#inline-email').val();
        var mobile = $('#inline-mobile').val();
        var time = $('#inline-time').val() || '18:00';
        var adults = parseInt($('#inline-adults').val() || 0);
        var children = parseInt($('#inline-children').val() || 0);
        var requests = $('#inline-requests').val();
        var guests = adults + children;

        try {
            const res = await fetch('admin/api/bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, mobile, date, time, guests, requests, adults, children })
            });
            
            if (!res.ok) {
                throw new Error('Server returned ' + res.status + ' ' + res.statusText);
            }
            
            const data = await res.json();
            
            if (data.success) {
                // 1. Success animation
                submitBtn.addClass('btn-success-booked');
                submitBtn.html('<span class="btn-left-icon" style="background:transparent!important;"><i class="fa-solid fa-circle-check" style="font-size:20px; color:#fff;"></i></span> <span class="btn-text" style="font-weight:800; font-size:16px; letter-spacing:1px;">TABLE BOOKED!</span> <span class="btn-right-arrow" style="background:transparent!important;"><i class="fa-solid fa-check" style="font-size:18px; color:#fff;"></i></span>');

                // 2. Show floating success toast banner
                var $toast = $('#booking-success-toast');
                $toast.addClass('show');

                // 3. Reset form inputs
                $('#reservation-inline')[0].reset();
                $('#inline-date').attr('min', todayVal);
                $('#inline-time-display').val('6:00 PM');
                $('#inline-time').val('18:00');

                // 4. Hide toast & reset button after 4 seconds
                setTimeout(function() {
                    $toast.removeClass('show');
                    submitBtn.removeClass('btn-success-booked');
                    submitBtn.html(originalBtnHtml);
                    submitBtn.prop('disabled', false);
                }, 4000);
            } else {
                alert('Booking failed: ' + (data.message || 'Unknown error'));
                submitBtn.html(originalBtnHtml);
                submitBtn.prop('disabled', false);
            }
        } catch (err) {
            console.error('Error submitting booking:', err);
            alert('Server error. Please try again later.');
            submitBtn.html(originalBtnHtml);
            submitBtn.prop('disabled', false);
        }
        return false;
    });

});


/*--------------------------
    FAIL-SAFE PRELOADER ENGINE
----------------------------*/
function hidePreloader() {
    var $loader = $('.preeloader, #app-preloader');
    if ($loader.length && !$loader.hasClass('dismissed')) {
        $loader.addClass('dismissed preloader-hidden').css({'opacity': '0', 'pointer-events': 'none'});
        setTimeout(function() {
            $loader.hide().remove();
        }, 400);
    }
}

// 1. Hide on DOM ready
jQuery(document).ready(function() {
    setTimeout(hidePreloader, 300);
});

// 2. Hide on window load
jQuery(window).on('load', function () {
    hidePreloader();

    /*--------------------------
        SMOOTH SCROLL
    ----------------------------*/
    $('a[href^="#"]').on('click', function (e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 60
            }, 1000, 'swing');
        }
    });
});

// 3. Absolute 800ms fail-safe fallback
setTimeout(hidePreloader, 800);