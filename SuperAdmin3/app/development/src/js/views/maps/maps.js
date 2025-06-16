import jsVectorMap from "jsvectormap";
import "jsvectormap/dist/maps/world.js";
import "./world-merc.js";
import "./canada.js";
import "./russia.js";
import "./spain.js";
import "./us-aea-en.js";
import "./us-lcc-en.js";
import "./us-merc-en.js";
import "./us-mill-en.js";
import { resizeMap } from "../../vendors/jsvectormap/jsVectorMap";
import { COLORS } from "../../utils.js";

export const maps = (item) => {
	// Map config
	const OPTIONS = {
		draggable: false,
		zoomButtons: false,
		zoomOnScroll: false,
	};

	// Maps
	const MAP_ITEMS = [
		{
			name: "World",
			options: {
				map: "world",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "World - Mercator",
			options: {
				map: "world_merc",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "USA - Miller",
			options: {
				map: "us_mill_en",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "USA - Mercator",
			options: {
				map: "us_merc_en",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "USA - Lambert Conformal Conic",
			options: {
				map: "us_lcc_en",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "USA - Albers Equal Area",
			options: {
				map: "us_aea_en",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "Spain",
			options: {
				map: "spain",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
		{
			name: "Canada",
			options: {
				map: "canada",
				...OPTIONS,
				regionStyle: {
					initial: {
						fill: `rgba(${COLORS.chart.main}, 0.75)`,
					},
				},
			},
		},
	];

	// Init map
	const initMap = (item) => {
		const MAP_WRAPPER = document.getElementById(`map-${item.options.map}`);

		// Set map size on load
		resizeMap(MAP_WRAPPER);

		new jsVectorMap({
			selector: `#map-${item.options.map}`,
			...item.options,
			onLoaded(map) {
				window.addEventListener("resize", () => {
					// Set map size on window resize
					resizeMap(MAP_WRAPPER);

					setTimeout(() => {
						map.updateSize();
					});
				});
			},
		});
	};

	// Card
	const CARD = document.createElement("div");
	CARD.setAttribute("class", "card-body");

	const WRAPPER = document.createElement("div");
	WRAPPER.classList.add("mx-auto");

	const mapCard = (id, name) => {
		WRAPPER.id = id;
		CARD.innerHTML = `<h3 class="card-title fs-5 mb-8">${name}</h3>`;
		CARD.appendChild(WRAPPER);

		return CARD.outerHTML;
	};

	// Render
	const MAPS_WRAPPER = document.querySelector("#maps-wrapper");
	let list = "";

	if (MAPS_WRAPPER) {
		MAP_ITEMS.forEach((map) => {
			list += `<div class="col-md-6">
						<div class="card">
							${mapCard(`map-${map.options.map}`, map.name)}
						</div>
					</div>`;

			setTimeout(() => {
				initMap(map);
			});
		});

		MAPS_WRAPPER.innerHTML = list;
	}
};
