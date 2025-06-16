import { DATA } from "./data";
import galleryImages from "../../../img/gallery/thumbs/*.jpg";

export const photos = () => {
	const WRAPPER = document.getElementById("photos");
	const HEADER = document.getElementById("photos-header");
	const ACTION = document.getElementById("photos-actions");
	const AMOUNT = document.getElementById("photos-amount");
	const CLEAR = document.getElementById("photos-clear");
	let selectedPhotos = [];

	// Select function to select individual and group photos
	const SELECT = (elem, arr) => {
		if (elem.checked) {
			selectedPhotos = selectedPhotos.filter((x) => !arr.includes(x));
			selectedPhotos.push(...arr);
		} else {
			selectedPhotos = selectedPhotos.filter((x) => !arr.includes(x));
		}
		RENDER_PHOTOS();
	};

	// Clear selected photos on back icon click
	CLEAR &&
		CLEAR.addEventListener("click", () => {
			selectedPhotos = [];
			RENDER_PHOTOS();
		});

	// Main render function
	const RENDER_PHOTOS = () => {
		WRAPPER.innerHTML = "";

		// Toggle photos action header
		if (selectedPhotos.length > 0) {
			HEADER.classList.add("d-none");
			ACTION.classList.remove("d-none");
			ACTION.classList.add("content-header-active");

			// Update amount of selected photos
			AMOUNT.innerHTML = selectedPhotos.length;
		} else {
			HEADER.classList.remove("d-none");
			ACTION.classList.add("d-none");
			ACTION.classList.remove("content-header-active");
		}

		// Loop through the data
		DATA.map((group) => {
			// Conditional class to toggle the visibility of the photo select checkbox
			const CHECKBOX_TOGGLE_CLASS = selectedPhotos.length > 0 ? "d-block" : "d-none";

			// Create wrapper element for the photo group
			let photoGroup = document.createElement("div");
			photoGroup.classList.add("mt-4");

			// Photo group header
			let photoGroupHeader = document.createElement("div");
			let photoGroupHeaderContent = `<div class="d-flex align-items-center">
                                                <div>${group.time}</div>
                                                <div class="fs-7 text-body-secondary ms-2 d-none d-sm-block">
                                                    ${group.location}
                                                    -
                                                    ${DATA.find((x) => x.id === group.id).items.length} Photos
                                                </div>
                                            </div>`;
			photoGroupHeader.classList.add("mb-3", "d-flex", "align-items-center");
			photoGroupHeader.innerHTML = photoGroupHeaderContent;

			// To select all the photos in the group, we need to send
			// a list of IDs as an array to the select function
			let groupPhotos = [];
			let groupPhotosArr = DATA.find((x) => x.id === group.id).items;
			groupPhotosArr.map((x) => groupPhotos.push(x.id));

			// Create and append the checkbox to select all photos
			let selectAll = document.createElement("input");
			selectAll.type = "checkbox";
			selectAll.classList.add("form-check-input", "mt-0", "ms-2", "me-3", CHECKBOX_TOGGLE_CLASS);
			selectAll.checked = groupPhotos.every((v) => selectedPhotos.includes(v));
			selectAll.onclick = () => SELECT(selectAll, groupPhotos);
			photoGroupHeader.prepend(selectAll);
			photoGroup.appendChild(photoGroupHeader);

			// Create and append the wrapper for the individiual photo items
			// Append this inside the photo group created above.
			let photoGallery = document.createElement("div");
			photoGallery.classList.add("grid", "gap-2");
			photoGroup.appendChild(photoGallery);

			// Loop through the photo items and create/append the individual photo items and checkboxes
			group.items.map((photo) => {
				// Create and append the checkbox to select indivisual photo items
				let selectItem = document.createElement("input");
				selectItem.type = "checkbox";
				selectItem.checked = selectedPhotos.includes(photo.id);
				selectItem.classList.add(CHECKBOX_TOGGLE_CLASS, "photos-check", "form-check-input", "position-absolute", "top-0", "start-0", "m-2");
				selectItem.onclick = () => SELECT(selectItem, [photo.id]);

				// Create photo item
				let photoItem = document.createElement("div");
				let activeClass = selectedPhotos.length > 0 ? "photos-highlight" : "";
				photoItem.setAttribute("class", `${activeClass} g-col-4 g-col-sm-3 g-col-md-2 position-relative photos-item`);
				photoItem.innerHTML = `<img class="rounded w-100" src="${galleryImages[photo.photo]}" alt="" />`;
				photoItem.appendChild(selectItem);

				// Append the photo item to the photo gallery
				photoGallery.appendChild(photoItem);
			});

			// Append the photo group to the main photos wrapper
			WRAPPER.appendChild(photoGroup);
		});
	};

	// Render photos
	WRAPPER && RENDER_PHOTOS();
};
