# USGeolocation


Usage example: 
```
require __DIR__ . '/Polygon.php';
require __DIR__ . '/GeoClass.php';

$gc = new \USGeolocation\GeoClass();
echo $gc->getStateFromLatLong(35.281501, -124.804688); //California
echo $gc->getStateFromZip(42001); //KY
echo $gc->getStateFromZip(43004); //OH

```
