export const health = () => {
	const WRAPPER = document.getElementById("list-health");
	let list = "";
	const DATA = [
		{
			icon: "ph-clock",
			title: "Time",
			description: "30% ahead of schedule",
			feedback: "ph-calendar-check",
			feedbackColor: "text-success",
		},
		{
			icon: "ph-dots-three-circle",
			title: "Workload",
			description: "5 tasks overdue",
			feedback: "ph-warning-octagon",
			feedbackColor: "text-danger",
		},
		{
			icon: "ph-check-circle",
			title: "Tasks",
			description: "23 tasks to be completed",
			feedback: "ph-warning-circle",
			feedbackColor: "text-warning",
		},
		{
			icon: "ph-chart-pie",
			title: "Progress",
			description: "76% completed",
			feedback: "ph-calendar-check",
			feedbackColor: "text-success",
		},
		{
			icon: "ph-currency-circle-dollar",
			title: "Budget",
			description: "10.43% under budget",
			feedback: "ph-check-circle",
			feedbackColor: "text-success",
		},
	];

	if (WRAPPER) {
		DATA.forEach((item, index) => {
			list += `<div class="d-flex align-items-center mt-3">
                        <div class="w-28 flex-shrink-0">
                            <div class="rounded border text-body-emphasis py-1.5 px-2.5 d-inline-flex align-items-center">
                                <i class="ph fs-4 me-1.5 ${item.icon}"></i>
                                ${item.title}
                            </div>
                        </div>
                        <div class="ms-2 flex-grow-1 ${DATA.length - 1 !== index ? "border-bottom pb-3 mb-n3" : ""}">${item.description}</div>
                        <i class="ph fs-3 ms-3 d-none d-sm-block ${item.feedback} ${item.feedbackColor}"></i>
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
