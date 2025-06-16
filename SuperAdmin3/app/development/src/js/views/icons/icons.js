import { ICONS_DATA } from "./data";

export const icons = () => {
	const WRAPPER = document.getElementById("icons");
	let list = "";

	if (WRAPPER) {
		ICONS_DATA.map((item) => {
			list += `<div class="g-col-4 g-col-sm-3 g-col-lg-2 p-3 text-center">
                        <i class="ph ph-${item} fs-1 text-body-emphasis"></i>
                        <div class="hidden sm:block text-body-secondary fs-7 mt-3">${item}</div>
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
