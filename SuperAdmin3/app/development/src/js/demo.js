import * as bootstrap from "bootstrap";

export const demo = () => {
	//----------------------------------------------
	// TOC
	//----------------------------------------------
	const TOC_WRAPPER = document.getElementById("toc-wrapper");
	if (TOC_WRAPPER) {
		let tocTitles = document.querySelectorAll(".card-title");
		let tocGroupTitles = document.querySelectorAll(".card-group-title");
		let list = "";
		let titles = "";

		if (tocGroupTitles.length > 0) {
			titles = tocGroupTitles;
		} else {
			titles = tocTitles;
		}

		titles.forEach((item) => {
			let id = item.innerText.replace(/\s+/g, "-").toLowerCase();
			item.parentNode.id = id;
			list += `<a class="dropdown-item" href="#${id}">${item.innerText}</a>`;
		});

		TOC_WRAPPER.innerHTML = `<div class="dropdown me-n2">
                                    <button class="icon-link" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ph ph-list-dashes"></i>
                                        Navigate
                                        <i class="ph ph-caret-down fs-7"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        ${list}
                                    </div>
                                </div>`;
	}

	//----------------------------------------------
	// Toast
	//----------------------------------------------
	const toastTrigger = document.getElementById("liveToastBtn");
	const toastLiveExample = document.getElementById("liveToast");

	if (toastTrigger) {
		if (toastTrigger) {
			const toastBootstrap = bootstrap.Toast.getOrCreateInstance(toastLiveExample);
			toastTrigger.addEventListener("click", () => {
				toastBootstrap.show();
			});
		}
	}
};
