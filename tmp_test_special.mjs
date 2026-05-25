import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const TOURNAMENT_ID = 4;

async function screenshot(page, name) {
    const path = `C:/Users/hugob/Desktop/Prono2000/tmp_screenshot_${name}.png`;
    await page.screenshot({ path, fullPage: false });
    console.log(`  📸 ${name}`);
}

async function login(page, email, password = 'password') {
    await page.goto(`${BASE}/login`);
    await page.waitForSelector('input[type="email"]', { timeout: 10000 });
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard|tournaments/, { timeout: 10000 });
    console.log(`  ✅ Logged in as ${email}`);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const errors = [];
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await ctx.newPage();

    // ── LOGIN ──
    console.log('\n=== Login ===');
    try {
        await login(page, 'test@example.com');
    } catch (e) {
        console.error(`Login failed: ${e.message}`);
        await screenshot(page, 'login_error');
        await browser.close();
        process.exit(1);
    }

    // ── TEST 1: Special Pronos page ──
    console.log('\n=== TEST 1: Page Pronos Spéciaux ===');
    await page.goto(`${BASE}/tournaments/${TOURNAMENT_ID}/special-pronos`);
    await page.waitForLoadState('networkidle');
    await screenshot(page, '01_special_pronos');

    const title = await page.locator('h1').first().textContent().catch(() => '');
    console.log(`  Titre: "${title}"`);
    if (title.includes('Pronos Spéciaux')) {
        console.log('  ✅ Page chargée');
    } else {
        errors.push('❌ Page Pronos Spéciaux introuvable');
        await screenshot(page, '01_error');
    }

    // Check sections
    const headings = await page.locator('h2').allTextContents().catch(() => []);
    console.log(`  Sections: ${headings.join(' | ')}`);
    if (headings.some(h => h.includes('Doublé'))) console.log('  ✅ Section Doublés présente');
    else errors.push('❌ Section Doublés manquante');
    if (headings.some(h => h.includes('Bloqué'))) console.log('  ✅ Section Bloqués présente');
    else errors.push('❌ Section Bloqués manquante');
    if (headings.some(h => h.includes('Échangé'))) console.log('  ✅ Section Échangés présente');
    else errors.push('❌ Section Échangés manquante');

    // ── TEST 2: Prono Doublé toggle ──
    console.log('\n=== TEST 2: Prono Doublé ===');
    const doublerBtns = await page.locator('button:has-text("Doubler")').all();
    console.log(`  Boutons "Doubler" trouvés: ${doublerBtns.length}`);

    if (doublerBtns.length > 0) {
        await doublerBtns[0].click();
        await page.waitForTimeout(1000);
        await screenshot(page, '02_after_double');

        const activeDouble = await page.locator('button:has-text("Actif ✓")').count();
        if (activeDouble > 0) {
            console.log('  ✅ Prono doublé activé');
            // Désactiver
            await page.locator('button:has-text("Actif ✓")').first().click();
            await page.waitForTimeout(600);
            console.log('  ✅ Prono doublé désactivé');
        } else {
            errors.push('❌ Le bouton double ne s\'est pas activé');
            await screenshot(page, '02_error');
        }
    } else {
        console.log('  ⚠️  Aucun bouton Doubler visible (pas de prono déposé sur ces matchs ?)');
    }

    // ── TEST 3: Prono Bloqué ──
    console.log('\n=== TEST 3: Prono Bloqué ===');
    const blockSelects = await page.locator('select').all();
    console.log(`  Selects trouvés: ${blockSelects.length}`);

    if (blockSelects.length > 0) {
        const opts = await blockSelects[0].locator('option').allTextContents();
        console.log(`  Options: ${opts.slice(0, 3).join(' | ')}...`);

        if (opts.length > 1) {
            await blockSelects[0].selectOption({ index: 1 });
            await page.waitForTimeout(200);
            const blockBtn = page.locator('button:has-text("Bloquer")').first();
            const btnVisible = await blockBtn.isVisible().catch(() => false);
            if (btnVisible) {
                await blockBtn.click();
                await page.waitForTimeout(1000);
                await screenshot(page, '03_after_block');
                const active = await page.locator('text=Bloc actif').count();
                if (active > 0) {
                    console.log('  ✅ Bloc activé');
                } else {
                    errors.push('❌ Bloc non activé');
                    await screenshot(page, '03_error');
                }
            } else {
                errors.push('❌ Bouton Bloquer non visible');
            }
        } else {
            console.log('  ⚠️  Pas de matchs ouverts pour bloquer');
        }
    } else {
        errors.push('❌ Aucun select trouvé pour les blocs');
    }

    // ── TEST 4: Prono Échangé ──
    console.log('\n=== TEST 4: Prono Échangé ===');
    // Les selects de swap sont dans la grille 2 colonnes
    const swapSection = page.locator('text=Pronos Échangés').locator('..');
    const swapSelects = await page.locator('.grid.grid-cols-2 select').all();
    console.log(`  Selects d'échange trouvés: ${swapSelects.length}`);

    if (swapSelects.length >= 2) {
        const myOpts = await swapSelects[0].locator('option').allTextContents();
        const theirOpts = await swapSelects[1].locator('option').allTextContents();
        console.log(`  Mon prono options: ${myOpts.slice(0,2).join(' | ')}`);
        console.log(`  Leur prono options: ${theirOpts.slice(0,2).join(' | ')}`);

        if (myOpts.length > 1 && theirOpts.length > 1) {
            await swapSelects[0].selectOption({ index: 1 });
            await swapSelects[1].selectOption({ index: 2 < theirOpts.length ? 2 : 1 });
            await page.waitForTimeout(200);
            const confirmBtn = page.locator('button:has-text("Confirmer")').first();
            if (await confirmBtn.isVisible()) {
                await confirmBtn.click();
                await page.waitForTimeout(1000);
                await screenshot(page, '04_after_swap');
                const active = await page.locator('text=Échange actif').count();
                if (active > 0) {
                    console.log('  ✅ Échange activé');
                } else {
                    errors.push('❌ Échange non activé');
                    await screenshot(page, '04_error');
                }
            }
        } else {
            console.log('  ⚠️  Pas assez d\'options dans les selects');
        }
    } else {
        console.log('  ⚠️  Selects d\'échange non trouvés');
    }

    // ── TEST 5: Match.vue – Double toggle ──
    console.log('\n=== TEST 5: Match.vue – Prono Doublé ===');
    await page.goto(`${BASE}/predictions/match/52`);
    await page.waitForLoadState('networkidle');
    await screenshot(page, '05_match_vue');

    const hasDoubleSection = await page.locator('text=Prono Doublé').count();
    const has6pts = await page.locator('text=6 points').count();
    const has2pts = await page.locator('text=2 points').count();

    if (hasDoubleSection > 0) console.log('  ✅ Section "Prono Doublé" visible');
    else errors.push('❌ Section "Prono Doublé" absente');

    if (has6pts > 0) console.log('  ✅ Affichage "6 points" correct');
    else errors.push('❌ "6 points" non affiché');

    if (has2pts > 0) console.log('  ✅ Affichage "2 points" correct');
    else errors.push('❌ "2 points" non affiché');

    await screenshot(page, '05_match_vue_final');

    // ── RÉSUMÉ ──
    console.log('\n=== RÉSUMÉ ===');
    if (errors.length === 0) {
        console.log('✅ Tous les tests passés !');
    } else {
        console.log(`${errors.length} problème(s) :`);
        errors.forEach(e => console.log(`  ${e}`));
    }

    await ctx.close();
    await browser.close();
})();
