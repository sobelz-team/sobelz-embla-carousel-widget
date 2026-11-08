import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const keys = ['autoplay', 'autoScroll', 'autoHeight', 'classNames', 'fade', 'wheelGestures'];
const assets = Object.fromEntries(keys.map(key => {
  const name = key === 'autoScroll' ? 'auto-scroll' : key === 'autoHeight' ? 'auto-height' : key === 'classNames' ? 'class-names' : key === 'wheelGestures' ? 'wheel-gestures' : key;
  return [key, '/assets/vendor/embla-carousel-' + name + '/embla-carousel-' + name + '.umd.js'];
}));
const labels = { goTo: 'Go to slide group %d', status: 'Slide group %1$d of %2$d', play: 'Start automatic rotation', pause: 'Pause automatic rotation', error: 'Carousel failed' };

function carousel(mode, suffix = '', loopGap = null) {
  const options = { align: 'start', axis: ['vertical', 'loop-y'].includes(mode) ? 'y' : 'x', direction: ['rtl', 'loop-rtl'].includes(mode) ? 'rtl' : 'ltr', loop: mode === 'autoplay' || mode.startsWith('loop'), duration: 0, slidesToScroll: mode === 'grouped' ? 'auto' : 1 };
  const plugins = {};
  if (mode === 'autoplay' || mode === 'editor') plugins.autoplay = { delay: 600, stopOnMouseEnter: true, stopOnInteraction: false, stopOnFocusIn: true };
  if (mode === 'vertical') plugins.autoScroll = { speed: 1, startDelay: 50, stopOnInteraction: false };
  if (mode === 'fade') Object.assign(plugins, { fade: {}, autoHeight: {}, classNames: {}, wheelGestures: {} });
  const config = { options, plugins, keyboard: true, respectReducedMotion: true, labels };
  const encoded = JSON.stringify(config).replace(/'/g, '&#39;');
  const slides = Array.from({ length: 6 }, (_, i) => '<div class="eew__slide" role="group"><article class="eew__card"><div class="eew__placeholder">0' + (i + 1) + '</div><div class="eew__body"><h3 class="eew__title">Slide ' + (i + 1) + '</h3><p>Slide content</p><a href="#example">Example</a></div></article></div>').join('');
  const style = loopGap === null ? '' : `style="--eew-loop-gap:${Math.max(0, Math.min(200, Number(loopGap)))}px"`;
  return `<div class="elementor-widget" id="widget${suffix}"><div class="eew eew--${options.axis} ${options.loop ? 'eew--loop' : ''} ${mode === 'fade' ? 'eew--fade eew--auto-height' : ''}" dir="${options.direction}" ${style} data-eew-config='${encoded}'>
    <div class="eew__viewport" id="vp${suffix}" tabindex="0"><div class="eew__container">${slides}</div></div>
    <div class="eew__controls" hidden><div class="eew__arrows"><button class="eew__arrow eew__prev" aria-label="Previous">←</button><button class="eew__arrow eew__next" aria-label="Next">→</button></div><div class="eew__dots"></div>${plugins.autoplay || plugins.autoScroll ? '<button class="eew__toggle" type="button"></button>' : ''}</div>
    <div class="eew__progress" hidden><div class="eew__progress-bar"></div></div><p class="eew__status eew__sr-only" aria-live="polite"></p>
  </div></div>`;
}

http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (url.pathname === '/test') {
    const mode = url.searchParams.get('mode') || 'basic';
    const multiple = url.searchParams.has('multiple');
    const loopGap = url.searchParams.get('loop_gap');
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    response.end(`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/carousel.css"><style>body{margin:40px;font-family:sans-serif}.eew{max-width:960px;margin:0 auto 40px}</style></head><body>${carousel(mode, '', loopGap)}${multiple ? carousel(mode, 'two', loopGap) : ''}
      <script>window.EEWAssets=${JSON.stringify({ plugins: assets })};
      window.EEWTest={hooks:{},handlers:[]};
      window.elementorModules={frontend:{handlers:{Base:class { constructor(options){this.$element=options.$element;this.onInit()}onInit(){}onDestroy(){} }}}};
      window.elementorFrontend={isEditMode:()=>${mode === 'editor'},hooks:{addAction:(name,fn)=>{EEWTest.hooks[name]=fn}},elementsHandler:{addHandler:(Class,options)=>{const handler=new Class(options);EEWTest.handlers.push(handler);return handler}}};
      ${mode === 'early' ? 'window.elementorFrontend = {};' : ''}
      </script><script src="/assets/vendor/embla-carousel/embla-carousel.umd.js"></script><script src="/assets/js/carousel.js"></script></body></html>`);
    return;
  }
  const candidate = path.resolve(root, '.' + url.pathname);
  if (!candidate.startsWith(path.join(root, 'assets') + '/') || !fs.existsSync(candidate) || !fs.statSync(candidate).isFile()) {
    response.writeHead(404).end();
    return;
  }
  response.setHeader('Content-Type', candidate.endsWith('.js') ? 'application/javascript' : candidate.endsWith('.css') ? 'text/css' : 'application/json');
  response.end(fs.readFileSync(candidate));
}).listen(9409, '127.0.0.1');
