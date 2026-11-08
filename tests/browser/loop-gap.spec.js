const { test, expect } = require('@playwright/test');

async function seam(page) {
  return page.locator('.eew').evaluate(root => {
    const api = root.emblaApi;
    api.scrollTo(api.scrollSnapList().length - 1, true);
    const slides = api.slideNodes();
    const first = slides[0].getBoundingClientRect();
    const last = slides.at(-1).getBoundingClientRect();
    return root.classList.contains('eew--y') ? first.top - last.bottom :
      root.dir === 'rtl' ? last.left - first.right : first.left - last.right;
  });
}

for (const mode of ['loop', 'loop-rtl', 'loop-y']) {
  test(`last-to-first spacing matches ordinary gaps in ${mode}`, async ({ page }) => {
    await page.goto('/test?mode=' + mode);
    await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
    expect(await page.locator('.eew').evaluate(root => root.emblaApi.internalEngine().options.loop)).toBe(true);
    // Fractional slide widths accumulate subpixel rounding across the loop.
    await expect.poll(() => seam(page)).toBeCloseTo(20, 0);
    await page.locator('.eew__next').click();
    expect(await page.locator('.eew').evaluate(root => root.emblaApi.selectedScrollSnap())).toBe(0);
  });

  test(`custom seam gap is configurable independently in ${mode}`, async ({ page }) => {
    await page.goto('/test?mode=' + mode + '&loop_gap=48');
    await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
    await expect.poll(() => seam(page)).toBeCloseTo(48, 0);
    const ordinaryGap = await page.locator('.eew__container').evaluate(node => parseFloat(getComputedStyle(node).gap));
    expect(ordinaryGap).toBe(20);
  });
}

test('loop gap updates in the editor and allows an explicit zero', async ({ page }) => {
  await page.goto('/test?mode=loop&loop_gap=48');
  await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
  await page.evaluate(() => {
    const scope = document.querySelector('.elementor-widget');
    EEWTest.hooks['frontend/element_ready/eew-carousel.default']([scope]);
    scope.querySelector('.eew').style.setProperty('--eew-loop-gap', '0px');
    EEWTest.handlers[0].onElementChange('loop_gap');
  });
  await expect.poll(() => seam(page)).toBeCloseTo(0, 0);
  await page.setViewportSize({ width: 390, height: 844 });
  // Keep both boundary slides visible so their physical separation can be
  // measured. A single full-width slide need not wrap its offscreen neighbour.
  await page.evaluate(() => {
    document.querySelector('.eew').style.setProperty('--eew-per-view', '2');
    EEWTest.handlers[0].onElementChange('slides_per_view_mobile');
  });
  await expect.poll(() => seam(page)).toBeCloseTo(0, 0);
});

test('non-looped layouts do not add trailing spacing', async ({ page }) => {
  await page.goto('/test');
  await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
  const margin = await page.locator('.eew__slide').last().evaluate(node => getComputedStyle(node).marginInlineEnd);
  expect(margin).toBe('0px');
});

test('responsive seam changes refresh Embla when slide sizes stay fixed', async ({ page }) => {
  await page.goto('/test?mode=loop');
  await expect(page.locator('.eew')).toHaveAttribute('data-eew-ready', 'true');
  const beforeWidth = await page.locator('.eew__viewport').evaluate(node => node.clientWidth);
  await expect.poll(() => seam(page)).toBeCloseTo(20, 0);
  await page.addStyleTag({ content: '@media(max-width:1150px){.eew{--eew-loop-gap:48px}}' });
  await page.setViewportSize({ width: 1100, height: 900 });
  expect(await page.locator('.eew__viewport').evaluate(node => node.clientWidth)).toBe(beforeWidth);
  await expect.poll(() => seam(page)).toBeCloseTo(48, 0);
});
