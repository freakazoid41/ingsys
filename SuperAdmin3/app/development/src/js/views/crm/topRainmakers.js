import images from "../../../img/contacts/*.jpg";

export const topRainmakers = () => {
	const WRAPPER = document.getElementById("list-top-rainmakers");
	const DATA = [
		{ img: 11, name: "John Smith", total_sales: 1200000, deals_closed: 45, performance: 10.5 },
		{ img: 12, name: "Jane Johnson", total_sales: 950000, deals_closed: 50, performance: 7.2 },
		{ img: 13, name: "Michael Williams", total_sales: 850000, deals_closed: 38, performance: 8.1 },
		{ img: 14, name: "Emily Davis", total_sales: 780000, deals_closed: 42, performance: 9.5 },
		{ img: 15, name: "David Brown", total_sales: 720000, deals_closed: 40, performance: 8.9 },
	];
	let list = "";
	let last = DATA.length - 1;

	if (WRAPPER) {
		DATA.forEach((item, index) => {
			list += `<div class="d-flex align-items-center ${index !== last ? "mb-5" : "mb-2"}">
                        <div class="rounded-circle border border-dashed border-danger me-3 flex-shrink-0 w-12 h-12 d-grid place-items-center" style="--bs-border-opacity: 0.3">
							<img class="w-10 h-10 rounded-circle" src="${images[item.img]}" alt="${item.name}">
						</div>

                        <div class="flex-grow-1">
                            <div class="mb-1 ps-1">${item.name}</div>
                            <div class="text-body-secondary fs-7">
                                <span class="border rounded ms-npx px-1.5 py-0.5 d-inline-block">Deals Closed: ${item.deals_closed}</span>
                                <span class="border rounded ms-0.5 px-2 py-0.5 d-none d-sm-inline-block">Performance: ${item.performance}</span>
                            </div>
                        </div>
                         
                        <div class="fw-medium text-body-emphasis">$${item.total_sales.toLocaleString()}</div>
                    </div>
            `;
		});

		WRAPPER.innerHTML = list;
	}
};
