# UI & 3D Step 0 Brief

**Status:** Steps 1–3 implemented on `codex/storefront-3d`; development checkpoint complete, production release still gated by the acceptance items below

**Prepared:** 2026-09-10

**Authoritative model source:** `desk_lamp_viewer_final_final.html`

**Source SHA-256:** `32f54a79d4a3e6fe96fb8ec207c7c299bb9e15cf8df6ef11cc900dcfdf0639d6`

> Step 1 correction (2026-09-26): the file supplied in this repository differs from the artifact inspected for the original brief. Its verified size and embedded geometry are recorded below. The supplied file is authoritative for this implementation and remains byte-for-byte unchanged.

This brief expands the UI direction beyond the homepage hero while keeping delivery in bounded checkpoints. Step 0 does not authorize implementation, dependency changes, catalog edits, hosting work, or deployment. The authoritative viewer must remain unchanged; derived assets should be reproducible from it.

## 1. Inspection findings

### Viewer and geometry

- The supplied source is a 25,691,587-byte standalone HTML document, not GLB/glTF and not Three.js. It embeds one base64 model payload and uses a hand-written WebGL 1 renderer, shaders, camera math, input handling, and part transforms.
- Its base64 vertex payload decodes to a 19,234,224-byte interleaved `Float32Array`: position XYZ followed by normal XYZ, with a 24-byte stride.
- Geometry is drawn as 801,426 non-indexed vertices (267,142 triangles) in 172 named parts. Part metadata supplies draw ranges, base colours, emissive flags, and pivots. The renderer derives functional categories from part names.
- The observed model includes the clamp and moving screw/pad, paired lower and upper rails, elbow and pivot hardware, four deforming coil springs with eyes, articulated light head and diffuser, inline four-button controller, external leads, and USB connector.
- Movement is implemented by explicit matrices, not a standard skeletal animation: lower arm, upper arm, neck bend, head roll, base swivel, and clamp opening. Six pose presets, part focus/isolation, anatomy labels, and exploded offsets are also custom code.
- The light head has four simulated colour modes (`6000 K`, `4500 K`, `3000 K`, and off) and a ten-position brightness input. These are shader colour/intensity changes, not measured photometric output.
- The black/white control recolours selected dark painted parts in the shader. It is a visual finish preview, not a second geometry asset and not evidence of a saleable option.
- A headless software-WebGL render was inspected. In the normal connected pose, a black lead visibly exits the rear of the clamp, loops through the controller, and continues to the USB plug. Code inspection confirms two cable paths: `Cable.USB_lead` in the static model and a procedural tube made by `updateWires()` for the base-to-controller segment. A presentation profile must omit both cable geometries while retaining the controller, USB connector, clamp, springs, articulation, and head. It should not depend only on the initial value of `state.wires`.
- The current renderer calls `requestAnimationFrame` continuously even when its `dirty` flag prevents a redraw. It does not pause for an offscreen canvas or hidden tab.
- The canvas is full-screen with `touch-action: none`, captures pointers, and cancels every wheel event. This is acceptable for a standalone inspector but would trap touch/wheel navigation in a storefront. There are no keyboard camera controls.
- WebGL failure currently leaves only a status sentence. There is no loading progress, matching poster, timeout/retry path, context-loss handling, or visual fallback.
- The standalone viewer currently combines presentation, camera input, articulation, and engineering inspection in one page. Storefront integration should reuse its geometry and transform logic through two explicit capability modes rather than embedding the standalone UI: a non-interactive rotating `showcase` mode for featured-product cards and an `interactive-showroom` mode for the associated product description page.

### Measured extraction opportunity

The following are local measurements of the authoritative source, not estimated production transfer sizes:

