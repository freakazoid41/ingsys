import Chart from "chart.js/auto";
import { CHART_TOOLTIP, CHART_LEGEND_LABEL, reloadChart } from "../../vendors/chart";
import { cssVar, COLORS } from "../../utils";

const WRAPPER = document.getElementById("chart-pie");
let chart;

export const pie = () => {
	const DATA = {
		labels: ["Jan", "Feb", "Mar", "April"],
		datasets: [
			{
				data: [23981, 16342, 9736, 7632],
				backgroundColor: [`rgb(${COLORS.blue}, 0.9)`, `rgb(${COLORS.teal}, 0.9)`, `rgb(${COLORS.purple}, 0.9)`, `rgb(${COLORS.orange}, 0.9)`],
				borderWidth: 3,
				borderColor: `rgba(${COLORS.black}, 0.1)`,
				hoverOffset: 1,
				hoverBorderWidth: 0,
			},
		],
	};

	// Chart config
	const CONFIG = {
		type: "pie",
		data: DATA,
		options: {
			maintainAspectRatio: false,
			interaction: {
				mode: "index",
				intersect: false,
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
		},
	};

	// Init chart
	if (WRAPPER) {
		chart = new Chart(WRAPPER, CONFIG);
	}
};

// Reload chart to match dark/light mode when switched
export const pieChartUpdate = () => {
	if (WRAPPER) {
		setTimeout(() => {
			reloadChart(chart, (chart.data.datasets[0].borderColor = cssVar("--bs-chart-pie-border-color")));
		});
	}
};
