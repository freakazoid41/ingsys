import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, chartTicks, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-budget-expenses");
let budgetExpensesChart;

export const budgetExpenses = () => {
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
		labels: [
			"Q2 19",
			"Q3 19",
			"Q4 19",
			"Q1 20",
			"Q2 20",
			"Q3 20",
			"Q4 20",
			"Q1 21",
			"Q2 21",
			"Q3 21",
			"Q4 21",
			"Q1 22",
			"Q2 22",
			"Q3 22",
			"Q4 22",
			"Q1 23",
			"Q2 23",
			"Q3 23",
			"Q4 23",
		],
		datasets: [
			{
				label: "Receieved",
				data: [114, 110, 110, 106, 108, 109, 106, 115, 110, 108, 108, 110, 105, 108, 105, 107, 106, 116, 107],
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
				label: "Converted",
				data: [112, 113, 112, 111, 111, 113, 113, 110, 113, 112, 113, 113, 112, 114, 111, 113, 115, 112, 111],
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.sub}, 0.25)`, `rgba(${COLORS.chart.sub}, 0)`),
				borderColor: `rgba(${COLORS.chart.sub}, 0.5)`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgba(${COLORS.chart.sub}, 0.5)`,
				pointBorderColor: `rgba(${COLORS.chart.sub}, 0.5)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.sub}, 0.5)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.5)`,
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
					bottom: "-5",
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
							return "$" + label + "K";
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
		budgetExpensesChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};