| Payload | Uncompressed | Gzip level 9 | Brotli quality 11 |
|---|---:|---:|---:|
| Supplied complete HTML | 25,691,587 B | Not re-measured | Not re-measured |
| Extracted binary vertex/normal buffer | 19,234,224 B | 4,383,679 B | 1,826,012 B |
| Extracted part/pivot metadata JSON | 27,988 B | Not measured | Not measured |
| Model-matched WebP poster (1200×960) | 5,172 B | Not applicable | Not applicable |

Separating the binary removes the base64 expansion from the initial document and allows the small commerce shell and poster to render before model fetch/decode. Brotli reduced the raw vertex buffer to about 1.67 MiB in this local compression test. Actual browser transfer depends on the eventual server/CDN content-encoding configuration and must be measured over HTTP.

### Current storefront and catalog boundary

- The homepage currently selects up to three `published()` and featured products, then treats the first result as the hero. There is no stable product-to-model association.
- The read-only local SQLite catalog contains one active featured product: `Long Arm Mechanical LED Desk Lamp`, slug `series-x-articulated-lamp`, price BDT 2,350.00, stock 5, no discount, no SKU, and no variants. The viewer calls itself `LAS Desk Lamp` and `LED Swing-Arm Desk Lamp`. Similar appearance is not sufficient proof that these identities are the same saleable record.
- Product media already supports up to ten photos per product in the write service. The inspected local product has one 1500×1500 JPEG. There is no model manifest/path field, dedicated viewer poster, or set of ten real product photos.
- The product has a nullable `variants` JSON field, but cart items contain only product and quantity, and order snapshots contain product name, SKU, price, and quantity. No selected colour flows through cart, checkout, stock, or order history. The hero control must therefore say **Preview finish** and **Visual preview only**; it must not act like a purchasable variant selector. Saleable colour selection is a separate commerce/schema checkpoint if the business requires it.
- Reviews contain `product_id`, optional `user_id`, rating, text, and `is_approved`. There is no order-item relationship or purchase-verification field. Approved reviews must be labelled **Customer Reviews** (or **Approved Reviews** internally), never **Verified Buyers**.
- The layout already names Manrope and DM Mono, but downloads them from Google Fonts at runtime. The Vite output contains only local Instrument Sans files. Manrope and DM Mono need locally licensed font files and pipeline integration before external font calls can be removed.
- The storefront applies a fixed full-screen scanline and a broad full-screen grid. The proposed system removes the scanline and limits a much fainter grid/radial treatment to the product stage.
- Customer-facing copy is inconsistent: most templates use `MAAT TECHNOLOGIE BD`, while one About sentence uses `Maat Technologies BD`. Proposed copy should use **MAAT Technologies BD**, but legal/registered-name and contact ownership must be confirmed before a later replacement pass.
- Current pages contain commerce claims that should not be promoted into the new UI without business confirmation: cart shipping is hard-coded at BDT 120 while checkout calculates BDT 80/140; delivery estimates, a 12-month warranty, seven-day returns, bulk discounts, and a two-hour contact response are expressed elsewhere. The UI must render current product price, discount, and stock from the published record, but policy/contact claims require verification and source ownership.
- Bengali support has no translation catalog or localized content model. It is a separate localization task, not a decorative language toggle.

## 2. Concrete two-mode experience proposal

The target balance is **70% premium e-commerce clarity and 30% engineering interface**.

### Mode 1: homepage featured-product showcase

1. Use a restrained 64–72 px navigation bar with the MAAT Technologies BD mark, **Shop Lamps**, **Your Cart**, wishlist, and account controls. All primary touch/click targets are at least 44×44 px.
2. Place one above-the-fold hero in a roughly 42/58 split:
   - **Commerce column:** published product name, optional SKU in DM Mono, short verified description, live database price/discount, honest stock state, **View Product**, and **Shop Lamps**. Keep quantity and purchase controls on the product description page. Do not show delivery, warranty, return, material, or range claims unless their source has been approved.
   - **Product stage:** the real LED Swing-Arm desk lamp fills the larger panel on a dark graphite-to-matte-charcoal surface with restrained teal accents, metallic hardware highlights, and a soft local halo around the illuminated head. No full-screen scanline; use only a low-contrast grid fragment behind the model.
