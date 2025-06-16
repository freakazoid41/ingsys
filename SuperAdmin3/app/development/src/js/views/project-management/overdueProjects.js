export const overdueProjects = () => {
	const WRAPPER = document.getElementById("list-overdue-projects");
	let list = "";
	const DATA = [
		{
			task: "Gather stakeholder requirements for features.",
			daysPassed: 2,
			assignee: "John",
			deadline: "2023-09-01",
		},
		{
			task: "Create wireframes and UI/UX concepts.",
			daysPassed: 5,
			assignee: "Emma",
			deadline: "2023-09-05",
		},
		{
			task: "Develop backend and APIs.",
			daysPassed: 8,
			assignee: "Olivia",
			deadline: "2023-09-08",
		},
		{
			task: "Code frontend and integrate APIs.",
			daysPassed: 10,
			assignee: "Michael",
			deadline: "2023-09-15",
		},
		{
			task: "Perform unit testing and resolve errors.",
			daysPassed: 14,
			assignee: "Sophia",
			deadline: "2023-09-10",
		},
		{
			task: "Conduct cross-browser compatibility testing.",
			daysPassed: 20,
			assignee: "Daniel",
			deadline: "2023-09-18",
		},
		{
			task: "Deploy to production server.",
			daysPassed: 25,
			assignee: "Liam",
			deadline: "2023-09-20",
		},
		{
			task: "Perform user acceptance testing.",
			daysPassed: 30,
			assignee: "Ava",
			deadline: "2023-09-25",
		},
	];

	if (WRAPPER) {
		DATA.forEach((item, index) => {
			// Severity
			let severityClasses = item.daysPassed > 10 ? "text-danger-emphasis border-danger" : "text-warning-emphasis border-warning";

			list += `<div class="d-flex align-items-start lh-1">
                        <div class="flex-grow-1 text-truncate ${DATA.length - 1 !== index ? "border-bottom pb-4 mb-4" : ""}">
                            <div class="mb-2 text-truncate">${item.task}</div>
                            <div class="d-flex align-items-center text-body-secondary fs-8 gap-1">
                                <span class="border rounded-1 p-1">Assignee: ${item.assignee}</span>
                                <span class="border rounded-1 p-1">Deadline: ${item.deadline}</span>
                            </div>
                        </div>
						<i class="ph ph-warning-octagon fs-2 ms-3 mt-2 d-none d-sm-block ${item.daysPassed > 10 ? "text-danger" : "text-warning"}"></i>	
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
