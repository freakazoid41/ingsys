import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, reloadChart, CHART_GRID } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-area");
let areaChart;

export const area = () => {
	// Chart data
	const DATA = {
		labels: ["100", "200", "300", "400", "500", "600", "700"],
		datasets: [
			{
				label: "January",
				data: [15, 3, 10, 9, 29, 5, 22],
				fill: true,
				borderColor: `rgba(${COLORS.chart.main}, 1)`,
				backgroundColor: `rgba(${COLORS.chart.sub}, 0.25)`,
				hoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.25)`,
				borderWidth: 1.25,
				pointRadius: 0,
				tension: 0.4,
				pointBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				pointBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBorderWidth: 1.75,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
			},
		],
	};

	// Chart config
	const CONFIG = {
		type: "line",
		data: DATA,
		options: {
			maintainAspectRatio: false,
			interaction: {
				mode: "index",
				intersect: false,
			},
			layout: {
				padding: {
					left: -8,
					right: 15,
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
						drawBorder: false,
						drawOnChartArea: false,
						drawTicks: false,
					},
					ticks: {
						...chartTicks(),
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
		areaChart = new Chart(CHART_WRAPPER, CONFIG);
	}
};

// Update chart
export const areaChartUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(areaChart);
		});
	}
};
