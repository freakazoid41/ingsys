export const leadsByIndustry = () => {
	//---------------------------------------------------------
	// Data
	//---------------------------------------------------------
	let list = "";
	const LIST_WRAPPER = document.getElementById("list-leads-industry");
	const DATA = [
		{
			source: "Software and Technology",
			percentage: 43,
			change: 52.6,
			className: "bg-primary",
			leads: 23981,
			up: true,
		},
		{
			source: "Energy",
			percentage: 37,
			change: 31.3,
			className: "bg-teal",
			leads: 16342,
			up: true,
		},
		{
			source: "Project Development",
			percentage: 32,
			change: 10.5,
			className: "bg-purple",
			leads: 9736,
			up: false,
		},
		{
			source: "Consultant",
			percentage: 19,
			change: 17.2,
			className: "bg-info",
			leads: 7632,
			up: false,
		},
		{
			source: "Miscellaneous",
			percentage: 12,
			change: 23.1,
			className: "bg-warning",
			leads: 4535,
			up: false,
		},
	];

	if (LIST_WRAPPER) {
		DATA.forEach((item) => {
			list += `<div class="d-flex align-items-start">
                        <i class="${item.className} w-2.5 h-2.5 mt-1.5 rounded-circle me-3"></i>

                        <div class="flex-grow-1 d-flex align-items-center flex-wrap">
							<div class="flex-grow-1 text-body-emphasis">${item.source}</div>

							<div class="fs-5 text-body-emphasis text-end w-12">${item.percentage}%</div>

							<div class="badge rounded-pill ms-5 fs-8 text-body-emphasis d-inline-flex align-items-center mt-n1 bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
								${item.change}%

								<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
							</div>
														
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
export const leadsByIndustryUpdate = () => {
	const updateBorderColors = () => {
		leadsByIndustryChart.data.datasets[0].borderColor = cssVar("--bs-chart-pie-border-color");
	};

	if (CHART_WRAPPER) {
		setTimeout(() => {
			reloadChart(leadsByIndustryChart, updateBorderColors());
		});
	}
};
