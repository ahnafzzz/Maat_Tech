# MECHARM Prototype

This workspace has been organized into a compact deployable prototype focused on storefront and admin experiences.

## Included files
- [index.html](index.html) – root redirect to storefront
- [screens/mech-lamp-storefront.html](screens/mech-lamp-storefront.html) – storefront experience
- [screens/lead-admin-panel.html](screens/lead-admin-panel.html) – lead admin control panel
- [screens/admin-login.html](screens/admin-login.html) – admin sign-in view
- [screens/theme-toggle.css](screens/theme-toggle.css) – shared light/dark theme styles
- [screens/theme-toggle.js](screens/theme-toggle.js) – shared dark-default theme toggle logic
- [screens/prototype-interactions.js](screens/prototype-interactions.js) – shared click-handler wiring
- [USER_GUIDE.md](USER_GUIDE.md) – full usage and deployment guide

## Run locally
From this folder, run:

```bash
npm start
```

Then open http://localhost:3000.

## Theme behavior
- Dark mode is the default.
- A floating toggle button is available on all primary pages.
- Theme preference is saved in browser localStorage.

## Prepare for cPanel upload (without deploying yet)
1. Build a static upload archive:
	```bash
	npm run cpanel:package
	```
2. The zip file will be generated in `dist/cpanel/`.
3. Use [deployment/CPANEL_PREP_CHECKLIST.md](deployment/CPANEL_PREP_CHECKLIST.md) when you are ready to upload.

## Low-resource hosting mode (1 GB RAM / 1 Core)
If you deploy Laravel on small shared hosting, use:

```bash
npm run laravel:low-resource
```

Then follow:
- [deployment/LOW_RESOURCE_HOSTING_1GB.md](deployment/LOW_RESOURCE_HOSTING_1GB.md)
- [deployment/CPANEL_PREP_CHECKLIST.md](deployment/CPANEL_PREP_CHECKLIST.md)

## Deploy to static hosts
This project is compatible with:
- Netlify
- Vercel
- GitHub Pages
- Any static file host

For Netlify and Vercel, simply point the publish directory to the project root.
