const { test, expect } = require('@playwright/test');

async function ready(page, query) {
  await page.goto('/test?' + query);
  await expect(page.locator('.eew').first()).toHaveAttribute('data-eew-ready', 'true');
}
async function looping(page) {
  return page.locator('.eew').first().evaluate(root => root.emblaApi.internalEngine().options.loop);
}
async function logicalIndex(page) {
  return page.locator('.eew').first().evaluate(root => Array.from(root.querySelectorAll('.eew__dot')).findIndex(dot => dot.hasAttribute('aria-current')));
}

for (const mode of ['loop', 'loop-rtl', 'loop-y']) {
  test(`three underfilled items repeat and keep three logical dots in ${mode}`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await ready(page, 'mode=' + mode + '&slides=3&view=5');
    expect(await looping(page)).toBe(true);
    await expect(page.locator('.eew__slide:not([data-eew-copy])')).toHaveCount(3);
    await expect(page.locator('.eew__dot')).toHaveCount(3);
    expect(await page.locator('.eew__slide').count()).toBeGreaterThan(5);
    for (let i = 1; i <= 10; i++) {
      await page.locator('.eew__next').click();
      expect(await logicalIndex(page)).toBe(i % 3);
      await expect(page.locator('.eew__prev')).toBeEnabled();
      await expect(page.locator('.eew__next')).toBeEnabled();
      const coverage = await page.locator('.eew').evaluate(root => {
        const vertical = root.classList.contains('eew--y');
        const view = root.querySelector('.eew__viewport').getBoundingClientRect();
        const start = vertical ? 'top' : 'left', end = vertical ? 'bottom' : 'right';
        const ranges = Array.from(root.querySelector('.eew__container').children).map(slide => slide.getBoundingClientRect()).filter(rect => rect[end] > view[start] && rect[start] < view[end]).sort((a, b) => a[start] - b[start]);
        return { leading: ranges[0][start] - view[start], trailing: view[end] - ranges.at(-1)[end], gap: Math.max(0, ...ranges.slice(1).map((rect, index) => rect[start] - ranges[index][end])) };
      });
      expect(coverage.leading).toBeLessThanOrEqual(1);
      expect(coverage.trailing).toBeLessThanOrEqual(1);
      expect(coverage.gap).toBeLessThanOrEqual(21);
    }
    await expect(page.locator('.eew__status')).toHaveText('Slide group 2 of 3');
    await page.locator('.eew__viewport').focus();
    await page.keyboard.press('End');
    expect(await logicalIndex(page)).toBe(2);
    await page.keyboard.press('Home');
    expect(await logicalIndex(page)).toBe(0);
    expect(errors).toEqual([]);
  });
}

test('responsive rebuild removes unnecessary copies and preserves the selected item', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5');
  const initial = await page.locator('.eew__slide').count();
  for (let i = 0; i < 4; i++) await page.locator('.eew__next').click();
  expect(await logicalIndex(page)).toBe(1);
  for (let i = 0; i < 3; i++) {
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('[data-eew-copy]')).toHaveCount(0);
    expect(await looping(page)).toBe(true);
    expect(await logicalIndex(page)).toBe(1);
    await page.setViewportSize({ width: 1280, height: 900 });
    await expect(page.locator('.eew__slide')).toHaveCount(initial);
    expect(await logicalIndex(page)).toBe(1);
  }
});

test('copies have unique references and remain outside the accessibility/tab order', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&rich&multiple');
  await expect(page.locator('.eew[data-eew-ready="true"]')).toHaveCount(2);
  const data = await page.evaluate(() => {
    const allIds = Array.from(document.querySelectorAll('[id]')).map(el => el.id);
    const copies = Array.from(document.querySelectorAll('[data-eew-copy]'));
    return {
      unique: new Set(allIds).size === allIds.length,
      hidden: copies.every(copy => copy.getAttribute('aria-hidden') === 'true'),
      tabs: copies.every(copy => Array.from(copy.querySelectorAll('a,input,button')).every(el => el.tabIndex === -1)),
      labels: copies.every(copy => copy.querySelector('label').htmlFor === copy.querySelector('input').id),
      svg: copies.every(copy => copy.querySelector('rect').getAttribute('fill') === 'url(#' + copy.querySelector('linearGradient').id + ')'),
      inline: copies.some(copy => copy.querySelector('[onclick]')),
    };
  });
  expect(data).toEqual({ unique: true, hidden: true, tabs: true, labels: true, svg: true, inline: false });
});

