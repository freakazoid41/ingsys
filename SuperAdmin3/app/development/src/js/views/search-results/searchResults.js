import { DATA } from "./data";

export const searchResults = () => {
	const WRAPPER = document.getElementById("search-results");
	const HIGHLIGHT_WORDS = ["sales", "leads", "sale", "lead"];
	const REGEX = new RegExp(`\\b(${HIGHLIGHT_WORDS.join("|")})\\b`, "gi");

	let list = "";

	if (WRAPPER) {
		DATA.map((item) => {
			const HIGLIGHTENED_TITLE = item.title.replace(REGEX, '<span class="bg-warning bg-opacity-75 text-black">$1</span>');

			list += `<a href="" class="card card-hover py-3 px-4 mb-2">
                        <div class="fw-medium text-body text-truncate mb-1">${HIGLIGHTENED_TITLE}</div>
                        <div class="text-body-secondary fs-7 text-truncate">${item.page}</div>
                    </a>`;
		});

		WRAPPER.innerHTML = list;
	}
};
