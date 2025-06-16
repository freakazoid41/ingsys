import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, CHART_LEGEND_LABEL, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-line");
let lineChart;

export const line = () => {
	// Chart data
	const DATA = {
		labels: ["100", "200", "300", "400", "500", "600", "700"],
		datasets: [
			{
				label: "January",
				data: [15, 3, 10, 9, 29, 5, 22],
				borderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				backgroundColor: "transparent",
				hoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				borderWidth: 1.25,
				pointRadius: 0,
				pointBackgroundColor: "transparent",
				pointBorderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointHoverBorderWidth: 1.75,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
			},
			{
				label: "February",
				data: [5, 19, 15, 24, 12, 30, 9],
				borderColor: `rgba(${COLORS.chart.main}, 1)`,
				backgroundColor: "transparent",
				hoverBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				borderWidth: 1.25,
				pointRadius: 0,
				pointBackgroundColor: "transparent",
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
					position: "bottom",
					labels: {
						usePointStyle: true,
						...CHART_LEGEND_LABEL,
					},
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
		lineChart = new Chart(CHART_WRAPPER, CONFIG);
	}
};

// Update chart
export const lineChartUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(lineChart);
		});
	}
};
