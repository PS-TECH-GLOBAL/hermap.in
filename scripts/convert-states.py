"""Convert the NWIC government state dataset; retain the SoI external outline.
Usage: python scripts/convert-states.py path/to/state_NWIC.GeoJSON
Dependencies: shapely, pyproj. Output is generalised overview data, not a survey.
"""
import json, sys
from pathlib import Path
from shapely.geometry import shape, mapping
from shapely.ops import transform
from pyproj import Transformer

root = Path(__file__).resolve().parents[1]
data = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8-sig'))
outline = shape(json.loads((root/'assets/data/india-outline.geojson').read_text())['features'][0]['geometry'])
project = Transformer.from_crs(4326, 3857, always_xy=True).transform
unproject = Transformer.from_crs(3857, 4326, always_xy=True).transform
fix_names = {'AR': 'Arunachal Pradesh', 'DH': 'Dadra and Nagar Haveli and Daman and Diu', 'AN': 'Andaman and Nicobar Islands'}
source_crs = data.get('crs', {}).get('properties', {}).get('name', 'EPSG:4326')
to_wgs84 = Transformer.from_crs(source_crs, 4326, always_xy=True).transform
features = []
for feature in data['features']:
    props = feature['properties']
    geom = transform(to_wgs84, shape(feature['geometry']))
    geom = transform(unproject, transform(project, geom).simplify(500, preserve_topology=True))
    # Clip any source coastline disagreement; never replace the external boundary.
    geom = geom.intersection(outline)
    point = geom.representative_point()
    features.append({'type': 'Feature', 'properties': {'code': props['state'], 'name': fix_names.get(props['state'], props['state_name']), 'label': [point.x, point.y], 'bounds': list(geom.bounds)}, 'geometry': mapping(geom)})

def rounded(value):
    if isinstance(value, float): return round(value, 6)
    if isinstance(value, dict): return {k: rounded(v) for k, v in value.items()}
    if isinstance(value, (list, tuple)): return [rounded(v) for v in value]
    return value

(root/'assets/data/india-states.geojson').write_text(json.dumps(rounded({'type': 'FeatureCollection', 'features': features}), separators=(',', ':')), encoding='utf-8')
print(f'Converted {len(features)} states and Union Territories.')
