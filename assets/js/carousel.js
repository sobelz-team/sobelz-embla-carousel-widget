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

  // Repeat presentation only. Keep one live DOM/widget instance per real item;
  // mirrors share its state and forward actions instead of starting more widgets.
  function createLoopFiller(root, viewport, config, activateOriginal) {
    const track = viewport.firstElementChild;
    const copySources = new WeakMap();
    const focusable = 'a[href],area[href],button,input,select,textarea,iframe,summary,audio[controls],video[controls],[tabindex],[contenteditable]';
    const references = ['for', 'form', 'list', 'headers', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'aria-activedescendant', 'aria-details', 'aria-errormessage'];
    const maxExtraSlides = 120;
    let sources = [];
    let copies = [];
    let cycles = 1;
    let minimumCycles = 1;
    let lastGeometry = '';
    let serial = 0;
    let disposed = false;

    function sourceSlides() {
      return Array.from(track.children).filter(node => !node.hasAttribute('data-eew-copy'));
    }

    function geometry() {
      const slides = sourceSlides();
      const vertical = config.options.axis === 'y';
      const style = getComputedStyle(track);
      const last = track.lastElementChild;
      const end = last && getComputedStyle(last);
      const gap = parseFloat(vertical ? style.rowGap : style.columnGap) || 0;
      const seam = end ? parseFloat(vertical ? end.marginBottom : config.options.direction === 'rtl' ? end.marginLeft : end.marginRight) || 0 : gap;
      const size = vertical ? viewport.clientHeight : viewport.clientWidth;
      const sizes = slides.map(slide => vertical ? slide.offsetHeight : slide.offsetWidth);
      const cross = config.plugins.autoHeight ? slides.map(slide => slide.offsetHeight) : [];
      return { slides, size, sizes, gap, seam, signature: [size, sizes.join(','), cross.join(','), gap, seam].join('|') };
    }

    function rewrite(value, meta) {
      return value.replace(/url\((['"]?)#([^)'"\s]+)\1\)/g, (all, quote, id) => meta.ids.has(id) ? 'url(' + quote + '#' + meta.prefix + id + quote + ')' : all);
    }

    function attribute(source, copy, name, meta) {
      if (name === 'data-element_type' || name === 'data-model-cid' || name === 'data-eew-config' || /^on/i.test(name) || name === 'autofocus' || (copy.matches('audio,video') && name === 'autoplay') || (copy.matches('iframe') && (name === 'src' || name === 'srcdoc'))) {
        copy.removeAttribute(name);
        return;
      }
      if (!source.hasAttribute(name)) {
        copy.removeAttribute(name);
        if (copy.matches(focusable)) copy.setAttribute('tabindex', '-1');
        return;
      }
      let value = source.getAttribute(name);
      if (name === 'id' || name === 'data-id') value = meta.prefix + value;
      // Keep .elementor-ID and all scoped styling classes, while preventing
      // Elementor's document bootstrap from attaching controllers to mirrors.
      else if (name === 'class') value = value.split(/\s+/).filter(token => token !== 'elementor' && token !== 'elementor-element-edit-mode').join(' ');
      else if (references.includes(name)) value = value.split(/\s+/).map(id => meta.ids.has(id) ? meta.prefix + id : id).join(' ');
      else if ((name === 'href' || name === 'xlink:href') && value[0] === '#' && meta.ids.has(value.slice(1))) value = '#' + meta.prefix + value.slice(1);
      copy.setAttribute(name, rewrite(value, meta));
      if (copy.matches(focusable)) copy.setAttribute('tabindex', '-1');
    }

    function pairNodes(source, copy, meta) {
      meta.nodes.set(source, copy);
      copySources.set(copy, source);
      Array.from(source.childNodes).forEach((child, index) => pairNodes(child, copy.childNodes[index], meta));
      if (copy.nodeType !== 1) return;
      Array.from(source.attributes).forEach(attr => attribute(source, copy, attr.name, meta));
      if (copy.matches(focusable)) copy.setAttribute('tabindex', '-1');
      // Loading another embedded browsing context or playing another media
      // instance is not part of repeating a presentation.
      if (copy.matches('audio,video')) { copy.removeAttribute('autoplay'); copy.setAttribute('preload', 'none'); }
      if (copy.matches('iframe')) { copy.removeAttribute('src'); copy.removeAttribute('srcdoc'); }
    }

    function makeCopy(source, index) {
      const node = source.cloneNode(true);
      const meta = { source, node, nodes: new WeakMap(), prefix: viewport.id + '-copy-' + (++serial) + '-', ids: new Set(Array.from(source.querySelectorAll('[id]')).map(el => el.id)) };
      if (source.id) meta.ids.add(source.id);
      pairNodes(source, node, meta);
      node.querySelectorAll('script').forEach(script => script.remove());
      node.style.removeProperty('transform');
      node.setAttribute('data-eew-copy', String(index));
      node.setAttribute('aria-hidden', 'true');
      node.removeAttribute('aria-label');
      node.removeAttribute('data-post-id');
      node.removeAttribute('data-term-id');
      return meta;
    }

    function gcd(a, b) { return b ? gcd(b, a % b) : a; }
    function unit() {
      const group = Number(config.options.slidesToScroll);
      return Number.isFinite(group) && group > 1 ? group / gcd(sources.length, group) : 1;
    }
    function allowed(data) {
      return config.options.loop && !config.plugins.fade && data.slides.length && data.size > 0 && data.sizes.every(size => size > 0);
    }
    function setCycles(count) {
      copies.forEach(meta => meta.node.remove());
      copies = [];
      sources.forEach(source => source.removeAttribute('data-eew-loop-end'));
      cycles = count;
      const fragment = document.createDocumentFragment();
      for (let cycle = 1; cycle < cycles; cycle++) {
        sources.forEach((source, index) => {
          const meta = makeCopy(source, index);
          copies.push(meta);
          fragment.appendChild(meta.node);
        });
      }
      track.appendChild(fragment);
      if (copies.length) {
        [sources, ...Array.from({ length: cycles - 1 }, (_, cycle) => copies.slice(cycle * sources.length, (cycle + 1) * sources.length).map(meta => meta.node))].forEach(group => group.at(-1).setAttribute('data-eew-loop-end', ''));
      }
      root.dataset.eewOriginalCount = String(sources.length);
      root.dataset.eewCopyCount = String(copies.length);
    }

    function prepare() {
      const data = geometry();
      const changedSources = data.slides.length !== sources.length || data.slides.some((slide, index) => slide !== sources[index]);
      if (data.signature !== lastGeometry || changedSources) minimumCycles = 1;
      sources = data.slides;
      let count = 1;
      if (allowed(data)) {
        const span = data.sizes.reduce((sum, size) => sum + size, 0) + Math.max(0, sources.length - 1) * data.gap + data.seam;
        const reserve = Math.max(...data.sizes) + Math.max(data.gap, data.seam) + data.gap;
        if (span > 0) count = Math.max(1, Math.ceil((data.size + reserve) / span));
        const group = Number(config.options.slidesToScroll);
        if (Number.isFinite(group)) count = Math.max(count, Math.ceil((group + 1) / sources.length));
        if (count > 1) count = Math.ceil(count / unit()) * unit();
        count = Math.min(Math.max(count, minimumCycles), 1 + Math.floor(maxExtraSlides / sources.length));
      }
      const changed = changedSources || count !== cycles;
      if (changed) setCycles(count);
      lastGeometry = geometry().signature;
      return changed;
    }

    function original(slide) {
      return slide && slide.hasAttribute('data-eew-copy') ? sources[Number(slide.dataset.eewCopy)] : slide;
    }

    function syncInputs(source) {
      if (!source || !source.matches('input,select,textarea')) return;
      copies.forEach(meta => {
        const copy = meta.nodes.get(source);
        if (!copy) return;
        if (source.matches('input')) copy.checked = source.checked;
        copy.value = source.value;
        if (source.matches('select')) copy.selectedIndex = source.selectedIndex;
      });
    }

    function copyRoot(target) {
      let node = target;
      while (node && node.parentElement !== track) node = node.parentElement;
      return node && node.hasAttribute('data-eew-copy') ? node : null;
    }

    function click(event) {
      const copy = copyRoot(event.target);
      if (!copy) return;
      const source = copySources.get(event.target);
      if (!source) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      if (source.disabled) return;
      source.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window, button: event.button, buttons: event.buttons, ctrlKey: event.ctrlKey, shiftKey: event.shiftKey, altKey: event.altKey, metaKey: event.metaKey }));
      queueMicrotask(() => { if (!disposed) syncInputs(source); });
    }
    function focus(event) {
      const copy = copyRoot(event.target);
      if (!copy) return;
      const source = copySources.get(event.target);
      if (source && source.focus) {
        activateOriginal(copySources.get(copy));
        source.focus({ preventScroll: true });
      }
    }
    const input = event => syncInputs(event.target);
    track.addEventListener('click', click, true);
    track.addEventListener('focusin', focus, true);
    track.addEventListener('input', input);
    track.addEventListener('change', input);

    // Mirror widget state without copying listeners, starting another form or
    // creating more Elementor/nested carousel controllers.
    const observer = new MutationObserver(records => {
      records.forEach(record => {
        if (!sources.some(source => source === record.target || source.contains(record.target))) return;
        if (record.type === 'attributes' && sources.includes(record.target)) return;
        copies.forEach(meta => {
          const copy = meta.nodes.get(record.target);
          if (!copy) return;
          if (record.type === 'characterData') copy.textContent = record.target.textContent;
          else if (record.type === 'attributes') {
            if (record.attributeName === 'id') {
              meta.ids = new Set(Array.from(meta.source.querySelectorAll('[id]')).map(el => el.id));
              if (meta.source.id) meta.ids.add(meta.source.id);
              meta.source.querySelectorAll('*').forEach(source => {
                const mirrored = meta.nodes.get(source);
                if (!mirrored) return;
                Array.from(source.attributes).filter(attr => references.includes(attr.name) || attr.name === 'href' || attr.name === 'xlink:href' || attr.value.includes('url(')).forEach(attr => attribute(source, mirrored, attr.name, meta));
              });
            }
            attribute(record.target, copy, record.attributeName, meta);
          }
          else {
            meta.ids = new Set(Array.from(meta.source.querySelectorAll('[id]')).map(el => el.id));
            if (meta.source.id) meta.ids.add(meta.source.id);
            const children = Array.from(record.target.childNodes).map(child => {
              const mirrored = child.cloneNode(true);
              pairNodes(child, mirrored, meta);
              return mirrored;
            }).filter(child => child.nodeType !== 1 || child.tagName !== 'SCRIPT');
            copy.replaceChildren(...children);
            copy.querySelectorAll('script').forEach(script => script.remove());
          }
        });
      });
    });
    observer.observe(track, { subtree: true, attributes: true, childList: true, characterData: true });

    return {
      prepare,
      original,
      get repeated() { return copies.length > 0; },
      get cycles() { return cycles; },
      changed: () => geometry().signature !== lastGeometry || sourceSlides().some((slide, index) => slide !== sources[index]) || sourceSlides().length !== sources.length,
      grow: function () {
        if (!allowed(geometry()) || (cycles + unit() - 1) * sources.length > maxExtraSlides) return false;
        minimumCycles = cycles + unit();
        setCycles(minimumCycles);
        lastGeometry = geometry().signature;
        return true;
      },
      destroy: function () {
        disposed = true;
        observer.disconnect();
        track.removeEventListener('click', click, true);
        track.removeEventListener('focusin', focus, true);
        track.removeEventListener('input', input);
        track.removeEventListener('change', input);
        copies.forEach(meta => meta.node.remove());
        sources.forEach(source => source.removeAttribute('data-eew-loop-end'));
        copies = [];
        delete root.dataset.eewCopyCount;
        delete root.dataset.eewOriginalCount;
      },
    };
  }

  function initialize(root) {
    if (root.closest('[data-eew-copy]')) return null;
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
    let refreshing = false;
    const filler = createLoopFiller(root, viewport, config, function (source) {
      if (!api) return;
      const index = api.slideNodes().indexOf(source);
      const registry = api.internalEngine().slideRegistry;
      const snap = registry.findIndex(group => group.includes(index));
      if (snap >= 0) api.scrollTo(snap, true);
    });

    function navigation() {
      const snaps = api.scrollSnapList();
      if (!filler.repeated) return { groups: snaps.map((_, index) => [index]), selected: api.selectedScrollSnap() };
      // Read the grouping calculated by the pinned Embla version, including
      // auto grouping. Never replace or mutate its engine/scroll calculations.
      const registry = api.internalEngine().slideRegistry;
      const nodes = api.slideNodes();
      const keys = [];
      const groups = [];
      registry.forEach((group, index) => {
        const key = filler.original(nodes[group[0]]);
        let logical = keys.indexOf(key);
        if (logical < 0) { logical = keys.length; keys.push(key); groups.push([]); }
        groups[logical].push(index);
      });
      return { groups, selected: Math.max(0, groups.findIndex(group => group.includes(api.selectedScrollSnap()))) };
    }

    function goToGroup(index) {
      const group = navigation().groups[index];
      if (!group || !group.length) return;
      const snaps = api.scrollSnapList();
      const current = snaps[api.selectedScrollSnap()];
      const distance = target => { const delta = Math.abs(snaps[target] - current); return Math.min(delta, Math.abs(1 - delta)); };
      api.scrollTo(group.reduce((best, candidate) => distance(candidate) < distance(best) ? candidate : best, group[0]));
    }

    function refreshLayout() {
      spacingFrame = 0;
      if (destroyed || !api || refreshing) return;
      refreshing = true;
      try {
        const registry = api.internalEngine().slideRegistry;
        const group = registry[api.selectedScrollSnap()] || [];
        const selectedSource = filler.original(api.slideNodes()[group[0]]);
        const changed = filler.prepare();
        api.reInit();
        // Measurement rounds to CSS pixels. If a boundary still misses Embla's
        // loop condition, add one complete period, with a strict DOM bound.
        for (let attempt = 0; config.options.loop && !config.plugins.fade && !api.internalEngine().options.loop && attempt < 3 && filler.grow(); attempt++) api.reInit();
        if (changed && selectedSource) {
          const sourceIndex = api.slideNodes().indexOf(selectedSource);
          const snap = api.internalEngine().slideRegistry.findIndex(indices => indices.includes(sourceIndex));
          if (snap >= 0) api.scrollTo(snap, true);
        }
        lastSpacing = spacingSignature();
      } finally {
        refreshing = false;
      }
    }

    function scheduleLayout() {
      if (!destroyed && !spacingFrame) spacingFrame = window.requestAnimationFrame(refreshLayout);
    }

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
      const count = navigation().groups.length;
      const value = filler.repeated
        ? (count < 2 ? 1 : ((api.scrollProgress() * filler.cycles) % 1 + 1) % 1)
        : config.plugins.fade
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
      const state = navigation();
      const selected = state.selected;
      if (previous) previous.disabled = !api.canScrollPrev();
      if (next) next.disabled = !api.canScrollNext();
      dotButtons.forEach(function (button, index) {
        button.classList.toggle('is-selected', selected === index);
        if (selected === index) button.setAttribute('aria-current', 'true');
        else button.removeAttribute('aria-current');
      });
      if (status) {
        status.textContent = labels.status.replace('%1$d', selected + 1).replace('%2$d', state.groups.length);
      }
      updateProgress();
    }

    function buildDots() {
      if (!dots || !api) return;
      const fragment = document.createDocumentFragment();
      dotButtons = navigation().groups.map(function (_, index) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eew__dot';
        button.setAttribute('aria-label', labels.goTo.replace('%d', index + 1));
        button.setAttribute('aria-controls', viewport.id);
        // These listeners belong to the replaced buttons and are GC'd on reInit.
        button.addEventListener('click', function () {
          goToGroup(index);
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
      refresh: scheduleLayout,
      destroy: function () {
        if (destroyed) return;
        destroyed = true;
        cleanups.splice(0).forEach(function (cleanup) { cleanup(); });
        window.cancelAnimationFrame(scrollFrame);
        window.cancelAnimationFrame(spacingFrame);
        if (api) api.destroy();
        filler.destroy();
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
      filler.prepare();
      options.watchResize = function () { if (filler.changed()) scheduleLayout(); return false; };
      options.watchSlides = function () { if (filler.changed()) scheduleLayout(); return false; };
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
          else goToGroup(event.key === 'Home' ? 0 : navigation().groups.length - 1);
          stopOrResetMotion();
        });
      }

      api.on('select', updateSelection).on('scroll', scheduleProgress);
      api.on('reInit', function () {
        lastSpacing = spacingSignature();
        buildDots();
        updateSelection();
        enforceMotionPreference();
        if (!refreshing && filler.changed()) scheduleLayout();
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
        if (filler.changed() || spacingSignature() !== lastSpacing) scheduleLayout();
      });
      refreshLayout();
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
            if (this.carousel) this.carousel.refresh();
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
