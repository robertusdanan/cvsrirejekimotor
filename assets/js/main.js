/* CV Sri Rejeki Motor — Interaction layer */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------- Header: shrink + blur on scroll ---------------- */
  var header = document.querySelector('.site-header');
  if (header) {
    var lastState = false;
    var onScroll = function () {
      var scrolled = window.scrollY > 40;
      if (scrolled !== lastState) {
        header.classList.toggle('is-scrolled', scrolled);
        lastState = scrolled;
      }
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------------- Mobile Navigation Drawer ---------------- */
  var navToggle = document.querySelector('.nav-toggle');
  var mainNav = document.getElementById('mainNav');
  var navScrim = document.querySelector('.nav-scrim');

  function openMenu() {
    if (!navToggle || !mainNav) return;
    navToggle.setAttribute('aria-expanded', 'true');
    mainNav.classList.add('is-open');
    if (navScrim) navScrim.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeMenu() {
    if (!navToggle || !mainNav) return;
    navToggle.setAttribute('aria-expanded', 'false');
    mainNav.classList.remove('is-open');
    if (navScrim) navScrim.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.contains('is-open');
      isOpen ? closeMenu() : openMenu();
    });
    if (navScrim) {
      navScrim.addEventListener('click', closeMenu);
    }
    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1024) closeMenu();
    });
  }

  /* ---------------- Hero Slideshow ---------------- */
  var hero = document.querySelector('.hero');
  if (hero) {
    var slides = hero.querySelectorAll('.hero-slide');
    var dots = hero.querySelectorAll('.hero-dot');

    if (slides.length > 1) {
      var current = 0;
      var intervalMs = parseInt(hero.getAttribute('data-interval'), 10) || 6000;
      var timer = null;

      var playVideoIn = function (slide) {
        var v = slide.querySelector('video');
        if (!v) return;
        v.muted = true;
        if (!v.getAttribute('preload') || v.getAttribute('preload') === 'none') {
          v.setAttribute('preload', 'auto');
        }
        try { v.currentTime = 0; } catch (e) {}
        var p = v.play();
        if (p && p.catch) p.catch(function () {});
      };

      var pauseVideoIn = function (slide) {
        var v = slide.querySelector('video');
        if (v) v.pause();
      };

      var goTo = function (index) {
        if (index === current) return;
        slides[current].classList.remove('is-active');
        pauseVideoIn(slides[current]);
        if (dots[current]) dots[current].classList.remove('is-active');

        current = (index + slides.length) % slides.length;

        slides[current].classList.add('is-active');
        playVideoIn(slides[current]);
        if (dots[current]) dots[current].classList.add('is-active');
      };

      var startTimer = function () {
        if (reduceMotion) return;
        window.clearInterval(timer);
        timer = window.setInterval(function () { goTo(current + 1); }, intervalMs);
      };

      dots.forEach(function (dot, i) {
        dot.addEventListener('click', function () {
          goTo(i);
          startTimer();
        });
      });

      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          window.clearInterval(timer);
        } else {
          startTimer();
        }
      });

      playVideoIn(slides[current]);
      startTimer();
    } else if (slides.length === 1) {
      var onlyVideo = slides[0].querySelector('video');
      if (onlyVideo) {
        onlyVideo.muted = true;
        var pp = onlyVideo.play();
        if (pp && pp.catch) pp.catch(function () {});
      }
    }
  }

  /* ---------------- Scroll Reveal ---------------- */
  var revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    if ('IntersectionObserver' in window && !reduceMotion) {
      var io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add('in-view');
              io.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
      );
      revealEls.forEach(function (el) { io.observe(el); });
    } else {
      revealEls.forEach(function (el) { el.classList.add('in-view'); });
    }
  }

  /* ---------------- Back to Top Button ---------------- */
  var backToTop = document.getElementById('backToTop');
  if (backToTop) {
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------------- Preloader Cleanup ---------------- */
  var preloader = document.getElementById('preloader');
  if (preloader) {
    var clear = function () {
      preloader.classList.add('is-done');
      window.setTimeout(function () {
        if (preloader.parentNode) preloader.parentNode.removeChild(preloader);
      }, 50);
    };
    if (document.documentElement.classList.contains('intro-seen') || reduceMotion) {
      clear();
    } else {
      preloader.addEventListener('animationend', function (e) {
        if (e.target === preloader) clear();
      });
      window.setTimeout(clear, 2500);
    }
  }
})();