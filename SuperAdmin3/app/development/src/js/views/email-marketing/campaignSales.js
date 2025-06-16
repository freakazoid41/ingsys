import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, chartTicks, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-campaign-sales");
let campaignSalesChart;

export const campaignSales = () => {
	//---------------------------------------------------------
	// Chart
	//---------------------------------------------------------

	// Helper function for chart gradient fill
	const gradientBg = (context, colorStart, colorEnd) => {
		const chart = context.chart;
		const { ctx, chartArea } = chart;
		return chartArea ? chartGradient(ctx, chartArea, 0.75, colorStart, colorEnd) : null;
	};

	// Chart data
	const CHART_DATA = {
		labels: ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "15", "16", "17", "18"],
		datasets: [
			{
				label: "Transections",
				data: [109, 106, 105, 106, 108, 109, 106, 109, 107, 105, 105, 105, 105, 108, 105, 107, 106, 109, 107],
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.main}, 0.25)`, `rgba(${COLORS.chart.main}, 0)`),
				borderColor: `rgb(${COLORS.chart.main})`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.chart.main})`,
				pointBorderColor: `rgb(${COLORS.chart.main})`,
				pointHoverBorderColor: `rgb(${COLORS.chart.main})`,
				pointHoverBackgroundColor: `rgb(${COLORS.chart.main})`,
			},
			{
				label: "Transections Revenue",
				data: [112, 113, 112, 111, 111, 113, 113, 110, 113, 112, 113, 113, 112, 114, 111, 113, 115, 115, 111],
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.sub}, 0.25)`, `rgba(${COLORS.chart.sub}, 0)`),
				borderColor: `rgb(${COLORS.chart.sub})`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.chart.sub})`,
				pointBorderColor: `rgb(${COLORS.chart.sub})`,
				pointHoverBorderColor: `rgb(${COLORS.chart.sub})`,
				pointHoverBackgroundColor: `rgb(${COLORS.chart.sub})`,
			},
		],
	};

	// Chart config
	const CHART_CONFIG = {
		type: "line",
		data: CHART_DATA,
		options: {
			maintainAspectRatio: false,
			interaction: {
				mode: "index",
				intersect: false,
			},
			layout: {
				padding: {
					left: "-5",
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
						maxTicksLimit: 12,
					},
				},
				y: {
					border: {
						display: false,
					},
					grid: {
						...CHART_GRID,
					},
					min: 100,
					max: 120,
					ticks: {
						...chartTicks(),
						maxTicksLimit: 6,
						callback: (label) => {
							return label + "K";
						},
					},
				},
			},
			plugins: {
				legend: {
					display: false,
				},
				tooltip: {
					...CHART_TOOLTIP,
				},
			},
		},
	};

	// Chart init
	if (CHART_WRAPPER) {
		campaignSalesChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};

// Reload Map and chart to match dark/light mode when switched
// This function will be used in `colorMode.js`
export const campaignSalesUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(campaignSalesChart);
		});
	}
};
