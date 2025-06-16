import Chart from "chart.js/auto";
import { CHART_GRID, CHART_TOOLTIP, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-campaign-reach");
let campaignReachChart;

export const campaignReach = () => {
	//------------------------------------------------------------
	// Chart
	//------------------------------------------------------------
	const CHART_DATA = {
		labels: ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12", "13", "14", "15"],
		datasets: [
			{
				label: "Impressions",
				data: [100, 150, 180, 100, 240, 150, 200, 140, 145, 160, 220, 180, 100, 150, 190, 160, 195, 160, 170, 150, 200, 160, 190, 157, 190],
				backgroundColor: "rgba(30, 145, 255, 0.25)",
				categoryPercentage: 0.25,
				borderWidth: 0,
				borderRadius: 5,
			},
			{
				label: "Views",
				data: [50, 100, 120, 50, 180, 100, 150, 100, 100, 130, 170, 150, 90, 120, 150, 110, 140, 100, 150, 100, 170, 110, 160, 100, 140],
				backgroundColor: "rgba(30, 145, 255, 0.75)",
				categoryPercentage: 0.25,
				borderWidth: 0,
				borderRadius: 5,
			},
			{
				label: "Clicks",
				data: [10, 30, 40, 10, 60, 20, 40, 10, 20, 20, 50, 20, 10, 30, 40, 20, 40, 30, 50, 20, 35, 40, 50, 10, 30],
				backgroundColor: COLORS.orange,
				categoryPercentage: 0.25,
				borderWidth: 0,
				borderRadius: 5,
			},
		],
	};
	const CHART_CONFIG = {
		type: "bar",
		data: CHART_DATA,
		options: {
			maintainAspectRatio: false,
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
					callbacks: {
						label: (tooltipItem, data) => {
							return tooltipItem.formattedValue + "K";
						},
					},
				},
			},
			scales: {
				x: {
					stacked: true,
					grid: {
						display: false,
					},
					border: {
						display: false,
					},
					ticks: {
						...chartTicks(0, "center"),
						maxTicksLimit: 10,
					},
				},
				y: {
					stacked: true,
					grid: {
						...CHART_GRID,
					},
					border: {
						display: false,
					},
					ticks: {
						...chartTicks(),
						maxTicksLimit: 6,
						callback: (label) => {
							return label + "K";
						},
					},
				},
			},
		},
	};

	if (CHART_WRAPPER) {
		campaignReachChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}
};