3. Paint a model-matched poster immediately, then automatically load the initial visible model without an activation click. Cross-fade to a stationary or slowly rotating canvas only after the first successful frame. Keep the poster on timeout, WebGL failure, context loss, or model error.
4. Show the published product's real name and current price with a clear **View Product** link. The showcase image/stage may also link to the same product description page, but it must have one clear keyboard-focusable link target and must not place nested links over purchase controls.
5. Treat the homepage model as display media, not a miniature configurator. Disable pointer camera input and expose no dragging, zooming, finish/light controls, articulation sliders, cable toggle, or engineering panel. The canvas is decorative (`pointer-events: none` and hidden from the accessibility tree); the associated product link supplies keyboard navigation and an accessible name.
6. Rotate slowly only when motion is allowed. With `prefers-reduced-motion: reduce`, render a stationary first frame. Pause rotation and animation scheduling whenever the showcase is offscreen or `document.hidden`, and resume only while it is visible and motion remains allowed.
7. Keep the external cable geometry hidden in the showcase presentation profile while preserving articulation-ready geometry, clamp, controller, springs, hardware, head, and exploded metadata for the product-page mode.
8. On mobile, keep the stage to a bounded aspect ratio and allow ordinary vertical scrolling over it. Because the homepage canvas has no gesture handling, it must never capture a pointer or cancel touch/wheel navigation.
9. Follow the hero with a simple **Shop Lamps** product grid and a short photo-led craft/engineering section. Technical labels and DM Mono remain secondary decoration, not the main navigation language.

### Mode 2: product-description interactive showroom

1. Automatically load the same product-linked model over its matching poster. Keep product name, current price/discount, stock, quantity, **Add to Cart**, and the real-photo gallery available alongside the viewer; essential commerce must not depend on WebGL.
2. Let customers orbit, zoom, reset the view, preview black/white finish, select the simulated light colour, and choose **Level 1–5**. Map the five visual levels to renderer values 2, 4, 6, 8, and 10 to preserve its current maximum and evenly sample the simulation. Label finish changes **Visual preview only** until saleable variants are implemented, and make no lumen claim.
3. If the showroom begins with a slow rotation, stop it permanently for that page view as soon as the customer uses any viewer control, pointer gesture, wheel/zoom action, or keyboard camera command. Reduced-motion starts stationary.
4. Put joint controls, pose presets, clamp motion, controller close-up, spring/hardware focus, anatomy labels, isolation, exploded anatomy, and optional cable inspection inside **Explore Engineering View**. Keep the external black wire hidden in the normal showroom presentation.
5. Use `touch-action: pan-y`, a deliberate horizontal drag threshold, and bounded pointer capture so the canvas does not trap mobile scrolling. Do not cancel ordinary page wheel events; provide 44 px zoom/reset controls and keyboard equivalents.
6. On mobile, order content as identity/price, bounded viewer, purchase controls, real-photo gallery, then collapsed specifications and engineering tools. Add the later planned sticky purchase bar without covering viewer controls or status messages.

### Reusable product-linked component

- Implement one viewer component with explicit `showcase` and `interactive-showroom` modes. Both consume the same product-specific manifest, poster, renderer, visibility profile, and accessible status contract; mode selects capabilities rather than duplicating renderer code.
- Resolve each component's manifest association through its own `Product::published()` record and generate its **View Product** URL from that record. Never hardcode every showcase to the LAS slug or reuse the LAS destination for another product.
- A featured product without a valid model/poster manifest uses its normal published product image and the same product link. Missing or failed 3D must not remove identity, price, or navigation.
- Load the initially visible featured showcase automatically. For additional featured products, start model fetch as each showcase approaches the viewport using a bounded `IntersectionObserver` root margin. Do not fetch every catalog model at page load, and abort obsolete requests during navigation.
- Use Manrope for headings, navigation, buttons, and body text; use DM Mono only for SKU, compact measurements, state readouts, and decorative engineering labels. Keep every interactive target at least 44 px, body copy at 16 px, and supporting copy at 14 px.

