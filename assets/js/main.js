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
  /* ---------------- Searchable Filter Dropdowns ---------------- */
  /* Combobox untuk filter Merek/Kendaraan/Kategori di /produk: ketik ->
     daftar opsi tersaring sesudah jeda 0,3 detik (debounce 300ms). */
  var combos = document.querySelectorAll('[data-combo]');
  combos.forEach(function (combo) {
    var input = combo.querySelector('.combo-input');
    var hidden = combo.querySelector('input[type="hidden"]');
    var list = combo.querySelector('.combo-list');
    if (!input || !hidden || !list) return;
    var items = Array.prototype.slice.call(list.querySelectorAll('li'));
    var noResult = document.createElement('li');
    noResult.className = 'combo-no-result';
    noResult.textContent = 'Tidak ada hasil';
    noResult.hidden = true;
    list.appendChild(noResult);
    var timer = null;
    var activeIndex = -1;

    var visibleItems = function () {
      return items.filter(function (li) { return !li.classList.contains('is-hidden'); });
    };
    var showList = function () { list.hidden = false; };
    var hideList = function () { list.hidden = true; activeIndex = -1; };
    var clearActive = function () {
      items.forEach(function (li) { li.classList.remove('is-active'); });
      activeIndex = -1;
    };
    var setActive = function (idx) {
      clearActive();
      var vis = visibleItems();
      if (!vis.length) return;
      activeIndex = (idx + vis.length) % vis.length;
      vis[activeIndex].classList.add('is-active');
      vis[activeIndex].scrollIntoView({ block: 'nearest' });
    };
    var selectItem = function (li) {
      if (!li || li.classList.contains('combo-no-result')) return;
      input.value = li.getAttribute('data-label') || '';
      hidden.value = li.getAttribute('data-value') || '';
      hideList();
    };
    var applyFilter = function () {
      var q = input.value.trim().toLowerCase();
      var anyVisible = false;
      items.forEach(function (li) {
        var match = q === '' || li.textContent.toLowerCase().indexOf(q) !== -1;
        li.classList.toggle('is-hidden', !match);
        if (match) anyVisible = true;
      });
      noResult.hidden = anyVisible;
      if (q === '') hidden.value = ''; // input dikosongkan = hapus filter
      clearActive();
      showList();
    };
    var scheduleFilter = function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(applyFilter, 300); // jeda 0,3 detik
    };
    input.addEventListener('input', scheduleFilter);
    input.addEventListener('focus', function () { applyFilter(); });
    input.addEventListener('blur', function () {
      window.setTimeout(hideList, 150);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); showList(); setActive(activeIndex + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); showList(); setActive(activeIndex - 1); }
      else if (e.key === 'Enter') {
        var vis = visibleItems();
        if (!list.hidden && vis.length) {
          e.preventDefault();
          selectItem(activeIndex >= 0 ? vis[activeIndex] : vis[0]);
        }
      } else if (e.key === 'Escape') { hideList(); }
    });
    items.forEach(function (li) {
      li.addEventListener('mousedown', function (e) {
        e.preventDefault(); // cegah blur sebelum klik terproses
        selectItem(li);
      });
    });
    // Saat form disubmit: bila teks tidak cocok persis dengan opsi mana pun,
    // kosongkan input supaya tidak ada teks menggantung tanpa filter.
    var form = combo.closest('form');
    if (form && !form.getAttribute('data-combo-submit-bound')) {
      form.setAttribute('data-combo-submit-bound', '1');
      form.addEventListener('submit', function () {
        combos.forEach(function (c) {
          var inp = c.querySelector('.combo-input');
          var hid = c.querySelector('input[type="hidden"]');
          if (!inp || !hid) return;
          if (hid.value !== '') return; // sudah ada pilihan valid
          var typed = inp.value.trim().toLowerCase();
          if (typed === '') return;
          var found = null;
          c.querySelectorAll('.combo-list li').forEach(function (li) {
            if (li.classList.contains('combo-no-result')) return;
            var lbl = (li.getAttribute('data-label') || '').toLowerCase();
            if (lbl === typed) found = li;
          });
          if (found) { hid.value = found.getAttribute('data-value') || ''; }
          else { inp.value = ''; }
        });
      });
    }
  });
})();