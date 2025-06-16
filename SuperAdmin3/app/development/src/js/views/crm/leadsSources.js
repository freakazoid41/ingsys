import "chartjs-adapter-moment";
import Chart from "chart.js/auto";
import { reloadChart, generateTimeSeriesData, CHART_TOOLTIP, chartTicks, CHART_GRID } from "../../vendors/chart";
import { COLORS } from "../../utils";

const CHART_WRAPPER = document.getElementById("chart-leads-sources");
let leadsSourcesChart;

export const leadsSources = () => {
	//---------------------------------------------------------
	// Chart
	//---------------------------------------------------------

	// Chart data
	const ADVERTISEMENT = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 20, {
		min: 180,
		max: 160,
	});

	const COLD_CALLS = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 20, {
		min: 150,
		max: 130,
	});

	const CUSTOM_REFERRAL = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 20, {
		min: 100,
		max: 80,
	});

	const EMPLOYEE_REFERRAL = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 20, {
		min: 60,
		max: 50,
	});

	const OTHERS = generateTimeSeriesData(new Date("12 Aug 2023").getTime(), 20, {
		min: 40,
		max: 30,
	});

	const CHART_DATA = {
		datasets: [
			{
				label: "Advertisements",
				data: ADVERTISEMENT,
				fill: true,
				backgroundColor: "transparent",
				borderColor: `rgb(${COLORS.green})`,
				borderWidth: 1.5,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.green})`,
				pointBorderColor: `rgb(${COLORS.green})`,
				pointHoverBorderColor: `rgb(${COLORS.green})`,
				pointHoverBackgroundColor: `rgb(${COLORS.green})`,
			},
			{
				label: "Cold Calls",
				data: COLD_CALLS,
				fill: true,
				backgroundColor: "transparent",
				borderColor: `rgb(${COLORS.cyan})`,
				borderWidth: 1.5,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.cyan})`,
				pointBorderColor: `rgb(${COLORS.cyan})`,
				pointHoverBorderColor: `rgb(${COLORS.cyan})`,
				pointHoverBackgroundColor: `rgb(${COLORS.cyan})`,
			},
			{
				label: "Custom Referrals",
				data: CUSTOM_REFERRAL,
				fill: true,
				backgroundColor: "transparent",
				borderColor: `rgb(${COLORS.blue})`,
				borderWidth: 1.5,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.blue})`,
				pointBorderColor: `rgb(${COLORS.blue})`,
				pointHoverBorderColor: `rgb(${COLORS.blue})`,
				pointHoverBackgroundColor: `rgb(${COLORS.blue})`,
			},
			{
				label: "Employee Referrals",
				data: EMPLOYEE_REFERRAL,
				fill: true,
				backgroundColor: "transparent",
				borderColor: `rgb(${COLORS.red})`,
				borderWidth: 1.5,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.red})`,
				pointBorderColor: `rgb(${COLORS.red})`,
				pointHoverBorderColor: `rgb(${COLORS.red})`,
				pointHoverBackgroundColor: `rgb(${COLORS.red})`,
			},
			{
				label: "Others",
				data: OTHERS,
				fill: true,
				backgroundColor: "transparent",
				borderColor: `rgb(${COLORS.orange})`,
				borderWidth: 1.5,
				tension: 0.4,
				pointRadius: 0,
				pointBackgroundColor: `rgb(${COLORS.orange})`,
				pointBorderColor: `rgb(${COLORS.orange})`,
				pointHoverBorderColor: `rgb(${COLORS.orange})`,
				pointHoverBackgroundColor: `rgb(${COLORS.orange})`,
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
						display: false,
						drawBorder: false,
						drawOnChartArea: false,
						drawTicks: false,
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
					max: 200,
					ticks: {
						...chartTicks(),
						maxTicksLimit: 10,
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
		leadsSourcesChart = new Chart(CHART_WRAPPER, CHART_CONFIG);
	}

	//---------------------------------------------------------
	// Data
	//---------------------------------------------------------
	let list = "";
	const LIST_WRAPPER = document.getElementById("list-leads-sources");
	const DATA = [
		{
			source: "Advertisements",
			percentage: 58,
			change: 23.1,
			className: "bg-success",
			up: false,
		},
		{
			source: "Cold Calls",
			percentage: 41,
			change: 1.2,
			className: "bg-purple",
			up: true,
		},
		{
			source: "Custom Referrals",
			percentage: 30,
			change: 43.2,
			className: "bg-primary",
			up: false,
		},
		{
			source: "Employee Referrals",
			percentage: 21,
			change: 9.83,
			className: "bg-danger",
			up: false,
		},
		{
			source: "Others",
			percentage: 9,
			change: 21.43,
			className: "bg-warning",
			up: true,
		},
	];

	if (LIST_WRAPPER) {
		DATA.forEach((item) => {
			list += `<div class="d-flex align-items-start">
                        <i class="${item.className} w-2.5 h-2.5 mt-1.5 rounded-circle me-3"></i>

                        <div class="flex-grow-1 d-flex align-items-center flex-wrap">
							<div class="flex-grow-1 text-body-emphasis">${item.source}</div>

							<div class="fs-7 d-none d-sm-flex align-items-center justify-content-end ${item.up ? "text-success" : "text-danger"}">
								${item.change}%

								<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
							</div>

							<div class="fs-5 text-body-emphasis w-12 text-end">${item.percentage}%</div>

							<div class="progress w-100 my-4 h-0.5" role="progressbar" aria-valuenow="${item.percentage}" aria-valuemin="0" aria-valuemax="100">
								<div class="progress-bar ${item.className}" style="width: ${item.percentage}%"></div>
							</div>
						</div>
                    </div>`;

			LIST_WRAPPER.innerHTML = list;
		});
	}
};

// Reload Map and chart to match dark/light mode when switched
// This function will be used in `colorMode.js`
export const leadsSourcesUpdate = () => {
	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(leadsSourcesChart);
		});
	}
};
