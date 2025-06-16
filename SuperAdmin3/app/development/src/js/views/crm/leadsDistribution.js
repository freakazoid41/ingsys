import jsVectorMap from "jsvectormap";
import "jsvectormap/dist/maps/world.js";
import { resizeMap } from "../../vendors/jsvectormap/jsVectorMap";
import images from "../../../img/flags/*.svg";

export const leadsDistribution = () => {
	//---------------------------------------------------------
	// Map
	//---------------------------------------------------------
	const MAP_WRAPPER = document.getElementById("map-leads-distribution");

	const c = [];

	if (MAP_WRAPPER) {
		// Resize map on load
		resizeMap(MAP_WRAPPER);

		// Initiate map
		new jsVectorMap({
			selector: "#map-leads-distribution",
			map: "world",
			draggable: false,
			zoomButtons: false,
			zoomOnScroll: false,
			regionStyle: {
				initial: {
					fill: "INITIAL_FILL",
					fillOpacity: 1,
					stroke: "INITIAL_FILL",
					strokeWidth: 1,
				},
				hover: {
					fillOpacity: 0.95,
				},
			},
			visualizeData: {
				scale: ["", ""],
				values: {
					US: 500,
					RU: 490,
					BR: 450,
					ZA: 400,
					IN: 350,
					AE: 300,
					AU: 260,
					SG: 250,
					FR: 200,
					ES: 190,
					PL: 150,
					SE: 120,
					ID: 110,
					JP: 90,
					EG: 45,
				},
			},
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
	}

	//---------------------------------------------------------
	// Data
	//---------------------------------------------------------
	const LIST_WRAPPER = document.getElementById("list-leads-distribution");

	if (LIST_WRAPPER) {
		let list = "";
		const DATA = [
			{
				img: "us",
				country: "United States of America",
				leads: 6732,
				up: true,
				percentage: 44,
				change: 4.58,
			},
			{
				img: "japan",
				country: "Japan",
				leads: 4532,
				up: true,
				percentage: 32,
				change: 0.43,
			},
			{
				img: "england",
				country: "United Kingdom",
				leads: 4487,
				up: false,
				percentage: 24,
				change: 5.43,
			},
			{
				img: "china",
				country: "China",
				leads: 3982,
				up: true,
				percentage: 19,
				change: 21.54,
			},
			{
				img: "germany",
				country: "Germany",
				leads: 3241,
				up: false,
				percentage: 16,
				change: 10.32,
			},
			{
				img: "france",
				country: "France",
				leads: 2985,
				up: true,
				percentage: 9,
				change: 0.54,
			},
		];

		let last = DATA.length - 1;

		DATA.forEach((item, index) => {
			list += `<div class="d-flex align-items-start">
                        <img class="w-6 me-4 rounded-circle" src="${images[item.img]}" alt="${item.country}" />

                        <div class="d-flex align-items-center flex-grow-1 ${index !== last ? "border-bottom mb-2 pb-2" : ""}">
                            <div class="flex-grow-1">
                                <div class="text-body-emphasis">${item.country}</div>
                                <span class="text-body-secondary fs-7">${item.leads} Leads</span>
                            </div>

							<div class="fs-5 text-body-emphasis text-end w-12">${item.percentage}%</div>

                            <div class="w-20 text-end">
								<span class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 ${item.up ? "bg-success" : "bg-danger"} bg-opacity-50">
									${item.change}

									<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
								</span>
							</div>
                        </div> 
                    </div>`;
		});

		LIST_WRAPPER.innerHTML = list;
	}
};
