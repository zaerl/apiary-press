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

	function renderMap(container) {
		if (container.dataset.apHiveMapReady === '1') {
			return;
		}

		let markers;
		try {
			markers = JSON.parse(container.getAttribute('data-markers') || '[]');
		} catch (e) {
			return;
		}

		if (!window.L || !Array.isArray(markers) || !markers.length) {
			return;
		}

		const singleZoom = parseInt(container.getAttribute('data-zoom') || '15', 10);
		const map = L.map(container);
		container.dataset.apHiveMapReady = '1';

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
		}).addTo(map);

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
