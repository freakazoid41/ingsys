import { DATA } from "./data";
import contactImages from "../../../img/contacts/*.jpg";
import { avatarCap, avatarImg } from "../../utils";

export const contacts = () => {
	const WRAPPER = document.getElementById("contacts-list");

	if (WRAPPER) {
		DATA.map((item, index) => {
			let listItems = "";
			let avatar = "";
			let active = "";
			let group = document.createElement("div");
			group.id = `contacts-list-${item.group}`;
			group.className = "position-relative";
			WRAPPER.appendChild(group);

			item.items.map((contact, contactIndex) => {
				// Set avatar
				if (contact.img) {
					avatar = avatarImg(contactImages[contact.img], contact.name);
				} else {
					avatar = avatarCap(contact.cap, contact.color);
				}

				// Set active class
				index === 0 && contactIndex === 0 ? (active = "bg-active") : (active = "bg-hover");

				listItems += `<button type="button" data-sa-toggle="body" class="${active} d-flex align-items-center w-100 text-start p-2 rounded mb-px">
                                    ${avatar}
                                    <div class="flex-1 overflow-hidden">
                                        <div class="text-body-emphasis fw-medium truncate mb-0.5">${contact.name}</div>
                                        <div class="text-body-secondary fs-7">${contact.email}</div>
                                    </div>
                                </button>`;
			});

			document.getElementById(`contacts-list-${item.group}`).innerHTML = listItems;
		});
	}
};
