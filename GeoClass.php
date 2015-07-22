<?php

namespace USGeolocation;

class GeoClass {
	
	private $geojson;
	private $geojsonRaw;
	private $zipcodes;

	function __construct(){
		$this->geojson = json_decode(file_get_contents(__DIR__ . '/data/state_lines.json'));
		$this->zipcodes = json_decode(file_get_contents(__DIR__ . '/data/state_zip.json'), true);
	}

	private function insideStatePolygon($lat, $lon, $polySet) {
		$polyShape = new \USGeolocation\Polygon($polySet);
		$polySet = $polyShape->getOutline();
		$polyShape->setOutline($polySet);

		if($polyShape->isValid() && $polyShape->pip($lat, $lon))
			return true;
		
		return false;
	}

	private function getPolyCenter($polySet) {
		$polyShape = new \USGeolocation\Polygon($polySet);
		$polySet= $polyShape->getOutline();
		$polyShape->setOutline($polySet);

		if($polyShape->isValid())
			return $polyShape->centroid();
		
		return false;
	}

	public function getStateFromZip($zip) {
		return $this->zipcodes[@end(array_filter(array_keys($this->zipcodes),
			function($v) use ($zip) {
				return ($v < $zip);
			}
		))];
	}

	public function getLatLongDistance($lat1, $lon1, $lat2, $lon2) {
		$dist = rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2))));
		return $dist * 60 * 1.1515; // Miles
	}

	public function getStateFromLatLong($lat, $lon) {

		$stateCenterDistance = [];
		$statePolygons = [];

		foreach($this->geojson->features as $item) {
			foreach($item->geometry->coordinates as $multipoly) {
				if($item->geometry->type == 'MultiPolygon') {
					foreach($multipoly as $poly) {
						$polyCoords = [];
						foreach($poly as $point) {
							$polyCoords[] = [$point[1], $point[0]];
						}

						if($this->insideStatePolygon($lat, $lon, $polyCoords))
							return $item->properties->NAME;

						$centerPosition = $this->getPolyCenter($polyCoords);
						$stateCenterDistance[$item->properties->NAME][] = $this->getLatLongDistance($lat, $lon, $centerPosition[0], $centerPosition[1]);
						$statePolygons[$item->properties->NAME][] = $poly;
					}
				}
				else
				{
					$polyCoords = [];
					foreach($multipoly as $point) 
						$polyCoords[] = [$point[1], $point[0]];

					if($this->insideStatePolygon($lat, $lon, $polyCoords))
						return $item->properties->NAME;

					$centerPosition = $this->getPolyCenter($polyCoords);
						$stateCenterDistance[$item->properties->NAME][] = $this->getLatLongDistance($lat, $lon, $centerPosition[0], $centerPosition[1]);

					$statePolygons[$item->properties->NAME][] = $multipoly;
				}
			}
		}

		//Average the distance of all states
		$stateCenterDistance = array_map(function($item){ return array_sum($item) / count($item); }, $stateCenterDistance);

		//Sort closest descending
		asort($stateCenterDistance);

		//Pick the top 5 closest states
		$stateCenterDistance = array_slice($stateCenterDistance, 0, 5, true);

		//Remove polygons we're not interested in using
		$statePolygons = array_filter($statePolygons, function($item, $key) use($stateCenterDistance){
			return array_key_exists($key, $stateCenterDistance);
		}, ARRAY_FILTER_USE_BOTH);

		$closestPoint = PHP_INT_MAX;
		$closestState = '';
		foreach($statePolygons as $stateName => $polygonSet) {
			//Is it a multipolygon?
			if(is_array($polygonSet[0])) {
				foreach($polygonSet as $polygon) {
					foreach($polygon as $point) {
						$pointDistance = $this->getLatLongDistance($lat, $lon, $point[1], $point[0]);
						
						if($pointDistance < $closestPoint) {
							$closestPoint = $pointDistance;
							$closestState = $stateName;
						}
					}
				}
			} else {
				foreach($polygonSet as $point) {
					$pointDistance = $this->getLatLongDistance($lat, $lon, $point[1], $point[0]);
					if($pointDistance < $closestPoint) {
						$closestPoint = $pointDistance;
						$closestState = $stateName;
					}
				}
			}
		}

		return $closestState;
	}
}