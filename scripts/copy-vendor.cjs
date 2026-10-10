const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
function copy(source, destination) {
  const target = path.join(root, destination);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.copyFileSync(path.join(root, source), target);
}
copy('node_modules/@fortawesome/fontawesome-free/css/all.min.css', 'assets/css/fontawesome.min.css');
fs.cpSync(path.join(root, 'node_modules/@fortawesome/fontawesome-free/webfonts'), path.join(root, 'assets/webfonts'), { recursive: true });
copy('node_modules/maplibre-gl/dist/maplibre-gl.mjs', 'assets/js/maplibre-gl.mjs');
copy('node_modules/maplibre-gl/dist/maplibre-gl-worker.mjs', 'assets/js/maplibre-gl-worker.mjs');
copy('node_modules/maplibre-gl/dist/maplibre-gl-shared.mjs', 'assets/js/maplibre-gl-shared.mjs');
copy('node_modules/maplibre-gl/dist/maplibre-gl.css', 'assets/css/maplibre.css');
for (const [pkg, label, file] of [
  ['@fortawesome/fontawesome-free', 'fontawesome', 'LICENSE.txt'],
  ['maplibre-gl', 'maplibre', 'LICENSE.txt'],
  ['tailwindcss', 'tailwind', 'LICENSE'],
]) copy(`node_modules/${pkg}/${file}`, `assets/licenses/${label}.txt`);
console.log('Local styles, icons, fonts and map library copied to assets/.');
