import images from "../../../img/browsers/*.svg";

export const browserBounceRate = () => {
	const WRAPPER = document.getElementById("list-browser-rate");
	const DATA = [
		{
			browser: "Google Chrome",
			rate: "22443",
			percentage: "81",
			icon: "chrome",
			up: false,
		},
		{
			browser: "Apple Safari",
			rate: "18312",
			percentage: "54",
			icon: "safari",
			up: true,
		},
		{
			browser: "Mozilla Firefox",
			rate: "16234",
			percentage: "43",
			icon: "firefox",
			up: true,
		},
		{
			browser: "Microsoft Edge",
			rate: "12956",
			percentage: "32",
			icon: "edge",
			up: false,
		},
		{
			browser: "Opera",
			rate: "10090",
			percentage: "25",
			icon: "opera",
			up: false,
		},
		{
			browser: "Internet Explorer",
			rate: "8354",
			percentage: "18",
			icon: "ie",
			up: false,
		},
		{
			browser: "Samsung Internet",
			rate: "7856",
			percentage: "15",
			icon: "samsung",
			up: true,
		},
	];
	let list = "";
	let last = DATA.length - 1;

	if (WRAPPER) {
		DATA.map((item, index) => {
			list += `<div class="d-flex align-items-center">
                        <div class="w-8 h-8 me-4 border rounded d-flex align-items-center justify-content-center">
                            <img class="" src="${images[item.icon]}" alt="" />
                        </div>

                        <div class="flex-grow-1 d-flex align-items-center flex-wrap py-3 ${index !== last ? "border-bottom" : ""}">
                            <div class="flex-grow-1">${item.browser}</div>

                            <div class="fs-5 fw-medium text-end w-16">${item.rate}%</div>

                            <div class="w-20 text-end d-none d-sm-block">
								<div class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-success bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
									${item.percentage}%

									<i class="ph fs-5 ms-1 ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"}"></i>
								</div>
							</div>
                        </div>
                    </div>`;

			WRAPPER.innerHTML = list;
		});
	}
};
