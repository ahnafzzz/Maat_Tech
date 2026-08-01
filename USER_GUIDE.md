# User Guide

## Overview
This project is now a deployable static prototype for MECHARM. It includes:
- a storefront landing experience
- a lead admin panel
- an admin login experience

## File map
- index.html — root redirect to storefront
- screens/mech-lamp-storefront.html — main storefront UI
- screens/lead-admin-panel.html — admin operations screen
- screens/admin-login.html — admin login screen
- screens/theme-toggle.css — shared theme styles (dark/light)
- screens/theme-toggle.js — shared theme toggle script
- screens/prototype-interactions.js — shared click-handler script
- package.json — local run script
- netlify.toml — Netlify deployment config
- .nojekyll — prevents GitHub Pages from processing the site with Jekyll

## Run locally
1. Open the project directory.
2. Run:
   ```bash
   npm start
   ```
3. Visit http://localhost:3000.

## Theme switching
- Dark mode is default across pages.
- A floating button switches between dark and light theme.
- Theme choice persists in browser localStorage.

## Prepare cPanel package (upload later)
1. Run:
   ```bash
   npm run cpanel:package
   ```
2. Find output zip in `dist/cpanel/`.
3. Follow [deployment/CPANEL_PREP_CHECKLIST.md](deployment/CPANEL_PREP_CHECKLIST.md) when you decide to upload.

## Deploy to Netlify
1. Create a new site in Netlify.
2. Connect this folder as the site root.
3. Use the default build settings.
4. Publish the site.

## Deploy to Vercel
1. Import the project into Vercel.
2. Set the root directory to the project folder.
3. Vercel will serve the static files automatically.

## Deploy to GitHub Pages
1. Push the project to GitHub.
2. Open the repository settings.
3. Choose Pages and publish from the main branch.
4. Use the root folder as the site source.

## Next implementation roadmap
- Set up a real Laravel project with models, migrations, seeders, and authentication.
- Convert the storefront to Blade templates and controllers.
- Add a product/cart API and guest-to-authenticated cart merge.
- Add Filament for full admin CRUD and analytics.
- Integrate payments such as Bkash or SSLCommerz, plus COD handling.
- Add shipping zones, Pathao integration, inventory logic, and notifications.
- Add search, reviews, coupons, and SEO improvements.

## Notes
- The current implementation is front-end only and uses static HTML/CSS/JS.
- No backend or database is wired yet.
- For a full production rollout, the UI can later be connected to Laravel and the features listed above.


Admin ID: ADM-0001-Z
Password: ChangeMe!2026