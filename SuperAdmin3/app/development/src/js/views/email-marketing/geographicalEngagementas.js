import jsVectorMap from "jsvectormap";
import "jsvectormap/dist/maps/world.js";
import { resizeMap } from "../../vendors/jsvectormap/jsVectorMap";
import { COLORS } from "../../utils";
import images from "../../../img/flags/*.svg";

export const geographicalEngagementas = () => {
	//------------------------------------------------------
	// Map
	//------------------------------------------------------
	const MAP_WRAPPER = document.getElementById("map-geographical-engagementas");
	const markerFils = {
		1: "rgba(223, 122, 0, 0.8)",
		2: "rgba(223, 122, 0, 0.7)",
		3: "rgba(223, 122, 0, 0.6)",
		4: "rgba(223, 122, 0, 0.5)",
		5: "rgba(223, 122, 0, 0.4)",
	};

	if (MAP_WRAPPER) {
		// Resize map on load
		resizeMap(MAP_WRAPPER);

		// Initiate map
		new jsVectorMap({
			selector: "#map-geographical-engagementas",
			map: "world",
			draggable: false,
			zoomButtons: false,
			zoomOnScroll: false,
			regionStyle: {
				initial: {
					fill: "rgba(254, 168, 76, 0.25)",
					fillOpacity: 1,
					stroke: COLORS.transparent,
					strokeWidth: 1,
				},
				hover: {
					fillOpacity: 1,
				},
			},
			markerStyle: {
				initial: {
					fill: markerFils[1],
				},
				hover: {
					fill: markerFils[1],
				},
			},
			markers: [
				{
					name: "United States",
					coords: [37.0902, -95.7129],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Canada",
					coords: [56.1304, -106.3468],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Mexico",
					coords: [23.6345, -102.5528],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Cuba",
					coords: [21.5218, -77.7812],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Jamaica",
					coords: [18.1096, -77.2975],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "France",
					coords: [46.603354, 1.888334],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[2],
							r: 8,
						},
					},
				},
				{
					name: "Germany",
					coords: [51.1657, 10.4515],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[2],
							r: 8,
						},
					},
				},
				{
					name: "Italy",
					coords: [41.8719, 12.5675],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[2],
							r: 8,
						},
					},
				},
				{
					name: "Spain",
					coords: [40.4637, -3.7492],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[2],
							r: 8,
						},
					},
				},
				{
					name: "Greece",
					coords: [39.0742, 21.8243],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[2],
							r: 8,
						},
					},
				},
				{
					name: "China",
					coords: [35.8617, 104.1954],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[3],
							r: 6,
						},
					},
				},
				{
					name: "India",
					coords: [20.5937, 78.9629],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[3],
							r: 6,
						},
					},
				},
				{
					name: "Japan",
					coords: [36.2048, 138.2529],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[3],
							r: 6,
						},
					},
				},
				{
					name: "South Korea",
					coords: [35.9078, 127.7669],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[3],
							r: 6,
						},
					},
				},
				{
					name: "Indonesia",
					coords: [-0.7893, 113.9213],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[3],
							r: 6,
						},
					},
				},
				{
					name: "South Africa",
					coords: [-30.5595, 22.9375],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[4],
							r: 4,
						},
					},
				},
				{
					name: "Nigeria",
					coords: [9.082, 8.6753],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[4],
							r: 4,
						},
					},
				},
				{
					name: "Egypt",
					coords: [26.8206, 30.8025],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[4],
							r: 4,
						},
					},
				},
				{
					name: "Kenya",
					coords: [-0.0236, 37.9062],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[4],
							r: 4,
						},
					},
				},
				{
					name: "Algeria",
					coords: [28.0339, 1.6596],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[4],
							r: 4,
						},
					},
				},
				{
					name: "Brazil",
					coords: [-14.235, -51.9253],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[5],
							r: 2,
						},
					},
				},
				{
					name: "Mexico",
					coords: [23.6345, -102.5528],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[5],
							r: 2,
						},
					},
				},
				{
					name: "Argentina",
					coords: [-38.4161, -63.6167],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[5],
							r: 2,
						},
					},
				},
				{
					name: "Peru",
					coords: [-9.19, -75.0152],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[5],
							r: 2,
						},
					},
				},
				{
					name: "Colombia",
					coords: [4.5709, -74.2973],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[5],
							r: 2,
						},
					},
				},
				{
					name: "Australia",
					coords: [-25.2744, 133.7751],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "United Arab Emirates",
					coords: [23.4241, 53.8478],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Saudi Arabia",
					coords: [23.8859, 45.0792],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Russia",
					coords: [61.524, 105.3188],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
				{
					name: "Mongolia",
					coords: [46.8625, 103.8467],
					style: {
						initial: {
							strokeWidth: 1,
							stroke: markerFils[5],
							fill: markerFils[1],
							r: 10,
						},
					},
				},
			],
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

	//------------------------------------------------------
	// List
	//------------------------------------------------------
	const LIST_WRAPPER = document.getElementById("list-geographical-engagementas");

	if (LIST_WRAPPER) {
		let list = "";
		const DATA = [
			{
				img: "us",
				country: "United States of America",
				clicks: 6732,
				up: true,
				percentage: 44,
				change: 4.58,
			},
			{
				img: "japan",
				country: "Japan",
				clicks: 4532,
				up: true,
				percentage: 32,
				change: 0.43,
			},
			{
				img: "england",
				country: "United Kingdom",
				clicks: 4487,
				up: false,
				percentage: 24,
				change: 5.43,
			},
			{
				img: "china",
				country: "China",
				clicks: 3982,
				up: true,
				percentage: 19,
				change: 21.54,
			},
			{
				img: "germany",
				country: "Germany",
				clicks: 3241,
				up: false,
				percentage: 16,
				change: 10.32,
			},
			{
				img: "france",
				country: "France",
				clicks: 2985,
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
                                <span class="text-body-secondary fs-7">${item.clicks} Clicks</span>
                            </div>

                            <div class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
                                <i class="ph ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"} fs-4 me-1"></i>
                                ${item.change}%
                            </div>

							<div class="fs-5 text-body-emphasis text-end w-12">${item.percentage}%</div>
                        </div> 
                    </div>`;
		});

		LIST_WRAPPER.innerHTML = list;
	}
};
