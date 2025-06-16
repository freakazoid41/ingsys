export const sidebar = () => {
	const CONTENT = document.getElementById("content");
	const SIDEBAR_SECONDARY = document.getElementById("sidebar-secondary");
	const SIDEBAR_SEC_CLOSE = document.getElementById("sidebar-secondary-close");
	const SIDEBAR = document.getElementById("sidebar");

	//--------------------------------------------
	// Sidebar
	//--------------------------------------------
	const SIDEBAR_TOGGLE = document.querySelectorAll(".sidebar-toggle");
	const BACKDROP = document.createElement("button");
	let isToggled = false;

	const sidebarClose = () => {
		SIDEBAR.classList.remove("toggled");
		BACKDROP.remove();
		isToggled = false;
	};

	if (SIDEBAR_TOGGLE.length > 0) {
		SIDEBAR_TOGGLE.forEach((el) => {
			BACKDROP.setAttribute("type", "button");
			BACKDROP.setAttribute("class", "backdrop d-xl-none");
			BACKDROP.onclick = () => sidebarClose();

			el.addEventListener("click", (e) => {
				e.preventDefault();

				// Close secondary sidebar if opened
				if (SIDEBAR_SECONDARY && SIDEBAR_SEC_CLOSE) {
					if (SIDEBAR_SECONDARY.classList.contains("toggled")) {
						SIDEBAR_SEC_CLOSE.click();
					}
				}

				isToggled = !isToggled;
				SIDEBAR.classList.toggle("toggled", isToggled);
				if (isToggled) {
					SIDEBAR.insertAdjacentElement("afterend", BACKDROP);
				} else {
					BACKDROP.remove();
				}
			});
		});

		// Close
		const SIDEBAR_CLOSE = document.getElementById("sidebar-close");
		SIDEBAR_CLOSE.addEventListener("click", (e) => {
			e.preventDefault();
			sidebarClose();
		});
	}

	//--------------------------------------------
	// Sidebar secondary
	//--------------------------------------------
	const SIDEBAR_SEC_TOGGLE = document.getElementById("sidebar-secondary-toggle");

	if (SIDEBAR_SEC_TOGGLE) {
		let isToggled = false;

		const BACKDROP = document.createElement("button");
		BACKDROP.setAttribute("type", "button");
		BACKDROP.setAttribute("class", "backdrop d-xl-none");
		BACKDROP.onclick = () => {
			close();
		};

		const close = () => {
			SIDEBAR_SECONDARY.classList.remove("toggled");
			BACKDROP.remove();
			isToggled = false;
		};

		SIDEBAR_SEC_TOGGLE.addEventListener("click", (e) => {
			e.preventDefault();
			isToggled = !isToggled;

			SIDEBAR_SECONDARY.classList.toggle("toggled", isToggled);
			if (isToggled) {
				CONTENT.insertAdjacentElement("afterend", BACKDROP);
			}
		});

		SIDEBAR_SEC_CLOSE.addEventListener("click", (e) => {
			e.preventDefault();
			close();
		});
	}

	//----------------------------------
	// Sidebar menu
	//----------------------------------
	const SUB_LIST = document.querySelector(".menu-sub");

	if (SUB_LIST) {
		const SUB_MENU_TOGGLES = document.querySelectorAll(".menu-sub > a");

		SUB_MENU_TOGGLES.forEach((el) => {
			let parent = el.closest(".menu-sub");
			let isOpened = parent.classList.contains("active");
			let isToggling = false;

			const toggleSubMenu = (el) => {
				isOpened = !isOpened;
				parent.classList.toggle("opened", isOpened);

				if (isOpened) {
					// Menu opening animation
					isToggling = true;
					el.style.height = "0px";
					el.style.opacity = "0";
					el.style.display = "block";

					setTimeout(() => {
						el.style.height = el.scrollHeight + "px";
						el.style.opacity = "1";

						setTimeout(() => {
							el.style.height = "";
							isToggling = false;
						}, 300);
					});
				} else {
					// Menu closing animation
					isToggling = true;
					el.style.height = el.scrollHeight + "px";
					el.style.opacity = "0";

					setTimeout(() => {
						el.style.height = "0px";

						setTimeout(() => {
							el.style.display = "none";
							isToggling = false;
						}, 300);
					});
				}
			};

			el.addEventListener("click", (e) => {
				e.preventDefault();
				if (isToggling) return;
				toggleSubMenu(el.nextElementSibling);
			});
		});
	}

	//----------------------------------
	// Sidebar menu active class
	//----------------------------------
	const ACTIVE_PAGE = document.documentElement.dataset.page;
	const menuLinks = document.querySelectorAll(".menu > li > a");
	const menuSubLinks = document.querySelectorAll(".menu-sub > ul > li > a");

	menuLinks.forEach((el) => {
		let link = el.innerText.toLowerCase().replace(/\s/g, "-");

		if (ACTIVE_PAGE === link) {
			el.parentNode.classList.add("active");
		}
	});

	menuSubLinks.forEach((el) => {
		let parent = el.closest(".menu-sub").innerText.toLowerCase().replace(/\s/g, "-");
		let link = el.innerText.toLowerCase().replace(/\s/g, "-");

		if (ACTIVE_PAGE === `${parent}-${link}`) {
			el.classList.add("active");
			el.closest(".menu-sub").classList.add("opened", "active");
		}
	});
};
