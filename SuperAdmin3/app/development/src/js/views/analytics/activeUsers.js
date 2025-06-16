import Chart from "chart.js/auto";
import { CHART_GRID, CHART_TOOLTIP, chartTicks, reloadChart } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-active-users");
let activeUsersChart;

export const activeUsers = () => {
	//------------------------------------------------------------
	// Chart
	//------------------------------------------------------------
	const CHART_DATA = {
		labels: [
			"01:00",
			"02:00",
			"03:00",
			"04:00",
			"05:00",
			"06:00",
			"07:00",
			"08:00",
			"09:00",
			"10:00",
			"11:00",
			"12:00",
			"13:00",
			"14:00",
			"15:00",
			"16:00",
			"17:00",
			"18:00",
			"19:00",
			"21:00",
			"22:00",
			"23:00",
			"24:00",
		],
		datasets: [
			{
				label: "Users",
				data: [100, 50, 105, 50, 240, 55, 110, 48, 145, 50, 99, 54, 200, 50, 190, 60, 95, 60, 170, 50, 200, 60, 190, 57, 90],
				backgroundColor: `rgba(${COLORS.chart.main}, 0.75)`,
				barThickness: 3,
				borderWidth: 0,
				borderRadius: 10,
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
					grid: {
						display: false,
					},
					border: {
						display: false,
					},
					ticks: {
						...chartTicks(0, "center"),
					},
				},
				y: {
					grid: {
						...CHART_GRID,
					},
					border: {
						display: false,
					},
					ticks: {
						...chartTicks(),
					},
				},
			},
		},
	};

	if (CHART_WRAPPER) {
		activeUsersChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}

	//------------------------------------------------------------
	// Data
	//------------------------------------------------------------
	const LIST_WRAPPER = document.getElementById("list-active-users");
	let list = "";
	const DATA = [
		{
			page: "/index.html",
			users: 1480,
			percentage: 78,
			up: true,
		},
		{
			page: "/downloads/mobile.html",
			users: 1143,
			percentage: 69,
			up: true,
		},
		{
			page: "/pricing.html",
			users: 964,
			percentage: 56,
			up: false,
		},
		{
			page: "/blog/how-to-configure.html",
			users: 542,
			percentage: 35,
			up: true,
		},
		{
			page: "/products/design.html",
			users: 234,
			percentage: 18,
			up: false,
		},
	];

	let last = DATA.length - 1;

	if (LIST_WRAPPER) {
		DATA.forEach((item, index) => {
			list += `<div class="d-flex align-items-center py-3 ${index !== last ? "border-bottom" : ""}">
                        <div class="flex-grow-1">${item.page}</div>
                        <div class="fs-5">${item.users}</div>

                        <div class="w-20 text-end d-none d-sm-block">
							<div class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-success bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
								${item.percentage}%

								<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
							</div>
						</div>
                    </div>`;
		});

		LIST_WRAPPER.innerHTML = list;
	}
};