(function ($) {
  "use strict";

  var nav_offset_top = $("header").height() + 70;
  /*-------------------------------------------------------------------------------
	  Navbar Fixed
	-------------------------------------------------------------------------------*/

  function navbarFixed() {
    if ($(".header_area").length) {
      $(window).scroll(function () {
        var scroll = $(window).scrollTop();
        if (scroll >= nav_offset_top) {
          $(".header_area").addClass("navbar_fixed");
        } else {
          $(".header_area").removeClass("navbar_fixed");
        }
      });
    }
  }
  navbarFixed();

  Splitting();

  $(".height-emulator").css({
    height: $("footer").outerHeight(true),
  });

  /*----------------------------------------------------*/
  /*  Main Slider js
    /*----------------------------------------------------*/
  function main_slider1() {
    if ($("#main_slider1").length) {
      $("#main_slider1").revolution({
        sliderType: "standard",
        sliderLayout: "fullscreen",
        dottedOverlay: "none",
        delay: 7000,
        autoHeight: "on",
        minHeight: 480,
        navigation: {
          keyboardNavigation: "off",
          keyboard_direction: "horizontal",
          onHoverStop: "off",
          mouseScrollNavigation: "off",
          mouseScrollReverse: "default",
          touch: {
            touchenabled: "on",
            swipe_threshold: 75,
            swipe_min_touches: 1,
            swipe_direction: "horizontal",
            drag_block_vertical: false,
          },
          bullets: {
            enable: true,
            hide_onmobile: true,
            direction: "horizontal",
            h_align: "center",
            v_align: "bottom",
            h_offset: 0,
            v_offset: 80,
            space: 25,
          },
        },
        responsiveLevels: [1920, 1199, 991, 768, 480],
        gridwidth: [1106, 1106, 720, 700, 480],
        gridheight: [900, 900, 600, 600, 480],
        lazyType: "none",
        shadow: 0,
        spinner: "off",
        stopLoop: "on",
        stopAfterLoops: 0,
        stopAtSlide: 1,
        shuffle: "off",
        autoHeight: "off",
        fullScreenAutoWidth: "off",
        fullScreenAlignForce: "off",
        fullScreenOffsetContainer: "",
        fullScreenOffset: "",
        disableProgressBar: "on",
        hideThumbsOnMobile: "off",
        hideSliderAtLimit: 0,
        hideCaptionAtLimit: 0,
        hideAllCaptionAtLilmit: 0,
        debugMode: false,
        fallbacks: {
          simplifyAll: "off",
          nextSlideOnWindowFocus: "off",
          disableFocusListener: false,
        },
        parallax: {
          type: "mouse",
          origo: "slidercenter",
          speed: 2000,
          levels: [2, 3, 4, 5, 6, 7, 12, 16, 10, 50],
        },
      });
    }
  }
  main_slider1();

  $(".slide-number .total-count").text($("#main_slider1 li").length);
  $("#main_slider1").bind("revolution.slide.onchange", function (e, data) {
    $(".slide-number .count").text(data.slideIndex);
  });

  /*----------------------------------------------------*/
  /*  Wow Animation Active js
  /*----------------------------------------------------*/
  function bodyScrollAnimation() {
    var scrollAnimate = $("body").data("scroll-animation");
    if (scrollAnimate === true) {
      new WOW({
        mobile: false,
      }).init();
    }
  }
  bodyScrollAnimation();

  /*----------------------------------------------------*/
  /*  Video Popup js
  /*----------------------------------------------------*/
  function video_js() {
    if ($(".popup-youtube").length) {
      $(".popup-youtube").magnificPopup({
        disableOn: 700,
        type: "iframe",
        mainClass: "mfp-fade",
        removalDelay: 160,
        preloader: false,

        fixedContentPos: false,
      });
    }
  }
  video_js();

  function latest_project_slider() {
    if ($(".latest_slider").length) {
      $(".latest_slider").slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 5000,
        arrows: false,
        dots: false,
        pauseOnHover: true,
        infinite: true,
        centerMode: true,
        centerPadding: "300px",
        responsive: [
          {
            breakpoint: 992,
            settings: {
              slidesToShow: 2,
              centerPadding: "0px",
            },
          },
          {
            breakpoint: 768,
            settings: {
              slidesToShow: 1,
              centerPadding: "0px",
            },
          },
        ],
      });
    }
  }
  latest_project_slider();

  function testi_text_slider() {
    if ($(".testi_text_slider").length) {
      $(".testi_text_slider").slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 5000,
        arrows: false,
        dots: false,
        pauseOnHover: true,
        infinite: true,
        responsive: [
          {
            breakpoint: 768,
            settings: {
              slidesToShow: 1,
            },
          },
          {
            breakpoint: 520,
            settings: {
              slidesToShow: 1,
            },
          },
        ],
      });
    }
  }
  testi_text_slider();

  // debounce from underscore.js
  function debounce(func, wait, immediate) {
    var timeout;
    return function () {
      var context = this,
        args = arguments;
      var later = function () {
        timeout = null;
        if (!immediate) func.apply(context, args);
      };
      var callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) func.apply(context, args);
    };
  }

  // use x and y mousewheel event data to navigate flickity
  function slick_handle_wheel_event(e, slick_instance, slick_is_animating) {
    // do not trigger a slide change if another is being animated
    if (!slick_is_animating) {
      // pick the larger of the two delta magnitudes (x or y) to determine nav direction
      var direction =
        Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;

      console.log("wheel scroll ", e.deltaX, e.deltaY, direction);

      if (direction > 0) {
        // next slide
        slick_instance.slick("slickNext");
      } else {
        // prev slide
        slick_instance.slick("slickPrev");
      }
    }
  }

  // debounce the wheel event handling since trackpads can have a lot of inertia
  var slick_handle_wheel_event_debounced = debounce(
    slick_handle_wheel_event,
    100,
    true
  );

  // init slider
  const slick_2 = $(".latest_slider");
  var slick_2_is_animating = false;

  slick_2.on("afterChange", function (index) {
    console.log("Slide after change " + index);
    slick_2_is_animating = false;
  });

  slick_2.on("beforeChange", function (index) {
    console.log("Slide before change " + index);
    slick_2_is_animating = true;
  });

  slick_2.on("wheel", function (e) {
    slick_handle_wheel_event_debounced(
      e.originalEvent,
      slick_2,
      slick_2_is_animating
    );
  });

  /*=============================================== 
	*** Testimonials Slider
	================================================*/
  function Testi_slider() {
    if ($(".testi_slider").length) {
      $(".testi_slider").slick({
        dots: false,
        infinite: true,
        speed: 300,
        slidesToShow: 1,
        adaptiveHeight: true,
        prevArrow: ".prev",
        nextArrow: ".next",
        responsive: [
          {
            breakpoint: 768,
            settings: {
              slidesToShow: 1,
            },
          },
          {
            breakpoint: 520,
            settings: {
              slidesToShow: 1,
            },
          },
        ],
      });
    }
  }
  Testi_slider();

  /*----------------------------------------------------*/
  /*  Gallery One js
    /*----------------------------------------------------*/
  function gallery_isotope() {
    if ($(".portfolio_area").length) {
      // Activate isotope in container
      $(".portfolio_inner").imagesLoaded(function () {
        $(".portfolio_inner").isotope({
          layoutMode: "masonry",
          percentPosition: true,
        });
      });

      // Add isotope click function
      $(".g_fillter ul li").on("click", function () {
        $(".g_fillter ul li").removeClass("active");
        $(this).addClass("active");

        var selector = $(this).attr("data-filter");
        $(".portfolio_inner").isotope({
          filter: selector,
          animationOptions: {
            duration: 450,
            easing: "linear",
            queue: false,
          },
        });
        return false;
      });
    }
  }

  gallery_isotope();

  function gallery_masonery() {
    if ($(".portfolio_masonry,#masonry").length) {
      // Activate isotope in container
      $(".portfolio_inner,#masonry").imagesLoaded(function () {
        $(".portfolio_inner,#masonry").isotope({
          layoutMode: "masonry",
          percentPosition: true,
        });
      });
    }
  }
  gallery_masonery();

  /*----------------------------------------------------*/
  /*  parallaxie js
    /*----------------------------------------------------*/
  function parallax() {
    if ($(".parallaxie").length) {
      $(".parallaxie").parallaxie({
        speed: 0.5,
      });
    }
  }
  parallax();

  /*-------------------------------------------------------------------------------
	  Loader
	-------------------------------------------------------------------------------*/

  $(".animsition").animsition({
    inClass: "fade-in",
    outClass: "fade-out",
    inDuration: 1000,
    outDuration: 700,
    linkElement: ".nav.navbar-nav li a",
    // e.g. linkElement: 'a:not([target="_blank"]):not([href^="#"])'
    loading: true,
    loadingParentElement: "body", //animsition wrapper element
    loadingClass: "spinner",
    loadingInner:
      '<div class="double-bounce1"></div><div class="double-bounce2"></div>', // e.g '<img src="loading.svg" />'
    timeout: false,
    timeoutCountdown: 5000,
    onLoadEvent: true,
    browser: ["animation-duration", "-webkit-animation-duration"],
    // "browser" option allows you to disable the "animsition" in case the css property in the array is not supported by your browser.
    // The default setting is to disable the "animsition" in a browser that does not support "animation-duration".
    overlay: false,
    overlayClass: "animsition-overlay-slide",
    overlayParentElement: "body",
    transition: function (url) {
      window.location.href = url;
    },
  });

  /*-------------------------------------------------------------------------------
	  Ajax Forms
	-------------------------------------------------------------------------------*/

  if ($(".js-form").length) {
    $(".js-form").each(function () {
      $(this).validate({
        errorClass: "error wobble-error",
        submitHandler: function (form) {
          console.log(form);
          $.ajax({
            type: "POST",
            url: "mail.php",
            data: $(form).serialize(),
            success: function () {
              $(".success-message").show();
            },

            error: function () {
              $(".error-message").show();
            },
          });
        },
      });
    });
  }
})(jQuery);
