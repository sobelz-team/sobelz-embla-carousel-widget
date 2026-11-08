(function () {
  'use strict';

  const instances = new WeakMap();
  const pendingScripts = new Map();
  let nextViewportId = 0;
  const factories = {
    autoplay: 'EmblaCarouselAutoplay',
    autoScroll: 'EmblaCarouselAutoScroll',
    autoHeight: 'EmblaCarouselAutoHeight',
    fade: 'EmblaCarouselFade',
    classNames: 'EmblaCarouselClassNames',
    wheelGestures: 'EmblaCarouselWheelGestures',
  };

  function loadPlugin(name) {
    const factory = factories[name];
    if (typeof window[factory] === 'function') return Promise.resolve();
    if (pendingScripts.has(name)) return pendingScripts.get(name);

    // Elementor can enable a new plugin in its preview after PHP dependencies
    // have already been printed. This fallback still uses the bundled local file.
    const promise = new Promise(function (resolve, reject) {
      const src = window.EEWAssets && window.EEWAssets.plugins[name];
      if (!factory || !src) {
        reject(new Error('Missing local Embla plugin: ' + name));
        return;
      }
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.dataset.eewPlugin = name;
      script.onload = function () {
        if (typeof window[factory] === 'function') resolve();
        else reject(new Error('Invalid Embla plugin: ' + name));
      };
      script.onerror = function () {
        script.remove();
        reject(new Error('Could not load local Embla plugin: ' + name));
      };
      document.head.appendChild(script);
    }).catch(function (error) {
      pendingScripts.delete(name);
      throw error;
    });
    pendingScripts.set(name, promise);
    return promise;
  }

  function directChild(root, className) {
    return Array.from(root.children).find(function (node) {
      return node.classList.contains(className);
    });
  }

  function initialize(root) {
    if (instances.has(root)) return instances.get(root);

    let config;
    try {
      config = JSON.parse(root.dataset.eewConfig);
    } catch (error) {
      console.error('[Embla Elementor]', error);
      return null;
    }
    const viewport = directChild(root, 'eew__viewport');
    if (!viewport || !viewport.firstElementChild) return null;
    const controls = directChild(root, 'eew__controls');
    const dots = controls && controls.querySelector('.eew__dots');
    const previous = controls && controls.querySelector('.eew__prev');
    const next = controls && controls.querySelector('.eew__next');
    const toggle = controls && controls.querySelector('.eew__toggle');
    const progress = directChild(root, 'eew__progress');
    const status = directChild(root, 'eew__status');
    viewport.id = 'eew-runtime-viewport-' + (++nextViewportId);
    if (controls) controls.querySelectorAll('[aria-controls]').forEach(function (node) {
      node.setAttribute('aria-controls', viewport.id);
    });
    const cleanups = [];
    const labels = config.labels;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const editor = Boolean(window.elementorFrontend && typeof window.elementorFrontend.isEditMode === 'function' && window.elementorFrontend.isEditMode());
    let api = null;
    let destroyed = false;
    let userPaused = false;
    let wasPlayingWhenHidden = false;
    let dotButtons = [];
    let scrollFrame = 0;
    let spacingFrame = 0;
    let lastSpacing = '';

    function spacingSignature() {
      const container = viewport.firstElementChild;
      const slide = container.lastElementChild;
      const trackStyle = window.getComputedStyle(container);
      const endStyle = slide ? window.getComputedStyle(slide) : null;
      return [trackStyle.rowGap, trackStyle.columnGap,
        endStyle && endStyle.marginLeft, endStyle && endStyle.marginRight,
        endStyle && endStyle.marginBottom].join('|');
    }

    function listen(node, type, handler) {
      if (!node) return;
      node.addEventListener(type, handler);
      cleanups.push(function () { node.removeEventListener(type, handler); });
    }

    function motionPlugin() {
      if (!api) return null;
      return api.plugins().autoplay || api.plugins().autoScroll || null;
    }

    function motionBlocked() {
      return userPaused || editor || document.hidden || (config.respectReducedMotion && reducedMotion.matches);
    }

    function updateMotionUI() {
      const motion = motionPlugin();
      const playing = Boolean(motion && motion.isPlaying());
      if (status) status.setAttribute('aria-live', playing ? 'off' : 'polite');
      if (!toggle) return;
      toggle.hidden = !motion;
      toggle.disabled = editor || Boolean(config.respectReducedMotion && reducedMotion.matches);
      toggle.textContent = playing ? labels.pause : labels.play;
      toggle.setAttribute('aria-label', toggle.textContent);
    }

    function stopOrResetMotion() {
      const motion = motionPlugin();
      if (!motion) return;
      if (motion.options.stopOnInteraction) {
        userPaused = true;
        motion.stop();
      } else {
        motion.reset();
      }
      updateMotionUI();
    }

    function updateProgress() {
      scrollFrame = 0;
      if (!api || !progress) return;
      const count = api.scrollSnapList().length;
      const value = config.plugins.fade
        ? (count > 1 ? api.selectedScrollSnap() / (count - 1) : 1)
        : Math.max(0, Math.min(1, api.scrollProgress()));
      progress.style.setProperty('--eew-progress', String(value));
      progress.setAttribute('aria-valuenow', String(Math.round(value * 100)));
    }

    function scheduleProgress() {
      if (!scrollFrame) scrollFrame = window.requestAnimationFrame(updateProgress);
    }

    function updateSelection() {
      if (!api) return;
      const selected = api.selectedScrollSnap();
      if (previous) previous.disabled = !api.canScrollPrev();
      if (next) next.disabled = !api.canScrollNext();
      dotButtons.forEach(function (button, index) {
        button.classList.toggle('is-selected', selected === index);
        if (selected === index) button.setAttribute('aria-current', 'true');
        else button.removeAttribute('aria-current');
      });
      if (status) {
        status.textContent = labels.status.replace('%1$d', selected + 1).replace('%2$d', api.scrollSnapList().length);
      }
      updateProgress();
    }

    function buildDots() {
      if (!dots || !api) return;
      const fragment = document.createDocumentFragment();
      dotButtons = api.scrollSnapList().map(function (_, index) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eew__dot';
        button.setAttribute('aria-label', labels.goTo.replace('%d', index + 1));
        button.setAttribute('aria-controls', viewport.id);
        // These listeners belong to the replaced buttons and are GC'd on reInit.
        button.addEventListener('click', function () {
          api.scrollTo(index);
          stopOrResetMotion();
        });
        fragment.appendChild(button);
        return button;
      });
      dots.replaceChildren(fragment);
      dots.hidden = dotButtons.length < 2;
    }

    function enforceMotionPreference() {
      if (!api) return;
      const motion = motionPlugin();
      if (motion && motionBlocked()) motion.stop();
      updateMotionUI();
    }

    const controller = {
      get api() { return api; },
      destroy: function () {
        if (destroyed) return;
        destroyed = true;
        cleanups.splice(0).forEach(function (cleanup) { cleanup(); });
        window.cancelAnimationFrame(scrollFrame);
        window.cancelAnimationFrame(spacingFrame);
        if (api) api.destroy();
        api = null;
        delete root.emblaApi;
        delete root.dataset.eewReady;
        if (controls) controls.hidden = true;
        if (progress) progress.hidden = true;
        instances.delete(root);
      },
    };
    instances.set(root, controller);

    Promise.all(Object.keys(config.plugins).map(loadPlugin)).then(function () {
      if (destroyed || !root.isConnected) {
        controller.destroy();
        return;
      }
      const plugins = Object.entries(config.plugins).map(function (entry) {
        const name = entry[0];
        const options = Object.assign({}, entry[1]);
        if (name === 'autoplay' || name === 'autoScroll') {
          options.playOnInit = !motionBlocked();
          options.rootNode = function () { return viewport; };
        }
        return window[factories[name]](options);
      });
      const options = Object.assign({}, config.options);
      if (config.respectReducedMotion) {
        options.breakpoints = { '(prefers-reduced-motion: reduce)': { duration: 0 } };
      }
      api = window.EmblaCarousel(viewport, options, plugins);
      root.emblaApi = api;
      root.dataset.eewReady = 'true';
      root.classList.remove('eew--error');

      listen(previous, 'click', function () { api.scrollPrev(); stopOrResetMotion(); });
      listen(next, 'click', function () { api.scrollNext(); stopOrResetMotion(); });
      listen(toggle, 'click', function () {
        const motion = motionPlugin();
        if (!motion) return;
        userPaused = motion.isPlaying();
        if (userPaused) motion.stop();
        else if (!motionBlocked()) motion.play();
        updateMotionUI();
      });
      if (config.keyboard) {
        listen(viewport, 'keydown', function (event) {
          // A nested template can contain inputs or another carousel.
          if (event.target !== viewport || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
          const rtl = options.direction === 'rtl';
          const back = options.axis === 'y' ? 'ArrowUp' : (rtl ? 'ArrowRight' : 'ArrowLeft');
          const forward = options.axis === 'y' ? 'ArrowDown' : (rtl ? 'ArrowLeft' : 'ArrowRight');
          if (![back, forward, 'Home', 'End'].includes(event.key)) return;
          event.preventDefault();
          if (event.key === back) api.scrollPrev();
          else if (event.key === forward) api.scrollNext();
          else api.scrollTo(event.key === 'Home' ? 0 : api.scrollSnapList().length - 1);
          stopOrResetMotion();
        });
      }

      api.on('select', updateSelection).on('scroll', scheduleProgress);
      api.on('reInit', function () {
        lastSpacing = spacingSignature();
        buildDots();
        updateSelection();
        enforceMotionPreference();
      });
      ['autoplay:play', 'autoplay:stop', 'autoScroll:play', 'autoScroll:stop'].forEach(function (event) {
        api.on(event, function () {
          // Embla emits these events before updating isPlaying(). Read the
          // final state in a microtask, including after an internal resume.
          queueMicrotask(function () {
            if (destroyed) return;
            const motion = motionPlugin();
            if (motion && motion.isPlaying() && motionBlocked()) motion.stop();
            updateMotionUI();
          });
        });
      });
      api.on('pointerDown', function () {
        const motion = motionPlugin();
        if (motion && motion.options.stopOnInteraction) userPaused = true;
      });
      listen(document, 'visibilitychange', function () {
        const motion = motionPlugin();
        if (!motion) return;
        if (document.hidden) {
          wasPlayingWhenHidden = motion.isPlaying();
          motion.stop();
        } else if (wasPlayingWhenHidden && !motionBlocked()) {
          motion.play();
        }
        updateMotionUI();
      });
      const onPreference = function () { enforceMotionPreference(); };
      reducedMotion.addEventListener('change', onPreference);
      cleanups.push(function () { reducedMotion.removeEventListener('change', onPreference); });
      // A responsive gap can change while a max-width viewport and all slide
      // widths stay fixed. Embla's ResizeObserver cannot detect that case.
      lastSpacing = spacingSignature();
      listen(window, 'resize', function () {
        if (spacingFrame) return;
        spacingFrame = window.requestAnimationFrame(function () {
          spacingFrame = 0;
          if (api && spacingSignature() !== lastSpacing) api.reInit();
        });
      });
      buildDots();
      updateSelection();
      updateMotionUI();
      if (controls) controls.hidden = false;
      if (progress) progress.hidden = false;
      root.dispatchEvent(new CustomEvent('eew:ready', { bubbles: true, detail: { api: api } }));
    }).catch(function (error) {
      if (destroyed) return;
      controller.destroy();
      root.classList.add('eew--error');
      if (status) {
        status.classList.remove('eew__sr-only');
        status.textContent = labels.error;
      }
      console.error('[Embla Elementor]', error);
    });
    return controller;
  }

  let elementorAttached = false;
  function attachElementor() {
    if (elementorAttached || !window.elementorFrontend || !window.elementorFrontend.hooks ||
      !window.elementorFrontend.elementsHandler || !window.elementorModules ||
      !window.elementorModules.frontend || !window.elementorModules.frontend.handlers) return;
    elementorAttached = true;
    class CarouselHandler extends window.elementorModules.frontend.handlers.Base {
      onInit() {
        super.onInit();
        const root = this.$element[0].querySelector('.eew');
        this.carousel = root ? initialize(root) : null;
      }
      onElementChange(property) {
        // Gap-only CSS changes do not resize slides when one fills the view.
        // Refresh measurements after Elementor applies its responsive styles.
        if (/^(gap|loop_gap|slides_per_view|vertical_height)(_|$)/.test(property)) {
          window.cancelAnimationFrame(this.layoutFrame);
          this.layoutFrame = window.requestAnimationFrame(() => {
            if (this.carousel && this.carousel.api) this.carousel.api.reInit();
          });
        }
      }
      onDestroy() {
        window.cancelAnimationFrame(this.layoutFrame);
        if (this.carousel) this.carousel.destroy();
        super.onDestroy();
      }
    }
    window.elementorFrontend.hooks.addAction('frontend/element_ready/eew-carousel.default', function ($scope) {
      window.elementorFrontend.elementsHandler.addHandler(CarouselHandler, { $element: $scope });
    });
  }

  if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', attachElementor);
  attachElementor();
  function start() {
    attachElementor();
    document.querySelectorAll('.eew[data-eew-config]').forEach(initialize);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
