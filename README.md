# Batch Resize

A Kirby Panel maintenance tool for resizing existing images according to their file blueprint `create` settings. Administrators can open **Clean Up** in the Panel menu, review a dry run, and process images in batches. Back up originals first; processing overwrites them.

Set a fallback file blueprint (such as `image` or `portrait`) to apply its create settings to files using the default template when they have no create settings. The default batch size is 10, configurable in the Panel.

## Layout

- `index.php` loads the service and registers the plugin.
- `config/area.php` registers the admin-only Panel menu and view.
- `config/api.php` handles authenticated previews and resize batches.
- `lib/ResizeService.php` collects images, decides what needs resizing, and runs batches.
- `src/index.js` registers the Panel view and `src/components/` contains its Vue components.
- `index.js` and `index.css` are built Panel assets. Commit both so the plugin works without npm on the server.
- `tests/` checks scanning, batch progression, Panel access, and API responses without touching actual files. Run with `npm test`.

Run `npm install` once, then `npm run dev` to watch changes or `npm run serve` for Panel hot reloading. Run `npm run build` before publishing and refresh the Panel.

Add Panel areas and API routes under `config/`, image-processing behavior in `lib/`, and UI in `src/components/`.