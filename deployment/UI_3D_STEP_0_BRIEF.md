# UI & 3D Step 0 Brief

**Status:** Step 1 implemented on `codex/storefront-3d`; Step 2 remains pending

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

## Step 2 boundary

The product-description interactive showroom remains unimplemented. Step 2 must reuse this renderer and manifest while adding accessible orbit/zoom/reset, first-interaction rotation cancellation, finish and light previews with honest labels, engineering controls, a real-photo gallery, purchase controls that work without WebGL, mobile interaction handling, and golden visual regressions. It must not infer a saleable finish variant or publish unapproved performance/commerce claims.

## Readiness statement

The supplied source has now been extracted into a faithful, non-interactive homepage showcase with explicit product association and bounded lifecycle behavior. Full customer interaction belongs only on the associated product description page. The product identity and publication rights still require business confirmation before production release; the environment-controlled association is the explicit safe path until then. Commerce claims, saleable colour selection, Bengali localization, hosting, and deployment remain outside this checkpoint.
