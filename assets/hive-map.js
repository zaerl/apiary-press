(function () {
	const RING_COLOR = '#E7AE43';
	const RINGS = [
		{ radius: 5000, fillOpacity: 0.08 },
		{ radius: 3500, fillOpacity: 0.16 },
		{ radius: 2000, fillOpacity: 0.28 }
	];

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function addTiles(map) {
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
		}).addTo(map);
	}

	function parseMarkers(container) {
		try {
			const markers = JSON.parse(container.getAttribute('data-markers') || '[]');
			return Array.isArray(markers) ? markers : [];
		} catch (e) {
			return [];
		}
	}

	/**
	 * Interactive picker: a draggable marker bound to two inputs.
	 * Clicking the map places the marker; dragging it, or editing the inputs, keeps both in sync.
	 */
	function renderPicker(container) {
		if (container.dataset.apHiveMapReady === '1' || !window.L) {
			return;
		}

		const latInput = document.getElementById(container.getAttribute('data-lat-input') || '');
		const lngInput = document.getElementById(container.getAttribute('data-lng-input') || '');
		if (!latInput || !lngInput) {
			return;
		}

		const context = parseMarkers(container);
		const singleZoom = parseInt(container.getAttribute('data-zoom') || '15', 10);
		const map = L.map(container);
		container.dataset.apHiveMapReady = '1';
		addTiles(map);

		context.forEach(function (item) {
			L.circleMarker([item.latitude, item.longitude], {
				radius: 6,
				color: '#fff',
				weight: 2,
				opacity: 1,
				fillColor: RING_COLOR,
				fillOpacity: 0.5,
				interactive: false
			}).addTo(map);
		});

		let marker = null;

		function readInputs() {
			const lat = parseFloat(latInput.value);
			const lng = parseFloat(lngInput.value);
			if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
				return null;
			}
			return L.latLng(lat, lng);
		}

		function writeInputs(latLng) {
			latInput.value = latLng.lat.toFixed(6);
			lngInput.value = latLng.lng.toFixed(6);
		}

		function placeMarker(latLng) {
			if (marker) {
				marker.setLatLng(latLng);
				return;
			}
			marker = L.marker(latLng, { draggable: true, autoPan: true }).addTo(map);
			marker.on('dragend', function () {
				writeInputs(marker.getLatLng());
			});
		}

		function syncFromInputs(pan) {
			const latLng = readInputs();
			if (!latLng) {
				if (marker) {
					map.removeLayer(marker);
					marker = null;
				}
				return;
			}
			placeMarker(latLng);
			if (pan) {
				map.setView(latLng, Math.max(map.getZoom(), singleZoom));
			}
		}

		map.on('click', function (e) {
			placeMarker(e.latlng);
			writeInputs(e.latlng);
		});

		[latInput, lngInput].forEach(function (input) {
			input.addEventListener('input', function () {
				syncFromInputs(false);
			});
			input.addEventListener('change', function () {
				syncFromInputs(true);
			});
		});

		// Initial view: the current position, else the other hives, else the world.
		const initial = readInputs();
		if (initial) {
			placeMarker(initial);
			map.setView(initial, singleZoom);
		} else if (context.length === 1) {
			map.setView([context[0].latitude, context[0].longitude], singleZoom);
		} else if (context.length > 1) {
			map.fitBounds(L.latLngBounds(context.map(function (c) {
				return [c.latitude, c.longitude];
			})), { padding: [24, 24] });
		} else {
			map.setView([20, 0], 2);
		}

		// Let the rest of the page (e.g. the geolocation button) push a position in.
		container.apHiveMapSync = function () {
			syncFromInputs(true);
		};
	}

	function renderMap(container) {
		if (container.dataset.apHiveMapReady === '1') {
			return;
		}

		if (container.hasAttribute('data-ap-map-picker')) {
			renderPicker(container);
			return;
		}

		const markers = parseMarkers(container);

		if (!window.L || !markers.length) {
			return;
		}

		const singleZoom = parseInt(container.getAttribute('data-zoom') || '15', 10);
		const map = L.map(container);
		container.dataset.apHiveMapReady = '1';

		addTiles(map);

		const circleLayer = L.layerGroup().addTo(map);

		function showRings(latLng) {
			circleLayer.clearLayers();
			let smallestCircle = null;
			RINGS.forEach(function (ring) {
				const circle = L.circle(latLng, {
					radius: ring.radius,
					color: RING_COLOR,
					weight: 1,
					opacity: 0.7,
					fillColor: RING_COLOR,
					fillOpacity: ring.fillOpacity,
					interactive: false
				}).addTo(circleLayer);

				if (!smallestCircle || ring.radius < smallestCircle.getRadius()) {
					smallestCircle = circle;
				}
			});

			if (smallestCircle) {
				map.fitBounds(smallestCircle.getBounds(), { padding: [16, 16] });
			}
		}

		const latLngs = markers.map(function (marker) {
			const latLng = [marker.latitude, marker.longitude];
			const leafletMarker = L.circleMarker(latLng, {
				radius: 8,
				color: '#fff',
				weight: 2,
				opacity: 1,
				fillColor: RING_COLOR,
				fillOpacity: 1
			}).addTo(map);

			if (marker.title || marker.url) {
				const title = escapeHtml(marker.title || '');
				const popup = marker.url ? '<a href="' + marker.url + '">' + title + '</a>' : title;
				leafletMarker.bindPopup(popup);
				if (marker.title) {
					leafletMarker.bindTooltip(marker.title, { direction: 'top', offset: [0, -8] });
				}
			}

			leafletMarker.on('click', function () {
				showRings(latLng);
			});

			return latLng;
		});

		map.on('click', function () {
			circleLayer.clearLayers();
		});

		if (latLngs.length === 1) {
			map.setView(latLngs[0], singleZoom);
		} else {
			map.fitBounds(L.latLngBounds(latLngs), { padding: [24, 24] });
		}
	}

	function initAll() {
		const containers = document.querySelectorAll('[data-ap-hive-map]');
		containers.forEach(renderMap);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
