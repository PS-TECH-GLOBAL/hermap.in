'use strict';
(() => {
  const stateSelect = document.getElementById('filter-state');
  const citySelect = document.getElementById('filter-city');
  const settings = document.getElementById('reports-settings');
  if (!stateSelect || !citySelect || !settings) return;

  const apiUrl = settings.dataset.apiUrl;
  let cityController = null;

  stateSelect.addEventListener('change', async () => {
    const stateId = stateSelect.value;
    citySelect.innerHTML = '<option value="">All Cities</option>';
    if (!stateId) {
      citySelect.disabled = true;
      return;
    }
    citySelect.disabled = true;
    const loadingOpt = new Option('Loading cities…', '');
    loadingOpt.disabled = true;
    citySelect.add(loadingOpt);
    citySelect.value = '';

    cityController?.abort();
    cityController = new AbortController();

    try {
      const response = await fetch(`${apiUrl}locations?type=cities&state_id=${encodeURIComponent(stateId)}`, {
        cache: 'no-store',
        signal: cityController.signal
      });
      const data = await response.json();
      citySelect.innerHTML = '<option value="">All Cities</option>';
      if (Array.isArray(data.items)) {
        for (const city of data.items) {
          citySelect.add(new Option(city.name, city.id));
        }
      }
      citySelect.disabled = false;
    } catch (e) {
      if (e.name !== 'AbortError') {
        citySelect.innerHTML = '<option value="">All Cities</option>';
        citySelect.disabled = false;
      }
    }
  });
})();
