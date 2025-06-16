import images from "../../../img/contacts/*.jpg";

export const campaignPerformance = () => {
	const WRAPPER = document.getElementById("list-campaign-performance");
	const DATA = [
		{ campaign: "Weekly Newsletter", clicks: 1200, conversions: 80, revenue: 1500 },
		{ campaign: "Summer Promo", clicks: 800, conversions: 40, revenue: 1000 },
		{ campaign: "Google Ads Campaign", clicks: 1500, conversions: 120, revenue: 2000 },
		{ campaign: "Daily Customer Update", clicks: 500, conversions: 30, revenue: 750 },
		{ campaign: "Content Marketing", clicks: 1000, conversions: 60, revenue: 900 },
		{ campaign: "Event Sponsorship", clicks: 300, conversions: 20, revenue: 400 },
		{ campaign: "VIP Announcement", clicks: 1100, conversions: 70, revenue: 1300 },
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
                            
                        </div> 
                        <div class="flex-grow-1 text-truncate">${item.campaign}</div>
                        <div class="w-20 text-end d-none d-sm-block">${item.clicks}</div>
                        <div class="w-16 w-sm-20 text-end flex-shrink-0 ms-sm-2">${item.conversions}</div>
                        <div class="w-20 text-end flex-shrink-0 ms-sm-2">$${item.revenue.toLocaleString()}</div>
                    </div>
            `;
		});

		WRAPPER.innerHTML = list;
	}
};
