export const contentSidebar = () => {
	const TOGGLES = document.querySelectorAll("[data-sa-toggle]");
	const CONTENT = document.getElementById("content");

	if (TOGGLES.length > 0) {
		const toggleHandler = (e) => {
			const target = e.currentTarget.getAttribute("data-sa-toggle");

			if (target === "body") {
				CONTENT.classList.remove("content-list-toggled");
				CONTENT.classList.add("content-body-toggled");
			} else if (target === "list") {
				CONTENT.classList.remove("content-body-toggled");
				CONTENT.classList.add("content-list-toggled");
			}
		};

		TOGGLES.forEach((el) => {
			el.addEventListener("click", toggleHandler);
		});
	}
};
