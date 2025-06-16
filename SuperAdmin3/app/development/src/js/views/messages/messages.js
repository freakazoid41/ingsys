import { DIRECT_MESSAGE_DATA, CONVERSATION_DATA, CHANNEL_MEMBERS, CHANNEL_FILES } from "./data";
import contactImages from "../../../img/contacts/*.jpg";
import galleryImages from "../../../img/gallery/thumbs/*.jpg";
import { avatarCap, avatarImg } from "../../utils";

export const messages = () => {
	// Direct messages
	(() => {
		const DM_WRAPPER = document.getElementById("direct-messages");
		let dMlist = "";
		let dMavatar = "";

		if (DM_WRAPPER) {
			DIRECT_MESSAGE_DATA.map((item, index) => {
				// Set avatar
				if (item.img) {
					dMavatar = `<img class="w-6 h-6 rounded-circle me-3" src="${contactImages[item.img]}" alt="" />`;
				} else {
					dMavatar = `<div class="w-6 h-6 rounded-circle me-3 fs-7 fw-medium d-flex align-items-center justify-content-center text-uppercase bg-opacity-50 ${item.color}">${item.cap}</div>`;
				}

				dMlist += `<button type="button" class="nav-link w-100" data-toggle="body">
                                ${dMavatar}
                                <div class="flex-grow-1 text-truncate overflow-hidden">${item.name}</div>
                            </button>`;
			});

			DM_WRAPPER.innerHTML = dMlist;
		}
	})();

	// Conversation
	(() => {
		const WRAPPER = document.getElementById("conversation");

		if (WRAPPER) {
			CONVERSATION_DATA.map((group) => {
				let messageGroup = document.createElement("div");
				messageGroup.id = `day-${group.id}`;
				WRAPPER.appendChild(messageGroup);

				// Set title date
				let date = `<div class="mt-7 mb-5 text-center ps-14">
								<i class="border-bottom d-block h-px mb-n4"></i>
                                <div class="h-8 px-4 bg-active text-body-emphasis rounded-pill d-inline-flex align-items-center fs-7 position-relative backdrop-blur-25">${group.day}</div>
                            </div>`;

				messageGroup.innerHTML = date;

				// Set messages
				group.chat.map((message) => {
					// Avatar
					let avatar = "";
					if (message.op.img) {
						avatar = avatarImg(contactImages[message.op.img]);
					} else {
						avatar = avatarCap(message.op.cap, message.op.color, message.op.borderColor);
					}

					// List
					let list = "";
					message.text.map((item) => {
						list += `<div id="message-${item.id}" class="message-item position-relative py-0.5 px-1.5 rounded bg-hover">
                                    ${item.text}
                                </div>`;
					});

					let messageItemGroup = document.createElement("div");
					messageItemGroup.id = `message-${message.id}`;
					messageItemGroup.classList.add("d-flex", "align-items-start", "mb-3");
					messageGroup.appendChild(messageItemGroup);

					messageItemGroup.innerHTML = `${avatar}
                                    <div class="flex-grow-1">
                                        <div class="text-body-emphasis ms-1.5 fw-medium">${message.op.name}</div>
                                        ${list}
                                    </div>`;
				});
			});

			// Message actions

			// Action dropdown
			let actions = document.createElement("div");
			actions.classList.add("message-actions");
			const MESSAGE_ACTION_WRAPPER = document.querySelectorAll(".message-item");
			const MESSAGE_ACTIONS = `<div class="bg-white bg-opacity-25 d-flex rounded position-absolute end-0 top-0 mt-n9 me-2 text-body-emphasis flex z-3 p-1 backdrop-blur-50">
										<a href="" class="icon ph ph-smiley"></a>
										<a href="" class="icon ph ph-arrow-bend-up-right"></a>
										<a href="" class="icon ph ph-push-pin-simple"></a>
										<div class="dropdown">
											<button class="icon ph ph-dots-three-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
												<span class="visually-hidden">More</span>
											</button>
											<div class="dropdown-menu dropdown-menu-end">
												<a class="dropdown-item" href="#"> <i class="ph ph-copy"></i>Copy Message</a>
												<a class="dropdown-item" href="#"> <i class="ph ph-hand-pointing"></i>Follow Message</a>
												<a class="dropdown-item" href="#"> <i class="ph ph-trash"></i>Delete</a>
												<a class="dropdown-item" href="#"> <i class="ph ph-share-fat"></i>Share</a>
											</div>
										</div>
									</div>`;

			// Toggle the action dropdown on hover
			MESSAGE_ACTION_WRAPPER.forEach((item) => {
				item.addEventListener("mouseenter", (e) => {
					actions.innerHTML = MESSAGE_ACTIONS;
					item.appendChild(actions);
				});

				item.addEventListener("mouseleave", (e) => {
					actions.remove();
				});
			});
		}
	})();

	// Channel members
	(() => {
		const MEMBERS_WRAPPER = document.getElementById("channel-members");
		let membersList = "";

		if (MEMBERS_WRAPPER) {
			CHANNEL_MEMBERS.map((item) => {
				let online;
				item.online === true ? (online = "border-success") : "";

				membersList += `<a href="#" class="d-flex align-items-center py-2 px-3 rounded bg-hover-emphasis">
									<img src="${contactImages[item.id]}" class="rounded-circle w-7 h-7 me-3 flex-shrink-0" />
									<div class="flex-grow-1 d-flex align-items-center leading-none">
										<div class="text-body-emphasis">${item.display}</div>
										<i class="w-3 h-3 mx-2 rounded-circle border border-2 ${online}"></i>
										<div class="text-body-secondary fs-7 d-none d-sm-block">${item.name}</div>
									</div>
								</a>`;
			});

			MEMBERS_WRAPPER.innerHTML = membersList;
		}
	})();

	// Channel files
	(() => {
		const FILES_WRAPPER = document.getElementById("channel-files");
		let filesList = "";

		if (FILES_WRAPPER) {
			CHANNEL_FILES.map((item) => {
				let icon;

				if (item.img) {
					icon = `<img src="${galleryImages[item.img]}" class="w-8 h-8 me-3 rounded-circle" />`;
				} else {
					icon = `<i class="ph rounded-circle w-8 h-8 me-3 fs-5 flex-shrink-0 d-grid place-content-center bg-highlight-inverse text-body ${item.icon}"></i>`;
				}

				filesList += `<a href="#" class="d-flex align-items-center py-3 px-3 bg-hover-emphasis rounded">
									${icon}
									<div class="flex-grow-1 lh-1">
										<div class="text-body-emphasis mb-2">${item.name}</div>
										<div class="text-body-secondary fs-7">by ${item.owner} on ${item.date}</div>
									</div>
								</a>`;
			});

			FILES_WRAPPER.innerHTML = filesList;
		}
	})();
};
