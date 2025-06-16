import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS, cssVar } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-delivered-vs-open");
let deliveredVsOpenChart;

export const deliveredVsOpen = () => {
	//---------------------------------------------------------
	// Chart
	//---------------------------------------------------------

	// Chart data
	const CHART_DATA = {
		labels: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 15, 16, 17, 18, 19, 20],
		datasets: [
			{
				label: "Opened",
				data: [75, 54, 145, 110, 145, 35, 135, 100, 30, 60, 70, 90, 100, 80, 180, 134, 180, 70, 225, 150, 35],
				categoryPercentage: 0.25,
				borderRadius: 15,
				backgroundColor: `rgba(${COLORS.chart.main}, 0.75)`,
				borderWidth: 0,
				grouped: false,
				maxBarThickness: 6,
			},
			{
				label: "Delivered",
				data: [100, 80, 180, 134, 180, 70, 225, 150, 35, 100, 120, 160, 120, 145, 200, 145, 200, 135, 230, 180, 60],
				categoryPercentage: 0.5,
				borderRadius: 15,
				backgroundColor: `rgba(${COLORS.black}, 0.2)`,
				borderWidth: 0,
				maxBarThickness: 12,
			},
		],
	};

	// Chart config
	const CHART_CONFIG = {
		type: "bar",
		data: CHART_DATA,
		options: {
			maintainAspectRatio: false,
			layout: {
				padding: {
					left: -5,
					right: 1,
				},
			},
			indexAxis: "x",
			elements: {
				bar: {
					borderWidth: 0,
				},
			},
			responsive: true,
			plugins: {
				legend: {
					display: false,
				},
				title: {
					display: false,
				},
				tooltip: {
					...CHART_TOOLTIP,
				},
			},
			scales: {
				x: {
					border: {
						display: false,
					},
					grid: {
						display: false,
					},
					ticks: {
						...chartTicks(0),
						maxTicksLimit: 10,
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

	// Init chart
	if (CHART_WRAPPER) {
		deliveredVsOpenChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};
