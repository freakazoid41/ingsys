import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, CHART_LEGEND_LABEL, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-bar");
let barChart;

export const bar = () => {
	// Chart data
	const DATA = {
		labels: ["100", "200", "300", "400", "500", "600", "700"],
		datasets: [
			{
				label: "January",
				data: [44, 55, 41, 37, 22, 43, 21],
				barThickness: 8,
				backgroundColor: `rgba(${COLORS.chart.sub}, 0.5)`,
				hoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.5)`,
			},
			{
				label: "February",
				data: [53, 32, 33, 52, 13, 43, 32],
				barThickness: 8,
				backgroundColor: `rgb(${COLORS.chart.main})`,
				hoverBackgroundColor: `rgb(${COLORS.chart.main})`
			},
		],
	};

	// Chart config
	const CONFIG = {
		type: "bar",
		data: DATA,
		options: {
			maintainAspectRatio: false,
			layout: {
				padding: {
					left: -8,
					right: 15,
				},
			},
			elements: {
				bar: {
					borderWidth: 0,
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
						maxTicksLimit: 7,
						...chartTicks(),
					},
				},
			},
		},
	};

	// Init chart
	if (CHART_WRAPPER) {
		barChart = new Chart(CHART_WRAPPER, CONFIG);
	}
};

// Update chart
export const barChartUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(barChart);
		});
	}
};
