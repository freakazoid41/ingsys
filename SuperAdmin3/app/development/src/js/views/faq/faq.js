import { DATA } from "./data";

export const faq = () => {
	const WRAPPER = document.getElementById("faq");
	let list = "";

	if (WRAPPER) {
		DATA.map((item) => {
			let faqLinks = document.createElement("div");

			let faqCategory = document.createElement("h3");
			faqCategory.innerText = item.category;

			item.links.map((link) => {
				let faqLinkItem = document.createElement("a");
				faqLinkItem.href = "#";
				faqLinkItem.innerText = link;
				faqLinkItem.classList.add("d-block", "border-bottom", "py-2", "link-body");
				faqLinkItem.setAttribute("data-bs-toggle", "modal");
				faqLinkItem.setAttribute("data-bs-target", "#faq-details");
				faqLinks.appendChild(faqLinkItem);
			});

			list += `<div class="card g-col-12 g-col-md-6 g-col-xl-4 px-7 py-6">
                        <h3 class="d-flex align-items-center text-body-emphasis mb-5">
                            <div class="flex-grow-1 fs-5">${item.category}</div> 
                            <i class="ms-auto fs-2 me-n1 ph ${item.icon}"></i>
                        </h3>
                        ${faqLinks.outerHTML}
                        <a href="#" class="link-muted fs-7 d-flex align-items-center mt-5">
                            Browse all questions

							<i class="ph ph-arrow-right ms-auto"></i>	
                        </a>
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
