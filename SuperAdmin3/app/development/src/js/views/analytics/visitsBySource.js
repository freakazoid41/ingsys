export const visitsBySource = () => {
	//---------------------------------------------------------
	// Data
	//---------------------------------------------------------
	const WRAPPER = document.getElementById("list-visits-source");
	let list = "";
	const DATA = [
		{
			source: "Google",
			visits: 23981,
			percentage: 52.6,
			className: "bg-primary",
			up: true,
		},
		{
			source: "Direct",
			visits: 16342,
			percentage: 31.3,
			className: "bg-info",
			up: true,
		},
		{
			source: "Bing",
			visits: 9736,
			percentage: 10.5,
			className: "bg-success",
			up: false,
		},
		{
			source: "Yahoo",
			visits: 5687,
			percentage: 7.1,
			className: "bg-danger",
			up: false,
		},
		{
			source: "Others",
			visits: 7632,
			percentage: 17.2,
			className: "bg-warning",
			up: false,
		},
	];

	if (WRAPPER) {
		DATA.forEach((item) => {
			list += `<div class="d-flex align-items-start">
                        <i class="${item.className} w-2.5 h-2.5 mt-1.5 rounded-circle me-3"></i>

                        <div class="flex-grow-1 d-flex align-items-center flex-wrap">
							<div class="flex-grow-1">${item.source}</div>

							<div class="fs-5 fw-medium text-end w-14">${item.visits}</div>

							<div class="w-20 text-end d-none d-sm-block">
								<div class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-success bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
									${item.percentage}%

									<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
								</div>
							</div>

							<div class="progress w-100 my-4 h-1" role="progressbar" aria-valuenow="${item.percentage}" aria-valuemin="0" aria-valuemax="100">
								<div class="progress-bar ${item.className}" style="width: ${item.percentage}%"></div>
							</div>
						</div>
                    </div>`;

			WRAPPER.innerHTML = list;
		});
	}
};