## 3. Model extraction and optimization approach

### Faithful first extraction

1. Keep `desk_lamp_viewer final final.html` untouched and record its SHA-256 in an extraction manifest.
2. Build a reproducible extractor that emits:
   - a hashed binary file containing the existing interleaved float buffer;
   - a small JSON manifest containing part ranges, pivots, categories, emissive flags, presentation visibility, product association, and source hash; and
   - a Vite-managed ES module adapted from the existing renderer and transform code.
3. Preserve part names and transform behavior so clamp motion, coupled rails, spring deformation, controller detail, light head, presets, and exploded anatomy remain testable. Define `showcase`, normal showroom `presentation`, and expanded `engineering` capability/visibility profiles; the first two exclude the static and procedural external leads.
4. Do not make GLB conversion the first milestone. A later proof may compare indexed/quantized GLB or another compact representation, but it is acceptable only if it preserves named-part draw ranges, pivots, custom spring deformation, articulation, finish preview, light state, controller detail, and exploded offsets without visual regression.

### Optimization gates

- Serve the binary and metadata as fingerprinted static assets with long-lived cache headers and Brotli/gzip where the eventual host supports them. Fetch only the manifest assigned to the current published product.
- After a fidelity baseline is captured, test exact vertex deduplication/indexing, position/normal quantization, and part-aware simplification in that order. Preserve hard edges and thin springs; compare the study, reach, tall, low, wide, folded, controller, clamp, head, and exploded views against reference images before accepting an optimization.
- Generate a responsive poster from the same approved model, pose, finish, light state, camera, and background used by the first frame. Keep the real photo as gallery content; do not present a mismatched photo as the renderer's exact loading frame.
- Load the page shell and poster first. Automatically fetch the initial visible showcase or current product's model; fetch later featured models only as their showcases approach the viewport. Never preload models for ordinary product cards or the whole catalog. Abort obsolete fetches during navigation.
- Render on demand when static. Run animation frames only while the model is changing or auto-rotation is active. Pause on `document.hidden`, pause while offscreen through `IntersectionObserver`, and restore cleanly after visibility/context return.
- In showroom mode, add keyboard orbit, zoom, reset, focus order, and readable status announcements. In showcase mode, keep the canvas non-interactive and make the product link keyboard-accessible. Essential shopping actions always remain outside the canvas. Respect `prefers-reduced-motion` by disabling rotation and cross-fade motion.
- Preserve page navigation on touch and wheel input. Pointer capture must be released on cancel, visibility change, and lost context.

### Verification evidence to collect during implementation

- Automated extraction integrity: source hash, buffer byte length, part count/ranges, finite values, and expected named parts/pivots.
- Homepage evidence for automatic initial loading, approach-to-viewport loading of later showcases, slow rotation, stationary reduced-motion state, offscreen/hidden-tab pause, non-interactive canvas behavior, correct product destination, image-only fallback, and preserved mobile scrolling.
- Product-page golden screenshots for every pose, black/white visual preview, four light modes, five exposed light levels, focused controller/clamp/head, and exploded anatomy—with external leads absent from normal presentation.
- Product-page interaction tests for orbit/zoom, keyboard controls, mobile scrolling, automatic-rotation cancellation after first interaction, collapsed engineering controls, and continued access to product/purchase information during WebGL failure.
- Loading, malformed asset, timeout, WebGL-unavailable, context-loss, reduced-motion, keyboard, focus, and mobile-scroll tests.
- Per-page network evidence proving only one model is requested, with uncompressed and transferred bytes recorded separately.
- Poster LCP, model-ready time, first interaction latency, long tasks, memory, and frame-time percentiles. Label Lighthouse/DevTools runs as emulation. Record real-device browser, device, network, and thermal conditions separately; emulation is not real-device evidence.

