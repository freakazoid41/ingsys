import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, CHART_GRID, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-planned-vs-actual");
let plannedVsActualChart;

export const plannedVsActual = () => {
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
		labels: [1, 2, 3, 4, 5, 6, 7, 8, 9],
		datasets: [
			{
				label: "Billable Hours",
				data: [45, 50, 30, 60, 30, 90, 40, 10, 60],
				fill: true,
				borderRadius: 5,
				backgroundColor: (context) => gradientBg(context, `rgba(${COLORS.chart.main}, 0.3)`, `rgba(${COLORS.chart.main}, 0)`),
				borderWidth: 1.25,
				borderColor: `rgba(${COLORS.chart.main}, 1)`,
				type: "line",
				tension: 0.4,
				pointRadius: 2,
				pointBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
				pointBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBorderColor: `rgba(${COLORS.chart.main}, 1)`,
				pointHoverBackgroundColor: `rgba(${COLORS.chart.main}, 1)`,
			},
		],
	};

	// Chart config
	const CHART_CONFIG = {
		data: CHART_DATA,
		options: {
			maintainAspectRatio: false,
			interaction: {
				mode: "index",
				intersect: false,
			},
			layout: {
				padding: {
					left: -5,
				},
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
						...chartTicks(0, "center"),
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
					max: 120,
					ticks: {
						...chartTicks(),
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
		plannedVsActualChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}

	//---------------------------------------------------------
	// Data
	//---------------------------------------------------------
	let list = "";
	const LIST_WRAPPER = document.getElementById("list-projects-by-status");
	const DATA = [
		{
			type: "In Progress",
			percentage: 56,
			change: 23.61,
			className: "bg-primary",
			projects: 96,
			up: true,
			percentage: 24.3,
		},
		{
			type: "Completed",
			percentage: 37,
			change: 2.35,
			className: "bg-success",
			projects: 64,
			up: true,
			percentage: 16.2,
		},
		{
			type: "Overdue",
			change: 8.54,
			className: "bg-warning",
			projects: 35,
			up: false,
			percentage: 8.8,
		},
		{
			type: "On Hold",
			change: 3.22,
			className: "bg-info",
			projects: 76,
			up: true,
			percentage: 19.2,
		},
		{
			type: "Cancelled",
			change: 33.25,
			className: "bg-danger",
			projects: 16,
			up: false,
			percentage: 4.1,
		},
		{
			type: "Planned",
			change: 0.83,
			className: "bg-purple",
			projects: 4.1,
			up: true,
			percentage: 27.6,
		},
	];

	if (LIST_WRAPPER) {
		DATA.forEach((item, index) => {
			list += `<div class="d-flex align-items-center">
                        <i class="${item.className} w-2.5 h-2.5 rounded-circle me-3"></i>

                        <div class="flex-grow-1 d-flex align-items-center py-3 ${index !== DATA.length - 1 ? "border-bottom" : ""}">
							<div class="flex-grow-1">${item.type}</div>

							<span class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-opacity-75 ${item.up ? "bg-success" : "bg-danger"}">
								${item.change}

								<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
							</span>

							<div class="fs-5 fw-medium text-end w-14">${item.projects}</div>
							<div class="fs-5 fw-medium text-end w-16">${item.percentage}%</div>
						</div>
                    </div>`;

			LIST_WRAPPER.innerHTML = list;
		});
	}
};
