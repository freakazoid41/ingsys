import { TODO_DATA, DETAILS } from "./data";
import { FILES_DATA } from "../file-manager/data";
import galleryImages from "../../../img/gallery/thumbs/*.jpg";

export const todoList = () => {
	const WRAPPER = document.getElementById("todo-lists");

	if (WRAPPER) {
		// Main list
		(() => {
			WRAPPER.innerHTML = "";
			TODO_DATA.map((item, index) => {
				let attachement = `<div class="text-body-secondary fs-7 ms-4 align-items-center d-none d-lg-flex">
                                        <i class="ph ph-paperclip fs-6 me-1"></i>
                                        <span class="text-body-secondary">
                                            ${item.files.length}
                                            ${item.files.length > 1 ? " Attachements" : " Attachement"}
                                        </span>
                                    </div>`;

				let dueDate = `<div class="text-body-secondary fs-7 align-items-center d-none d-sm-flex flex-shrink-0">
                                    <i class="ph ph-calendar-blank fs-6 me-1"></i>
                                    ${item.due.date}
                                </div>`;

				let listItem = document.createElement("div");
				listItem.className = "card card-hover flex-row align-items-center mt-1.5";

				let checkbox = document.createElement("input");
				checkbox.type = "checkbox";
				checkbox.classList.add("form-check-input", "todo-checkbox", "mt-0", "me-3");
				checkbox.checked = item.completed;

				let listTag = document.createElement("i");
				listTag.classList.add("w-1", "rounded", "h-5", "mx-3", "flex-shrink-0", `bg-${item.color}`, "bg-opacity-50");

				listItem.innerHTML = `<a href="" class="flex-grow-1 d-flex gap-5 align-items-center py-3.5 pe-4 min-w-0" data-bs-toggle="modal" data-bs-target="#todo-list-details">
                                            <div class="text-truncate flex-grow-1 text-body-emphasis lh-base fw-medium min-w-0">${item.title}</div>
                                            ${item.due.date ? dueDate : ""}
                                            ${item.files.length > 0 ? attachement : ""}
                                        </a>`;
				listItem.prepend(checkbox);
				listItem.prepend(listTag);

				WRAPPER.appendChild(listItem);
			});
		})();

		// Sub tasks
		(() => {
			const SUB_TASK_WRAPPER = document.getElementById("todo-sub-tasks");
			let list = "";
			let last = DETAILS.length - 1;

			if (SUB_TASK_WRAPPER) {
				DETAILS.map((item, index) => {
					list += `<div class="form-check">
                                <input type="checkbox" class="form-check-input todo-checkbox">
                                <div class="pb-3 ${last !== index ? "border-bottom mb-3" : ""}">${item.task}</div>
                            </div>`;
				});

				SUB_TASK_WRAPPER.innerHTML = list;
			}
		})();

		// Files
		(() => {
			const ATTACHEMENT_WRAPPER = document.getElementById("todo-attachements");
			let list = "";
			let icon = "";

			if (ATTACHEMENT_WRAPPER) {
				FILES_DATA.slice(0, 5).map((item, index) => {
					if (item.type === "image") {
						icon = `<img class="w-8 h-8 rounded-circle me-3 flex-shrink-0" src="${galleryImages[item.img]}" alt="">`;
					} else {
						icon = `<i class="ph rounded-circle w-8 h-8 me-3 fs-5 flex-shrink-0 d-grid place-content-center bg-highlight-inverse text-body ${item.icon}"></i>`;
					}

					list += `<a href="#" class="d-flex align-items-center rounded py-3 px-3 bg-hover-emphasis">
                            ${icon}
                            <div class="flex-grow-1 lh-1">
                                <div class="text-body-emphasis mb-2">${item.name}</div>
                                <div class="text-body-secondary fs-7">
                                    Added on ${item.date}
                                    <div class="float-end d-none d-sm-inline">${item.size}</div>
                                </div>
                            </div>
                        </a>`;
				});

				ATTACHEMENT_WRAPPER.innerHTML = list;
			}
		})();
	}
};
