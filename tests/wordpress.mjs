import assert from 'node:assert/strict';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { runCLI } from '@wp-playground/cli';
import { chromium } from '@playwright/test';

const root = path.resolve(import.meta.dirname, '..');
const mounts = [{ hostPath: path.join(root, 'dist/sobelz-embla-carousel-widget'), vfsPath: '/wordpress/wp-content/plugins/sobelz-embla-carousel-widget' }];
if (process.env.EEW_ELEMENTOR_PRO_DIR) mounts.push({ hostPath: path.resolve(process.env.EEW_ELEMENTOR_PRO_DIR), vfsPath: '/wordpress/wp-content/plugins/elementor-pro' });
const steps = [
  { step: 'installPlugin', pluginData: { resource: 'url', url: 'https://downloads.wordpress.org/plugin/elementor.latest-stable.zip' }, options: { activate: true } },
  ...(process.env.EEW_ELEMENTOR_PRO_DIR ? [{ step: 'activatePlugin', pluginPath: 'elementor-pro/elementor-pro.php' }] : []),
  { step: 'activatePlugin', pluginPath: 'sobelz-embla-carousel-widget/sobelz-embla-carousel-widget.php' },
  { step: 'writeFile', path: '/wordpress/wp-content/mu-plugins/eew-test-fixtures.php', data: readFileSync(path.join(root, 'tests/wordpress-fixtures.php'), 'utf8') },
];
const server = await runCLI({
  command: 'server', php: process.env.EEW_TEST_PHP || '8.3', wp: process.env.EEW_TEST_WP || 'latest',
  port: 9407, workers: 1, verbosity: 'normal',
  mount: mounts,
  blueprint: { steps },
});

let browser;
try {
  await server.playground.writeFile('/wordpress/wp-content/eew-release-manifest.json', readFileSync(path.join(root, 'update.json'), 'utf8'));
  const updater = await server.playground.run({ code: readFileSync(path.join(root, 'tests/wordpress-updater.php'), 'utf8') }).catch(error => { throw new Error(error.message); });
  if (updater.errors) console.log(updater.errors);
  const updaterResult = new TextDecoder().decode(updater.bytes);
  console.log(updaterResult);
  assert.equal(JSON.parse(updaterResult).updater, 'passed');
  await server.playground.writeFile('/wordpress/wp-content/eew-query-tests.php', readFileSync(path.join(root, 'tests/wordpress-query.php'), 'utf8'));
  const result = await server.playground.run({ code: readFileSync(path.join(root, 'tests/wordpress-setup.php'), 'utf8') }).catch(error => { throw new Error(error.message); });
  if (result.errors) console.log(result.errors);
  const text = new TextDecoder().decode(result.bytes);
  console.log(text);
  const setup = JSON.parse(text);
  assert.equal(setup.ok, true);
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const requests = [];
  page.on('request', request => { if (request.url().includes('/assets/vendor/')) requests.push(request.url()); });
  for (const name of ['plain', 'basic', 'autoplay', 'fade', 'vertical', 'template', 'query', 'query_hook', 'loop_item']) {
    console.log('Checking WordPress page:', name);
    requests.length = 0;
    await page.goto(server.serverUrl + '/?page_id=' + setup.pages[name], { waitUntil: 'networkidle' });
    if (name === 'plain') {
      assert.equal(requests.length, 0, 'No Embla assets on pages without the widget');
      continue;
    }
    await page.waitForSelector('.eew[data-eew-ready="true"]', { timeout: 15000 }).catch(async error => {
      mkdirSync(path.join(root, '.playground'), { recursive: true });
      writeFileSync(path.join(root, '.playground', name + '.html'), await page.content());
      console.log({ errors, requests, scripts: await page.locator('script[src]').evaluateAll(nodes => nodes.map(n => n.src)), body: (await page.locator('body').innerText()).slice(0, 1000) });
      throw error;
    });
    const data = await page.locator('.eew').first().evaluate(root => ({
      plugins: Object.keys(root.emblaApi.plugins()), snaps: root.emblaApi.scrollSnapList().length,
      direction: root.getAttribute('dir'), text: root.textContent,
      selected: root.emblaApi.selectedScrollSnap(),
    }));
    assert.ok(data.snaps >= 1);
    assert.ok(requests.every(url => new URL(url).origin === new URL(server.serverUrl).origin));
    if (name === 'basic') {
      assert.deepEqual(data.plugins, []);
      assert.equal(requests.length, 1, 'Only core loads for a basic widget');
      await page.locator('.eew__next').click();
      assert.equal(await page.locator('.eew').evaluate(root => root.emblaApi.selectedScrollSnap()), 1);
    }
    if (name === 'autoplay') {
      assert.deepEqual(data.plugins, ['autoplay']);
      assert.equal(requests.length, 2, 'Core and autoplay only');
      assert.equal(await page.locator('.eew').evaluate(root => root.emblaApi.plugins().autoplay.isPlaying()), true);
    }
    if (name === 'fade') {
      assert.deepEqual(data.plugins.sort(), ['autoHeight', 'autoplay', 'classNames', 'fade', 'wheelGestures'].sort());
    }
    if (name === 'vertical') {
      assert.deepEqual(data.plugins, ['autoScroll']);
      assert.equal(data.direction, 'rtl');
    }
    if (name === 'template') assert.ok(data.text.includes('Saved template content'));
    if (['query', 'query_hook', 'loop_item'].includes(name)) {
      const ids = await page.locator('.eew__slide[data-post-id]').evaluateAll(nodes => nodes.map(node => Number(node.dataset.postId)));
      assert.deepEqual(ids, name === 'query_hook' ? setup.queries.featured : setup.queries.events);
      if (name === 'loop_item') {
        const itemIds = await page.locator('.eew-test-post').evaluateAll(nodes => nodes.map(node => Number(node.dataset.itemId)));
        assert.deepEqual(itemIds, setup.queries.events, 'Template renders a distinct current post for each item');
      }
    }
    console.log('WordPress browser check:', name, data.plugins.join(', ') || 'core');
  }
  assert.deepEqual(errors, [], 'No browser errors');
  console.log(`Passed real WordPress ${setup.wordpress}, Elementor ${setup.elementor}, PHP ${setup.php} integration tests.`);
  console.log(setup.queries.pro ? 'Elementor Pro tested: ' + setup.queries.pro : 'Loop Item contract tested with a test document; Elementor Pro itself was not installed.');
} finally {
  if (browser) await browser.close();
  await server[Symbol.asyncDispose]();
}
