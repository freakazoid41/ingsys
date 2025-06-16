import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, CHART_LEGEND_LABEL, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-scatter");
let scatterChart;

export const scatter = () => {
	// Chart data
	const SCATTER_DATA_1 = [
		{
			x: 16.4,
			y: 5.4,
		},
		{
			x: 21.7,
			y: 2,
		},
		{
			x: 25.4,
			y: 3,
		},
		{
			x: 19,
			y: 2,
		},
		{
			x: 10.9,
			y: 1,
		},
		{
			x: 13.6,
			y: 3.2,
		},
		{
			x: 10.9,
			y: 7.4,
		},
		{
			x: 10.9,
			y: 0,
		},
		{
			x: 10.9,
			y: 8.2,
		},
		{
			x: 16.4,
			y: 0,
		},
		{
			x: 16.4,
			y: 1.8,
		},
		{
			x: 13.6,
			y: 0.3,
		},
		{
			x: 13.6,
			y: 0,
		},
		{
			x: 29.9,
			y: 0,
		},
		{
			x: 27.1,
			y: 2.3,
		},
		{
			x: 16.4,
			y: 0,
		},
		{
			x: 13.6,
			y: 3.7,
		},
		{
			x: 10.9,
			y: 5.2,
		},
		{
			x: 16.4,
			y: 6.5,
		},
		{
			x: 10.9,
			y: 0,
		},
		{
			x: 24.5,
			y: 7.1,
		},
		{
			x: 10.9,
			y: 0,
		},
		{
			x: 8.1,
			y: 4.7,
		},
		{
			x: 19,
			y: 0,
		},
		{
			x: 21.7,
			y: 1.8,
		},
		{
			x: 27.1,
			y: 0,
		},
		{
			x: 24.5,
			y: 0,
		},
		{
			x: 27.1,
			y: 0,
		},
		{
			x: 29.9,
			y: 1.5,
		},
		{
			x: 27.1,
			y: 0.8,
		},
		{
			x: 22.1,
			y: 2,
		},
	];

	const SCATTER_DATA_2 = [
		{
			x: 36.4,
			y: 13.4,
		},
		{
			x: 1.7,
			y: 11,
		},
		{
			x: 5.4,
			y: 8,
		},
		{
			x: 9,
			y: 17,
		},
		{
			x: 1.9,
			y: 4,
		},
		{
			x: 3.6,
			y: 12.2,
		},
		{
			x: 1.9,
			y: 14.4,
		},
		{
			x: 1.9,
			y: 9,
		},
		{
			x: 1.9,
			y: 13.2,
		},
		{
			x: 1.4,
			y: 7,
		},
		{
			x: 6.4,
			y: 8.8,
		},
		{
			x: 3.6,
			y: 4.3,
		},
		{
			x: 1.6,
			y: 10,
		},
		{
			x: 9.9,
			y: 2,
		},
		{
			x: 7.1,
			y: 15,
		},
		{
			x: 1.4,
			y: 0,
		},
		{
			x: 3.6,
			y: 13.7,
		},
		{
			x: 1.9,
			y: 15.2,
		},
		{
			x: 6.4,
			y: 16.5,
		},
		{
			x: 0.9,
			y: 10,
		},
		{
			x: 4.5,
			y: 17.1,
		},
		{
			x: 10.9,
			y: 10,
		},
		{
			x: 0.1,
			y: 14.7,
		},
		{
			x: 9,
			y: 10,
		},
		{
			x: 12.7,
			y: 11.8,
		},
		{
			x: 2.1,
			y: 10,
		},
		{
			x: 2.5,
			y: 10,
		},
		{
			x: 27.1,
			y: 10,
		},
		{
			x: 2.9,
			y: 11.5,
		},
		{
			x: 7.1,
			y: 10.8,
		},
		{
			x: 2.1,
			y: 12,
		},
	];

	const DATA = {
		labels: ["100", "200", "300", "400", "500", "600", "700"],
		datasets: [
			{
				label: "January",
				data: SCATTER_DATA_1,
				pointRadius: 5,
				pointHoverRadius: 6,
				pointBorderColor: "transparent",
				pointHoverBorderColor: "transparent",
				pointBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
			},
			{
				label: "February",
				data: SCATTER_DATA_2,
				pointRadius: 5,
				pointHoverRadius: 6,
				pointBorderColor: "transparent",
				pointHoverBorderColor: "transparent",
				pointBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
			},
		],
	};

	// Chart config
	const CONFIG = {
		type: "scatter",
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
						maxTicksLimit: 8,
						...chartTicks(),
					},
				},
			},
		},
	};

	// Init chart
	if (CHART_WRAPPER) {
		scatterChart = new Chart(CHART_WRAPPER, CONFIG);
	}
};

// Update chart
export const scatterChartUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(scatterChart);
		});
	}
};
