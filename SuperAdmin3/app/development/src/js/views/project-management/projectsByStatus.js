import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { chartGradient, CHART_TOOLTIP, CHART_GRID, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-projects-by-status");
let projectsByStatusChart;

export const projectsByStatus = () => {
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
		labels: ["In Progress", "Completed", "Overdue", "On Hold", "Cancelled", "Planned"],
		datasets: [
			{
				label: "Current Month",
				data: [96, 64, 35, 76, 14, 109],
				fill: true,
				backgroundColor: (context) => [
					gradientBg(context, "rgba(30, 145, 255, 0.25)", "rgba(30, 145, 255, 0"),
					gradientBg(context, "rgba(40, 199, 111, 0.25)", "rgba(40, 199, 111, 0"),
					gradientBg(context, "rgba(254, 168, 76, 0.25)", "rgba(254, 168, 76, 0"),
					gradientBg(context, "rgba(38, 198, 218, 0.25)", "rgba(38, 198, 218, 0"),
					gradientBg(context, "rgba(255, 99, 99, 0.25)", "rgba(255, 99, 99, 0"),
					gradientBg(context, "rgba(226, 108, 245, 0.25)", "rgba(226, 108, 245, 0"),
				],
				borderColor: (context) => [
					gradientBg(context, "rgba(30, 145, 255, 0.9)", "rgba(30, 145, 255, 1"),
					gradientBg(context, "rgba(40, 199, 111, 0.9)", "rgba(40, 199, 111, 0.75"),
					gradientBg(context, "rgba(254, 168, 76, 0.9)", "rgba(254, 168, 76, 0.75"),
					gradientBg(context, "rgba(38, 198, 218, 0.9)", "rgba(38, 198, 218, 0.75"),
					gradientBg(context, "rgba(255, 99, 99, 0.9)", "rgba(255, 99, 99, 0.75"),
					gradientBg(context, "rgba(226, 108, 245, 0.9)", "rgba(226, 108, 245, 0.75"),
				],
				borderRadius: 5,
				borderWidth: 1.25,
			},
		],
	};

	// Chart config
	const CHART_CONFIG = {
		type: "bar",
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
		projectsByStatusChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
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

							<span class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 ${item.up ? "bg-success" : "bg-danger"}">
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
