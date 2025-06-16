import { TODO_DATA } from "../../views/todo-lists/data";

export const tasks = () => {
	const WRAPPER = document.getElementById("top-tasks");

	if (WRAPPER) {
		let list = "";
		const LIMIT = 8;
		let last = LIMIT - 1;

		TODO_DATA.slice(0, LIMIT).map((item, index) => {
			list += `<div class="px-4 py-1">
						<div class="form-check">
							<input class="form-check-input todo-checkbox" type="checkbox" value="" id="task-${item.id}">
							<label class="form-check-label pb-3 d-block" for="task-${item.id}">
								<div class="mb-1 text-truncate text-body-emphasis">${item.title}</div>
								<div class="d-flex align-items-center text-body-secondary">
									<div class="badge bg-opacity rounded-pill truncate text-${item.color} bg-${item.color}" style="--bs-bg-opacity: 0.2">${item.label}</div>
									${
										item.due.date
											? `<div class="mx-2">-</div>
												<div class="fs-7">${item.due.date}</div>`
											: ""
									}
									<div class="mx-2">-</div>
									<div class="fs-7 fw-medium">${item.priority}</div>
								</div>
							</label>
						</div>
					</div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
