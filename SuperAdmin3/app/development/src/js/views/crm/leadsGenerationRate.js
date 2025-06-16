import Chart from "chart.js/auto";
import { chartTicks, CHART_TOOLTIP, CHART_GRID, reloadChart } from "../../vendors/chart";
import { COLORS, cssVar } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-leads-generation");
let leadsGenerationRateChart;

export const leadsGenerationRate = () => {
	//---------------------------------------------------------
	// Chart
	//---------------------------------------------------------

	// Chart data
	const CHART_DATA = {
		labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Nov", "Dec"],
		datasets: [
			{
				label: "This Year",
				data: [128, 117, 145, 180, 225, 150, 135, 100, 128, 60, 70],
				borderRadius: 10,
				backgroundColor: `rgba(${COLORS.chart.main}, 0.75)`,
				borderWidth: 0,
				categoryPercentage: 0.5,
				maxBarThickness: 8,
			},
			{
				label: "Last Year",
				data: [100, 80, 180, 134, 180, 70, 225, 150, 35, 100, 120],
				categoryPercentage: 0.5,
				borderRadius: 10,
				backgroundColor: `rgba(${COLORS.black}, 0.25)`,
				borderWidth: 0,
				maxBarThickness: 8,
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
					bottom: -5,
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

					ticks: {
						...chartTicks(),
					},
				},
			},
		},
	};

	// Init chart
	if (CHART_WRAPPER) {
		leadsGenerationRateChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};