test('copy actions use the original live widget and mirror its updated content', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&rich');
  const copy = page.locator('[data-eew-copy="0"]').first();
  await copy.locator('.repeat-action').dispatchEvent('click');
  expect(await page.evaluate(() => window.eewClicks)).toBe(1);
  await expect(page.locator('.eew__slide:not([data-eew-copy])').first().locator('.repeat-action')).toHaveText('Clicked 1');
  await expect(copy.locator('.repeat-action')).toHaveText('Clicked 1');
  await copy.locator('input').focus();
  await expect(page.locator('#field0')).toBeFocused();
  await page.keyboard.press('End');
  await page.keyboard.type(' updated');
  await expect(copy.locator('input')).toHaveValue('Original 0 updated');
  await copy.locator('a').dispatchEvent('click');
  await expect.poll(() => page.evaluate(() => location.hash)).toBe('#example');
});

test('changed IDs and references stay unique in live mirrors', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&rich');
  await page.locator('.eew__slide:not([data-eew-copy])').first().evaluate(source => {
    source.querySelector('input').id = 'renamed-field';
    source.querySelector('label').htmlFor = 'renamed-field';
    source.querySelector('linearGradient').id = 'renamed-paint';
    source.querySelector('rect').setAttribute('fill', 'url(#renamed-paint)');
    source.querySelector('input').setAttribute('tabindex', '0');
    source.querySelector('input').removeAttribute('tabindex');
  });
  await expect.poll(() => page.locator('[data-eew-copy="0"]').first().evaluate(copy => copy.querySelector('label').htmlFor === copy.querySelector('input').id && copy.querySelector('rect').getAttribute('fill') === 'url(#' + copy.querySelector('linearGradient').id + ')')).toBe(true);
  await expect(page.locator('[data-eew-copy="0"]').first().locator('input')).toHaveAttribute('tabindex', '-1');
});

test('nested carousel mirrors share its live state and do not create more controllers', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await ready(page, 'mode=loop&slides=3&view=5&nested');
  await page.addStyleTag({ content: '.eew .eew{--eew-per-view:1}' });
  const original = page.locator('.eew__slide:not([data-eew-copy]) > .eew__card > .eew__body > .elementor-widget > .eew').first();
  const mirrored = page.locator('[data-eew-copy="0"]').first().locator('.eew');
  await expect(original).toHaveAttribute('data-eew-ready', 'true');
  await expect(original.locator('.eew__next')).toBeEnabled();
  expect(await page.locator('.eew').evaluateAll(roots => roots.filter(root => root.emblaApi).length)).toBe(2);
  await mirrored.locator('.eew__next').dispatchEvent('click');
  expect(await original.evaluate(root => root.emblaApi.selectedScrollSnap())).toBe(1);
  const transform = await original.locator('.eew__container').evaluate(track => track.style.transform);
  await expect.poll(() => mirrored.locator('.eew__container').evaluate(track => track.style.transform)).toBe(transform);
  expect(await mirrored.evaluate(root => Boolean(root.emblaApi))).toBe(false);
  expect(errors).toEqual([]);
});

