import Quill from "quill";
import "quill/dist/quill.core.css";
import "quill/dist/quill.snow.css";

export const quillEditor = () => {
	const QUILL_EL = document.querySelectorAll(".quill-inner");

	// Replace icons
	let icons = Quill.import("ui/icons");
	icons["bold"] = '<i class="ph ph-text-b"></i>';
	icons["italic"] = '<i class="ph ph-text-italic"></i>';
	icons["underline"] = '<i class="ph ph-text-underline"></i>';
	icons["link"] = '<i class="ph ph-link"></i>';
	icons["image"] = '<i class="ph ph-image"></i>';
	icons["list"]["ordered"] = '<i class="ph ph-list-numbers"></i>';
	icons["list"]["bullet"] = '<i class="ph ph-list-bullets"></i>';

	if (QUILL_EL.length > 0) {
		QUILL_EL.forEach((item) => {
			let placeholder = item.getAttribute("data-placeholder");

			new Quill(item, {
				modules: {
					toolbar: [
						["bold", "italic", "underline"],
						[{ list: "ordered" }, { list: "bullet" }],
						["link", "image"],
					],
				},
				placeholder: placeholder,
				theme: "snow",
			});
		});
	}
};
