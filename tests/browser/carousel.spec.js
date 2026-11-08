const { test, expect } = require('@playwright/test');

async function ready(page, query = '') {
  await page.goto('/test' + query);
  await expect(page.locator('.eew').first()).toHaveAttribute('data-eew-ready', 'true');
}
async function selected(page) { return page.locator('.eew').first().evaluate(root => root.emblaApi.selectedScrollSnap()); }
async function playing(page) { return page.locator('.eew').first().evaluate(root => root.emblaApi.plugins().autoplay.isPlaying()); }

test('basic widget uses core only and navigation updates groups', async ({ page }) => {
  const scripts = [];
  const errors = [];
  page.on('request', request => { if (request.url().includes('/assets/vendor/')) scripts.push(request.url()); });
  page.on('pageerror', error => errors.push(error.message));
  await ready(page);
  expect(scripts).toHaveLength(1);
  expect(await page.locator('.eew').evaluate(root => Object.keys(root.emblaApi.plugins()))).toEqual([]);
  await expect(page.locator('.eew__dot')).toHaveCount(4);
  await expect(page.locator('.eew__prev')).toBeDisabled();
  await page.locator('.eew__next').click();
  expect(await selected(page)).toBe(1);
  await expect(page.locator('.eew__dot').nth(1)).toHaveAttribute('aria-current', 'true');
  await page.locator('.eew__viewport').focus();
  await page.keyboard.press('End');
  expect(await selected(page)).toBe(3);
  await expect(page.locator('.eew__next')).toBeDisabled();
  await expect(page.locator('.eew__progress')).toHaveAttribute('aria-valuenow', '100');
  expect(errors).toEqual([]);
});

test('initialization tolerates Elementor frontend before its hooks are ready', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await ready(page, '?mode=early');
  await page.locator('.eew__next').click();
  expect(await selected(page)).toBe(1);
  expect(errors).toEqual([]);
});

test('autoplay is locally loaded once for multiple independent widgets', async ({ page }) => {
  const scripts = [];
  page.on('request', request => { if (request.url().includes('/assets/vendor/')) scripts.push(request.url()); });
  await ready(page, '?mode=autoplay&multiple');
  await expect(page.locator('.eew[data-eew-ready="true"]')).toHaveCount(2);
  expect(scripts).toHaveLength(2);
  expect(scripts.every(url => url.startsWith('http://127.0.0.1:9409/'))).toBe(true);
  expect(await playing(page)).toBe(true);
  await expect.poll(() => selected(page), { timeout: 3000 }).toBeGreaterThan(0);
  await page.locator('.eew__toggle').first().click();
  expect(await playing(page)).toBe(false);
  await page.mouse.move(0, 0);
  await page.locator('.eew__viewport').first().hover();
  await page.mouse.move(0, 0);
  expect(await playing(page)).toBe(false);
  await expect(page.locator('.eew__toggle').first()).toHaveText('Start automatic rotation');
  expect(await page.locator('.eew').nth(1).evaluate(root => root.emblaApi.plugins().autoplay.isPlaying())).toBe(true);
});

test('hover pauses and leaving resumes autoplay with accurate labels', async ({ page }) => {
  await ready(page, '?mode=autoplay');
  await page.locator('.eew__placeholder').first().hover();
  await expect.poll(() => playing(page)).toBe(false);
  await expect(page.locator('.eew__toggle')).toHaveText('Start automatic rotation');
  await page.mouse.move(0, 0);
  await expect.poll(() => playing(page)).toBe(true);
  await expect(page.locator('.eew__toggle')).toHaveText('Pause automatic rotation');
});

test('RTL keyboard and responsive regrouping follow Embla', async ({ page }) => {
  await ready(page, '?mode=rtl');
  await page.locator('.eew__viewport').focus();
  await page.keyboard.press('ArrowLeft');
  expect(await selected(page)).toBe(1);
  await page.keyboard.press('ArrowRight');
  expect(await selected(page)).toBe(0);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.eew__dot')).toHaveCount(6);
  await page.locator('.eew__next').click();
  expect(await selected(page)).toBe(1);
});

test('grouped dots are rebuilt on viewport changes', async ({ page }) => {
  await ready(page, '?mode=grouped');
  await expect(page.locator('.eew__dot')).toHaveCount(2);
  await page.setViewportSize({ width: 800, height: 900 });
  await expect(page.locator('.eew__dot')).toHaveCount(3);
  await page.locator('.eew__dot').last().click();
  expect(await selected(page)).toBe(2);
});

test('reduced motion blocks autoplay including internal hover resumes', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await ready(page, '?mode=autoplay');
  expect(await playing(page)).toBe(false);
  await expect(page.locator('.eew__toggle')).toBeDisabled();
  await page.locator('.eew__placeholder').first().hover();
  await page.mouse.move(0, 0);
  await expect.poll(() => playing(page)).toBe(false);
  await page.locator('.eew__next').click();
  expect(await selected(page)).toBe(1);
});

test('fade, auto height, class names and wheel gestures register together', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await ready(page, '?mode=fade');
  expect(await page.locator('.eew').evaluate(root => Object.keys(root.emblaApi.plugins()).sort())).toEqual(['autoHeight', 'classNames', 'fade', 'wheelGestures']);
  await expect(page.locator('.eew__dot')).toHaveCount(6);
  await page.locator('.eew__next').click();
  expect(await selected(page)).toBe(1);
  await expect(page.locator('.eew__slide').nth(1)).toHaveClass(/is-snapped/);
  expect(errors).toEqual([]);
});

test('vertical auto scroll works', async ({ page }) => {
  await ready(page, '?mode=vertical');
  expect(await page.locator('.eew').evaluate(root => Object.keys(root.emblaApi.plugins()))).toEqual(['autoScroll']);
  await expect.poll(() => page.locator('.eew').evaluate(root => root.emblaApi.scrollProgress())).toBeGreaterThan(0);
  await page.locator('.eew__viewport').focus();
  await page.keyboard.press('End');
  expect(await selected(page)).toBe(3);
});

test('Elementor destroy and rerender clean up the old instance', async ({ page }) => {
  await ready(page, '?mode=editor');
  expect(await playing(page)).toBe(false);
  await page.evaluate(() => {
    const scope = document.querySelector('.elementor-widget');
    const hook = EEWTest.hooks['frontend/element_ready/eew-carousel.default'];
    hook([scope]);
    window.oldApi = scope.querySelector('.eew').emblaApi;
    EEWTest.handlers[0].onDestroy();
    const root = scope.querySelector('.eew');
    const config = JSON.parse(root.dataset.eewConfig);
    config.plugins = { fade: {} };
    root.dataset.eewConfig = JSON.stringify(config);
    hook([scope]);
  });
  await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
  expect(await page.locator('.eew').evaluate(root => Object.keys(root.emblaApi.plugins()))).toEqual(['fade']);
  expect(await page.locator('.eew').evaluate(root => root.emblaApi !== window.oldApi)).toBe(true);
});
