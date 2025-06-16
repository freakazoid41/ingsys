import jsVectorMap from "jsvectormap";
import "jsvectormap/dist/maps/world.js";
import Chart from "chart.js/auto";
import { resizeMap } from "../../vendors/jsvectormap/jsVectorMap";
import { CHART_TOOLTIP, chartTicks, CHART_GRID } from "../../vendors/chart";
import { COLORS, cssVar } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-sessions-country");
let sessionsByCountryChart;

export const sessionsByCountry = () => {
	// ----------------------------------------------
	// Map
	// ----------------------------------------------
	const MAP_WRAPPER = document.getElementById("map-sessions-country");

	if (MAP_WRAPPER) {
		// Resize map on load
		resizeMap(MAP_WRAPPER);

		// Initiate map
		new jsVectorMap({
			selector: "#map-sessions-country",
			map: "world",
			draggable: false,
			zoomButtons: false,
			zoomOnScroll: false,
			regionStyle: {
				initial: {
					fill: "INITIAL_FILL",
					fillOpacity: 1,
					stroke: COLORS.transparent,
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
					RU: 480,
					ZA: 400,
					IN: 350,
					AE: 300,
					AU: 260,
					BR: 255,
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

	// ----------------------------------------------
	// Chart
	// ----------------------------------------------
	const CHART_DATA = {
		labels: ["USA", "China", "England", "Japan", "Canada", "France"],
		datasets: [
			{
				label: "Sessions",
				data: [83, 44, 32, 53, 62, 23],
				backgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				barThickness: 3,
				borderWidth: 0,
				borderRadius: 10,
			},
		],
	};
	const CHART_CONFIG = {
		type: "bar",
		data: CHART_DATA,
		options: {
			maintainAspectRatio: false,
			layout: {
				padding: {
					left: 0,
					bottom: 0,
				},
			},
			responsive: true,
			indexAxis: "y",
			plugins: {
				legend: {
					display: false,
				},
				title: {
					display: false,
				},
				tooltip: {
					...CHART_TOOLTIP,
					callbacks: {
						label: (tooltipItem, data) => {
							return tooltipItem.formattedValue + "K";
						},
					},
				},
			},
			scales: {
				x: {
					border: {
						display: false,
					},
					grid: {
						...CHART_GRID,
					},
					ticks: {
						...chartTicks(),
						callback: (label, index, labels) => {
							return label + "K";
						},
					},
				},
				y: {
					border: {
						display: false,
					},
					grid: {
						...CHART_GRID,
					},
					ticks: {
						...chartTicks(),
					},
				},
			},
		},
	};

	if (CHART_WRAPPER) {
		sessionsByCountryChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};