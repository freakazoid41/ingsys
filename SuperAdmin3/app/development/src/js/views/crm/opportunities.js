import images from "../../../img/contacts/*.jpg";

export const opportunities = () => {
	const WRAPPER = document.getElementById("list-opportunities");
	const DATA = [
		{ employee_name: "John Doe", img: 1, qualified: 45, won: 62, total_amount: 657321 },
		{ employee_name: "Jane Smith", img: 2, qualified: 36, won: 27, total_amount: 576099 },
		{ employee_name: "Michael Johnson", img: 3, qualified: 59, won: 25, total_amount: 498567 },
		{ employee_name: "Emily Brown", img: 4, qualified: 68, won: 45, total_amount: 310393 },
		{ employee_name: "David Lee", img: 5, qualified: 29, won: 54, total_amount: 125478 },
		{ employee_name: "Sarah Wilson", img: 6, qualified: 64, won: 63, total_amount: 98563 },
	];
	let list = "";

	if (WRAPPER) {
		DATA.forEach((item, index) => {
			// Even row background
			let rowClass = "";
			if (index % 2 === 0) {
				rowClass = "bg-highlight";
			} else {
				rowClass = "";
			}

			list += `<div class="d-flex justify-content-between align-items-center h-12 rounded px-3 ${rowClass}">
                        <div class="flex-shrink-0">
                            <img class="w-8 h-8 me-3 rounded-circle" src="${images[item.img]}" alt="${item.employee_name}">
                        </div> 
                        <div class="flex-grow-1 text-truncate">${item.employee_name}</div>
                        <div class="w-20 text-end d-none d-sm-block">${item.qualified}</div>
                        <div class="w-10 w-sm-20 text-end flex-shrink-0">${item.won}</div>
                        <div class="w-20 text-end flex-shrink-0 ms-sm-2">$${item.total_amount.toLocaleString()}</div>
                    </div>
            `;
		});

		WRAPPER.innerHTML = list;
	}
};
