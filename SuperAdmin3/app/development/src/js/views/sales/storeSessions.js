import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { chartGradient, generateTimeSeriesData, CHART_TOOLTIP, chartTicks, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-store-sessions");
let storeSessionsChart;

export const storeSessions = () => {
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
	const LAST_MONTH_DATA = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 19, {
		min: 50,
		max: 30,
	});

	const CURRENT_MONTH_DATA = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 19, {
		min: 70,
		max: 40,
	});

	const CHART_DATA = {
		datasets: [
			{
				label: "Last Month",
				data: LAST_MONTH_DATA,
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.main}, 0.4)`, `rgba(${COLORS.chart.main}, 0)`),
				borderColor: `rgba(${COLORS.chart.main}, 1)`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				pointBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
			},
			{
				label: "Current Month",
				data: CURRENT_MONTH_DATA,
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.sub}, 0.1)`, `rgba(${COLORS.chart.sub}, 0)`),
				borderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointBorderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.sub}, 0.75)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.sub}, 0.75)`,
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
					type: "time",
					ticks: {
						...chartTicks(),
						maxTicksLimit: 8,
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
					max: 100,
					ticks: {
						...chartTicks(),
						maxTicksLimit: 8,
						padding: 8,
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
		storeSessionsChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};