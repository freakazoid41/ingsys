import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, CHART_GRID, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-visit-duration");
let visitDurationChart;

export const averageVisitDuration = () => {
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
				label: "Last Month",
				data: [160, 130, 160, 110, 150, 120, 165, 130, 170, 110, 160],
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.main}, 0.2)`, `rgba(${COLORS.chart.main}, 0)`),
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
				data: [100, 80, 100, 75, 95, 80, 100, 85, 110, 80, 110],
				fill: true,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.sub}, 0.75)`, `rgba(${COLORS.chart.sub}, 0)`),
				borderColor: `rgba(${COLORS.chart.sub}, 1)`,
				borderWidth: 1.25,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgba(${COLORS.chart.sub}, 1)`,
				pointBorderColor: `rgba(${COLORS.chart.sub}, 1)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.sub}, 1)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.sub}, 1)`,
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
						...CHART_GRID,
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
					max: 250,
					ticks: {
						...chartTicks(),
						maxTicksLimit: 6,
						callback: (label) => {
							return label + "s";
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
		visitDurationChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};