test('editor destruction removes copies and reattachment does not accumulate them', async ({ page }) => {
  await ready(page, 'mode=editor&loop&slides=3&view=5');
  const count = await page.locator('.eew__slide').count();
  for (let i = 0; i < 2; i++) {
    await page.evaluate(() => {
      EEWTest.hooks['frontend/element_ready/eew-carousel.default']([document.querySelector('.elementor-widget')]);
      EEWTest.handlers.at(-1).onDestroy();
    });
    await expect(page.locator('.eew__slide')).toHaveCount(3);
    await expect(page.locator('[data-eew-loop-end]')).toHaveCount(0);
    await page.evaluate(() => EEWTest.hooks['frontend/element_ready/eew-carousel.default']([document.querySelector('.elementor-widget')]));
    await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
    await expect(page.locator('.eew__slide')).toHaveCount(count);
    expect(await looping(page)).toBe(true);
  }
});

test('single-item loops repeat while non-loop and Fade layouts keep their original items', async ({ page }) => {
  await ready(page, 'mode=loop&slides=1&view=5');
  expect(await looping(page)).toBe(true);
  await expect(page.locator('.eew__dot')).toHaveCount(1);
  await expect(page.locator('.eew__dots')).toBeHidden();
  await page.locator('.eew__next').click();
  await expect(page.locator('.eew__next')).toBeEnabled();
  await ready(page, 'slides=3&view=5');
  await expect(page.locator('[data-eew-copy]')).toHaveCount(0);
  await ready(page, 'mode=fade&loop&slides=2&view=5');
  await expect(page.locator('[data-eew-copy]')).toHaveCount(0);
  await expect(page.locator('.eew__dot')).toHaveCount(2);
});

test('auto grouping and large fixed groups retain useful logical navigation', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&group=auto');
  expect(await looping(page)).toBe(true);
  const dots = await page.locator('.eew__dot').count();
  expect(dots).toBeGreaterThan(1);
  expect(dots).toBeLessThanOrEqual(3);
  await page.locator('.eew__dot').last().click();
  expect(await logicalIndex(page)).toBe(dots - 1);
  await ready(page, 'mode=loop&slides=3&view=5&group=20');
  expect(await looping(page)).toBe(true);
  await expect(page.locator('.eew__dot')).toHaveCount(3);
  expect(await page.locator('.eew__slide').count()).toBeLessThanOrEqual(123);
  await page.locator('.eew__next').click();
  expect(await logicalIndex(page)).toBe(1);
});

test('hidden initialization and live source changes rebuild a bounded repeat set', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&hidden');
  await expect(page.locator('[data-eew-copy]')).toHaveCount(0);
  await page.locator('.eew').evaluate(root => { root.style.display = 'block'; });
  await expect.poll(() => looping(page)).toBe(true);
  await expect.poll(() => page.locator('[data-eew-copy]').count()).toBeGreaterThan(0);
  await page.locator('.eew__slide:not([data-eew-copy])').first().evaluate(slide => slide.remove());
  await expect(page.locator('.eew')).toHaveAttribute('data-eew-original-count', '2');
  await expect(page.locator('.eew__dot')).toHaveCount(2);
  expect(await looping(page)).toBe(true);
});

test('continuous auto scroll works with an underfilled loop', async ({ page }) => {
  await ready(page, 'mode=loop&slides=3&view=5&motion=auto-scroll');
  expect(await looping(page)).toBe(true);
  await expect.poll(() => page.locator('.eew').evaluate(root => root.emblaApi.scrollProgress())).toBeGreaterThan(0.01);
  await page.locator('.eew__toggle').click();
  expect(await page.locator('.eew').evaluate(root => root.emblaApi.plugins().autoScroll.isPlaying())).toBe(false);
});

for (const gap of [0, 48]) {
  test(`repeated source boundaries respect a ${gap}px custom seam`, async ({ page }) => {
    await ready(page, 'mode=loop&slides=3&view=5&loop_gap=' + gap);
    const measured = await page.locator('.eew').evaluate(root => {
      const slides = Array.from(root.querySelector('.eew__container').children);
      return slides[3].getBoundingClientRect().left - slides[2].getBoundingClientRect().right;
    });
    expect(measured).toBeCloseTo(gap, 0);
    expect(await looping(page)).toBe(true);
  });
}
