export const openRateByDevice = () => {
	const WRAPPER = document.getElementById("list-open-rate-by-device");
	let list = "";
	const DATA = [
		{
			icon: "ph-laptop",
			percentage: 62,
			sessions: 12453,
			bg: "bg-info",
			text: "text-info",
		},
		{
			icon: "ph-device-mobile-camera",
			percentage: 30,
			sessions: 4853,
			bg: "bg-purple",
			text: "text-purple",
		},
		{
			icon: "ph-device-tablet-speaker",
			percentage: 8,
			sessions: 983,
			bg: "bg-warning",
			text: "text-warning",
		},
	];

	if (WRAPPER) {
		DATA.forEach((item) => {
			list += `<div style="--bs-bg-opacity: 0.2" class="flex-grow-1 rounded d-flex align-items-center py-1 mb-5 ${item.bg + " " + item.text}">
                    <i class="ph fs-4 mx-3 ${item.icon}"></i>
                    <div class="flex-grow-1 d-flex align-items-center">
                        <div class="h-8 d-flex align-items-center justify-content-end rounded-1 bg-current" style="width: ${item.percentage + "%"}">
                            ${item.percentage > 10 ? `<div class="text-white fs-8 px-2">${item.percentage}%</div>` : ""}
                        </div>
                        ${item.percentage <= 9 ? `<div class="text-current fs-8 px-2">${item.percentage}%</div>` : ""}
                    </div>
                    <div class="ms-auto pe-3 text-current w-14 text-end">${item.sessions}</div>
                </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
