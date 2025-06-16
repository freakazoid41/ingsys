import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, CHART_GRID, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-sent-not-sent");
let sentVsNotSentChart;

export const sentVsNotSent = () => {
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
		labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Nov", "Dec"],
		datasets: [
			{
				label: "Current Month",
				data: [100, 80, 100, 75, 95, 80, 100, 85, 110, 80, 110],
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
				label: "Last Month",
				data: [160, 130, 160, 110, 150, 120, 165, 130, 170, 110, 160],
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
			scales: {
				x: {
					border: {
						display: false,
					},
					grid: {
						display: false,
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
					min: 0,
					max: 200,
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
		sentVsNotSentChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};

// Reload Map and chart to match dark/light mode when switched
// This function will be used in `colorMode.js`
export const sentVsNotSentUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(sentVsNotSentChart);
		});
	}
};
