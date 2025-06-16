import { DATA } from "./data";
import contactImages from "../../../img/contacts/*.jpg";
import { avatarCap, avatarImg } from "../../utils";

export const mail = () => {
	const WRAPPER = document.getElementById("mail-list");
	let list = "";
	let avatar = "";
	let active = "";
	let unread = "";

	if (WRAPPER) {
		DATA.map((item, index) => {
			// Set avatar
			if (item.img) {
				avatar = avatarImg(contactImages[item.img], item.from);
			} else {
				avatar = avatarCap(item.cap, item.color, item.border);
			}

			// Set active class
			index === 0 && index === 0 ? (active = "bg-active") : (active = "bg-hover");

			// Set unread indicator
			item.unread ? (unread = `<i class="w-1 h-1 rounded-circle position-absolute top-0 bottom-0 my-auto start-0 ms-n2 bg-white"></i>`) : (unread = "");

			list += `<button type="button" data-sa-toggle="body" class="${active} d-flex align-items-center w-100 text-start p-2 rounded mb-px">
                        <div class="position-relative">
                            ${unread}
                            ${avatar}
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate d-flex align-items-center mb-0.5 ${unread ? "text-body-emphasis" : ""}">
                                <div class="text-body-emphasis fw-medium truncate mb-0.5">${item.from}</div>
                                <div class="text-body-secondary fs-8 ms-auto">${item.time.short}</div>
                            </div>
                            <div class="text-body-secondary fs-7 text-truncate">${item.subject}</div>
                        </div>
                    </button>`;
		});

		WRAPPER.innerHTML = list;
	}
};
