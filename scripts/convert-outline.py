import sys, json, hashlib
from pathlib import Path
sys.path.insert(0, sys.argv[1])
import shapefile
from pyproj import CRS, Transformer
from shapely.geometry import shape, mapping, Point
from shapely.ops import transform
source = Path(sys.argv[2]); output = Path(sys.argv[3])
reader = shapefile.Reader(str(source))
projected = shape(reader.shape(0).__geo_interface__)
converter = Transformer.from_crs(CRS.from_wkt(source.with_suffix('.prj').read_text()), 'EPSG:4326', always_xy=True)
outline = transform(converter.transform, projected.simplify(150, preserve_topology=True))
assert outline.is_valid
assert outline.covers(Point(74.3,35.2)), 'Northern claimed territory must be included'
assert outline.covers(Point(96,28)), 'Arunachal Pradesh must be included'
def round_coords(value):
    if isinstance(value,(list,tuple)): return [round_coords(v) for v in value]
    return round(value,6) if isinstance(value,float) else value
geometry = mapping(outline); geometry['coordinates'] = round_coords(geometry['coordinates'])
document={'type':'FeatureCollection','features':[{'type':'Feature','properties':{'name':'India','source':'Survey of India','source_url':'https://surveyofindia.gov.in/documents/Outline_of_India.zip','note':'Generalised official outline; not a cadastral or street-level boundary'},'geometry':geometry}]}
output.write_text(json.dumps(document,separators=(',',':')),encoding='utf-8')
print(json.dumps({'output':str(output),'bounds':outline.bounds,'polygons':len(outline.geoms),'bytes':output.stat().st_size,'source_sha256':hashlib.sha256(source.read_bytes()).hexdigest()}))