## 4. Bounded implementation sequence

1. **Homepage featured showcase.** Confirm the LAS product identity; introduce the reusable product/manifest association; extract the binary/metadata and shared custom renderer; create the matching poster; and implement the automatically loaded, slowly rotating, non-interactive showcase with correct **View Product** navigation, reduced-motion stationary state, visibility pausing, cable suppression, product-image fallback, approach-to-viewport loading, and focused homepage tests. Keep product-description interaction and all other pages unchanged.
2. **Product-description interactive showroom.** Reuse the component in `interactive-showroom` mode beside the real-photo gallery and current price/stock/quantity/purchase controls. Add orbit/zoom, finish and five-level light previews, first-interaction rotation cancellation, a mobile sticky purchase bar, and collapsible specifications/**Explore Engineering View** controls for articulation and exploded anatomy. Ensure a catalog of roughly ten products and ten photos each never loads every model simultaneously. Correct familiar customer labels and review wording in this bounded commerce pass; plan saleable colour variants separately if approved.
3. **Visual polish and verification.** Apply the 70/30 graphite/matte/teal system consistently, remove customer-facing scanlines, localize grid effects, self-host Manrope/DM Mono through Vite, normalize labels such as **Your Cart** and **Place Order — Cash on Delivery**, and run accessibility, transfer, interaction, responsive, fallback, emulated, and real-device verification. Handle Bengali only in a later localization checkpoint.

## 5. Missing assets and business decisions

1. **Canonical LAS identity:** confirm whether the viewer represents product ID 1 / `series-x-articulated-lamp`, approve its customer-facing name, and assign a stable SKU/model identifier. Do not infer this from similar geometry.
2. **Model provenance:** confirm ownership/licensing and permission to publish and derive optimized assets from the authoritative HTML.
3. **Poster and photography:** approve the default pose/finish/light/camera and produce responsive poster outputs. Only one real product photo is present; provide or approve the intended gallery set and alt-text facts.
4. **Physical lighting contract:** confirm the five physical levels and the real colour-temperature labels. The proposed 1–5-to-2/4/6/8/10 mapping is only a renderer normalization, not a performance specification.
5. **Saleable colours:** decide whether black and white are purchasable SKUs/options with independent stock. Current carts and order records cannot retain a colour selection; until a separate commerce change is approved, finish switching remains clearly labelled visual preview only.
6. **Verified commerce facts:** nominate the source of truth and owner for delivery charges/estimates, stock, discounts, warranty, return/refund terms, materials, dimensions, articulation limits, and contact response promises. Resolve the current BDT 120 cart fee versus BDT 80/140 checkout calculation before reusing delivery copy.
7. **Review terminology:** decide whether purchase verification will be built later. Until then, use **Customer Reviews**, because approval alone does not prove purchase.
8. **Brand/legal copy:** confirm that **MAAT Technologies BD** is the approved customer-facing and legal/trading name. Existing `MAAT TECHNOLOGIE BD` strings conflict and should be inventoried before a bounded replacement.
9. **Fonts:** provide approved, web-licensed Manrope and DM Mono files (prefer WOFF2) or approve a package/source for bundling through the existing Vite pipeline.
10. **Device evidence:** nominate the minimum real mobile devices/browsers and network profiles for acceptance. Desktop software-WebGL inspection and emulation do not substitute for those results.

## 6. Step 1 implementation record (2026-09-26)

- `scripts/extract-desk-lamp-model.mjs` performs a reproducible, fail-closed extraction. It verifies the supplied source hash and byte size, finite interleaved floats, part ranges, 172-part count, required mechanisms, and all nine pivots before writing anything.
- The extractor emitted fingerprinted `desk-lamp.a45a18eea9197981.bin` and `desk-lamp.de824f25cf33f9e6.json` assets. The manifest records the source hash plus explicit `showcase`, `presentation`, and `engineering` visibility profiles. `showcase` and `presentation` hide every `Cable.` part; the homepage renderer does not create the source viewer's procedural lead.
- `resources/js/desk-lamp-renderer.js` preserves the supplied renderer's geometry ranges, materials, pivots, coupled arm transforms, spring deformation, clamp position, head transforms, and presentation camera. Homepage capability is intentionally limited to slow automatic rotation. There are no pointer, touch, wheel, zoom, articulation, lighting, or engineering handlers.
- `ProductShowcaseRegistry`, `config/product-showcases.php`, and the reusable Blade component make product-to-model association explicit by slug. The default candidate is `series-x-articulated-lamp`, but its business identity is still unconfirmed: `FEATURED_LAMP_PRODUCT_SLUG` can override it, and an empty value disables that 3D association. Unassociated products retain their own image and URL.
- The initially selected associated showcase fetches automatically. Later component instances use an `IntersectionObserver` with a 320 px approach margin. The manager caps live WebGL contexts at two, evicts the oldest non-visible context when necessary, aborts pending work on navigation, and stops request-animation-frame scheduling offscreen, while the document is hidden, or under reduced motion.
- The product-linked stage is a single keyboard-focusable link with a visible focus ring. Its canvas is decorative and `pointer-events: none`, leaving mobile scrolling untouched. The same model-matched poster remains visible during loading, WebGL/asset failure, or context loss; a failed poster falls back to that product's ordinary image.
- Focused Laravel tests cover exact association despite an unrelated first featured product, real name/price/URL, image-only fallback, and extracted-asset integrity. Node regression tests cover active, hidden-tab, offscreen, and reduced-motion scheduling decisions.
- Headless Chromium validation against the production frontend build and a disposable SQLite catalog confirmed the correct `/products/series-x-articulated-lamp` destination, visible keyboard focus, non-interactive canvas, one model request, active in-view rotation, offscreen pause, a stationary reduced-motion result, a 390×844 visual viewport with normal scrolling and no horizontal overflow, and poster fallback when the binary request is blocked. The localhost resource timings were 8 ms for the poster, 2 ms for the manifest, and 26 ms for the uncompressed 19,234,224-byte binary; these warm local figures are not production-network or real-device evidence.

## 7. Step 2 implementation record (2026-09-26)

- The explicitly associated published product now receives an automatically loaded `interactive-showroom` instance on its description page. Unassociated products retain the ordinary image/gallery path, and the Step 1 homepage remains navigation-only with its original slow-rotation behavior.
- The shared renderer now supports bounded pointer/keyboard orbit, bounded zoom, black/white finish previews, warm/neutral/white light previews, and five customer-facing brightness levels mapped to the source renderer's values 2/4/6/8/10. The finish control is explicitly labelled as a visual preview and does not alter cart, checkout, inventory, or order data.
- First interaction permanently stops automatic rotation for that page view. Reduced-motion starts stationary. `IntersectionObserver`, `visibilitychange`, context-loss handling, teardown, and a page-lifetime parsed-model cache prevent hidden/offscreen animation, stale listeners, duplicate downloads, and abandoned GPU contexts.
- Mobile uses `touch-action: pan-y`; one-finger vertical intent continues normal page scrolling, deliberate horizontal movement captures orbit, and a two-pointer gesture zooms. Wheel zoom is accepted only while the canvas is focused (or for a browser pinch gesture), avoiding ordinary wheel-scroll capture. Camera controls have keyboard equivalents and visible focus; all dedicated buttons are at least 44 px.
- The collapsed **Explore Engineering View** exposes the source viewer's six documented poses, lower/upper arm, neck, head roll, base swivel, clamp opening, exploded offsets, anatomy colours, and eight available part-group labels. Input limits and transform formulas match the supplied viewer. The normal presentation profile continues to suppress every `Cable.` part and never creates its procedural external lead.
- Actual name, price/discount, stock, quantity, cart, wishlist, and contact actions remain outside the canvas. Real photographs remain separate gallery content. Unsupported bulk discount, delivery/COD, and purchase-verification claims were removed from this page; approved records are labelled **Customer Reviews**.
- The matching poster remains visible during loading and on blocked asset, WebGL, or context-loss failure. Commerce and real images remain usable without the renderer. The 19,234,224-byte binary is cached and parsed once per page lifetime, but production compression is not configured here.

### Step 2 verification evidence

- Focused JavaScript regression coverage verifies animation gates, first-interaction pause, horizontal touch intent, source limits/presets, and the five-level mapping. Laravel feature coverage verifies the exact model association, image-only fallback, real shopping/gallery content, honest copy, and unchanged navigation-only homepage behavior.
- A dependency-free Chromium/DevTools smoke test runs against a fresh disposable SQLite catalog and the production Vite build. At 1440×1100 it verified ready-state rendering, enabled controls, visible keyboard focus, a 48 px Add to Cart action, interaction pause, eight anatomy labels, finish/light previews, and complete Reset View state restoration. At 390×844 it verified reduced-motion pause, `pan-y`, ordinary vertical scrolling, no horizontal overflow, offscreen pause, and blocked-binary poster fallback.
- Retained comparison evidence is under `deployment/evidence/ui-3d-step-2/`: showroom and supplied-viewer default and exploded/anatomy images, plus mobile reduced-motion and loading-failure images. Visual inspection confirms the same clamp, paired rails, spring sets, pivots, head, finish rules, and exploded grouping. Normal showroom evidence intentionally differs by hiding the source viewer's external lead.
- The localhost HTTP response served the raw model with `Content-Length: 19234224`, no `Content-Encoding`, no service worker, and DevTools `encodedDataLength: 19234387`. The asset is therefore extracted and cacheable, but was not compressed in the measured transfer; the earlier Brotli number remains only an offline measurement.
- The supplied HTML still hashes to `32f54a79d4a3e6fe96fb8ec207c7c299bb9e15cf8df6ef11cc900dcfdf0639d6` and remains byte-identical. The browser evidence is desktop/headless software WebGL and mobile emulation, not physical-device acceptance.

## 8. Step 3 implementation record (2026-09-28)

- The customer storefront now uses the Vite-built graphite/matte/teal system without the full-screen scanline or global grid. Local grid detail is limited to the two model stages at reduced opacity. Manrope variable and DM Mono 400/500 Latin WOFF2 files are bundled from Fontsource under the recorded OFL-1.1 notices; customer authentication no longer depends on Google Fonts or the Tailwind CDN.
- Customer-facing branding and navigation use **MAAT Technologies BD** and readable shopping labels. The old **MAAT TECHNOLOGIE BD** identity remains in administrator-only templates and conflicts with the requested customer name; legal/trading-name and contact ownership still require business confirmation before production release.
- Product, cart, wishlist, and navigation targets touched in this pass are at least 44 px with visible focus. The mobile product page has one sticky purchase submitter associated with the existing `product-purchase-form`; it introduces no second POST path or variant state, and the submit-once guard covers both buttons. The unsupported flat BDT 120 cart estimate was replaced by **Calculated at checkout**, leaving the existing district-based checkout service authoritative.
- A deterministic gzip companion is emitted beside the unchanged raw binary. The manifest identifies the gzip fingerprint, size, hash, and format. Supporting browsers fetch and stream-decompress it explicitly; unsupported decompression or a failed gzip fetch falls back to the raw fingerprinted binary, and complete model failure retains the poster. This distinct-resource design does not depend on Brotli, `Content-Encoding`, or `Vary: Accept-Encoding`.
- Apache `.htaccess`, container Apache, and the Nginx sample assign explicit model MIME types and immutable one-year cache policy. The PHP development server used for browser evidence correctly delivered `application/gzip` but does not apply those host configurations, so it emitted no cache header and the measured “warm” reload refetched the asset. The eventual host/CDN must be checked for the committed MIME/cache behavior before production; no cache saving is claimed from this local warm run.
- The original 390 px check exposed an aspect-ratio/min-height interaction that forced a 448 px stage. The stage now drops its minimum height below 480 px while retaining larger breakpoints. The final 390×844 run measured no overflowing elements and kept ordinary scrolling, `touch-action: pan-y`, reduced-motion pause, and the 73 px sticky purchase action.

### Step 3 browser and transfer evidence

- A fresh disposable SQLite catalog, array cache/session/mail, synchronous queue, isolated compiled views, the production Vite build, and headless Chromium software WebGL were used. No development database or real storage was migrated or edited.
- Cold localhost product HTML transferred 25,958 bytes. The manifest transferred 28,305 bytes. The delivered gzip model transferred 4,370,023 bytes (`application/gzip`, no `Content-Encoding`) and decoded to the verified 19,234,224-byte buffer. Step 2's raw response transferred 19,234,387 bytes, so the actual browser model transfer fell by 14,864,364 bytes (77.3%). The raw-recovery browser case still transferred 19,234,387 bytes and became usable.
- Cold localhost model-ready time was 946 ms. Under the specified cache-disabled profile—4 Mbps downstream, 1.5 Mbps upstream, and 150 ms latency—it was 10,277 ms, with the poster visible during loading. The local “warm” reload reached ready in 457 ms but refetched 4,370,023 bytes because PHP's development server supplied no immutable cache header; this is not production cache evidence.
- Browser checks passed for the exact featured destination, navigation-only homepage canvas, automatic and reduced-motion lifecycle, desktop keyboard focus/orbit, first-interaction pause, finish/light previews, reset, collapsed engineering controls, eight anatomy labels, offscreen pause, context-loss fallback, gzip-to-raw recovery, all-model poster fallback, image-only product path, 390 px scrolling, no horizontal overflow, and the existing purchase form association. Source/default and exploded views were visually compared; geometry, clamp, paired arms, springs, pivots, head, and grouping remain consistent, with the external lead intentionally hidden in presentation.
- Retained evidence is under `deployment/evidence/ui-3d-step-3/`: `homepage-desktop.png`, `homepage-mobile-reduced-motion.png`, `showroom-desktop-default.png`, `showroom-desktop-engineering.png`, `showroom-mobile-reduced-motion.png`, `showroom-mobile-loading-failure.png`, the two supplied-viewer comparisons, and `browser-transfer-report.json`.
- Physical-device pinch, physical iOS/Android browser rendering, a real background-tab transition, production-host cache hits, real-network LCP/interaction latency, memory, thermals, and frame-time percentiles were unavailable. Manual release acceptance must: test pinch and uninterrupted vertical scroll on nominated iOS Safari and Android Chrome devices; background/foreground the real tab and verify pause/resume; block both model URLs and verify shopping/poster usability; exercise cart submission once; and record cold/warm host/CDN headers and timings from a production-like network.

## Readiness statement

The supplied source now powers a polished navigation-only homepage showcase and an accessible interactive showroom on the explicitly associated product page. Step 3 development and isolated acceptance are complete. This is not production-release readiness: confirm the canonical product/SKU and model publication rights, approve the legal/trading identity and contacts, verify immutable caching on the selected host/CDN, and complete the physical-device/manual checklist. `FEATURED_LAMP_PRODUCT_SLUG` remains the explicit association override and an empty value safely disables the model. Saleable colour selection, verified-purchase reviews, Bengali localization, hosting purchase, and deployment remain outside this checkpoint.
