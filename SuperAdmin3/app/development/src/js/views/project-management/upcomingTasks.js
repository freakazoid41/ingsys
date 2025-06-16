import { evenRow } from "../../utils";
import images from "../../../img/contacts/*.jpg";

export const upcomingTasks = () => {
	const WRAPPER = document.getElementById("list-upcoming-tasks");
	const DATA = [
		{
			project: "Website Redesign",
			task: "Develop User Registration Module",
			risk: "At Risk",
			endDate: "2023-09-05",
			assignee: "John Smith",
			img: 1,
			completion: 65,
			color: "bg-primary",
		},
		{
			project: "Mobile App Launch",
			task: "Design UI Mockups for Mobile App",
			risk: "On Track",
			endDate: "2023-09-08",
			assignee: "Emily Brown",
			img: 2,
			completion: 52,
			color: "bg-warning",
		},
		{
			project: "Software Upgrade",
			task: "Conduct User Acceptance Testing (UAT)",
			risk: "On Track",
			endDate: "2023-09-12",
			assignee: "QA Team",
			img: 3,
			completion: 73,
			color: "bg-success",
		},
		{
			project: "Quarterly Report",
			task: "Prepare Project Status Report",
			risk: "On Track",
			endDate: "2023-09-14",
			assignee: "Project Manager",
			img: 4,
			completion: 41,
			color: "bg-danger",
		},
		{
			project: "Marketing",
			task: "Review and Approve Marketing Materials",
			risk: "On Track",
			endDate: "2023-09-18",
			assignee: "Marketing Team",
			img: 5,
			completion: 62,
			color: "bg-primary",
		},
		{
			project: "Code Optimization",
			task: "Code Refactoring for Performance Optimization",
			risk: "Planned",
			endDate: "2023-09-20",
			assignee: "Development Team",
			img: 6,
			completion: 76,
			color: "bg-primary",
		},
		{
			project: "Employee Training",
			task: "Plan and Schedule Team Training Session",
			risk: "At Risk",
			endDate: "2023-09-23",
			assignee: "Training Coordinator",
			img: 7,
			completion: 58,
			color: "bg-teal",
		},
		{
			project: "Documentation",
			task: "Update Documentation for New Feature Release",
			risk: "Delayed",
			endDate: "2023-09-25",
			assignee: "Technical Writer",
			img: 8,
			completion: 67,
			color: "bg-purple",
		},
		{
			project: "Budget Planning",
			task: "Finalize Budget Proposal for Q4",
			risk: "On Track",
			endDate: "2023-09-28",
			assignee: "Finance Team",
			img: 9,
			completion: 49,
			color: "bg-info",
		},
		{
			project: "Client Engagement",
			task: "Conduct Client Feedback Session",
			risk: "Planned",
			endDate: "2023-09-30",
			assignee: "Account Manager",
			img: 10,
			completion: 71,
			color: "bg-info",
		},
	];
	let list = "";
	let badgeClass = {
		"At Risk": "bg-danger",
		"On Track": "bg-success",
		Planned: "bg-info",
		Delayed: "bg-warning",
	};

	if (WRAPPER) {
		DATA.forEach((item, index) => {
			list += `<div class="py-3 rounded d-flex align-items-center justify-content-between ${evenRow(index)}">
                        <div class="w-sm-40 px-3 flex-shrink-0">
                            ${item.project}
                        </div>
                        <div class="w-sm-16 px-3 flex-shrink-0">
							<span class="badge rounded-pill fs-8 ms-auto d-inline-flex align-items-center mt-n1 bg-opacity-75 ${badgeClass[item.risk]}">${item.risk}</span>
						</div>
                        <div class="w-28 px-3 text-end flex-shrink-0 d-none d-sm-block">
							${item.endDate}
						</div>
                        <div class="w-14 px-3 text-end flex-shrink-0 d-none d-sm-block">${item.completion}%</div>
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
