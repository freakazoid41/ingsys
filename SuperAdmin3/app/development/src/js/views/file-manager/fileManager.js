import filesImages from "../../../img/gallery/thumbs/*.jpg";
import { FOLDERS_DATA, FILES_DATA } from "./data";

export const fileManager = () => {
	const FOLDERS_WRAPPER = document.getElementById("folders");
	const FILES_WRAPPER = document.getElementById("files");
	let foldersList = "";
	let filesList = "";

	// Folders
	if (FOLDERS_WRAPPER) {
		FOLDERS_DATA.forEach((item) => {
			foldersList += `<button type="button" class="card card-hover px-4 py-3 text-start flex-row g-col-6 g-col-sm-4 g-col-md-3 g-col-lg-3 g-col-xxl-2">
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="fw-medium text-truncate text-body-emphasis mb-0.5">${item.name}</div>
                                    <div class="text-body-secondary fs-7">${item.files} Files</div>
                                </div>
								<i class="ph ph-folder fs-4"></i>
                            </a>`;
		});

		FOLDERS_WRAPPER.innerHTML = foldersList;
	}

	// Files
	if (FILES_WRAPPER) {
		let filePreview = "";
		FILES_DATA.forEach((item) => {
			if (item.type === "image") {
				filePreview = `<div class="h-24 w-100">
                                    <img class="w-100 h-24 object-fit-cover rounded-1" src="${filesImages[item.img]}" alt="">
                                </div>`;
			} else {
				filePreview = `<div class="h-24 w-100 d-grid place-content-center rounded-1 bg-white" style="--bs-bg-opacity: 0.075;">
                                    <i class="w-10 h-10 rounded-circle d-grid place-content-center fs-3 bg-opacity-50 ph ${item.icon} ${item.color}"></i>
                                </div> `;
			}

			filesList += `<button type="button" class="card card-hover p-1.5 g-col-6 g-col-sm-4 g-col-md-3 g-col-lg-3 g-col-xxl-2">
                                ${filePreview}
                                <div class="p-2 overflow-hidden text-start">
                                    <div class="text-truncate text-body-emphasis fs-6 mb-0.5">${item.name}</div>
                                    <div class="text-body-secondary fs-7">${item.size}</div>
                                </div>
                            </button>`;
		});

		FILES_WRAPPER.innerHTML = filesList;
	}
};
