import images from "../../../img/products/*.jpg";

export const topProducts = () => {
	const WRAPPER = document.getElementById("top-products");

	if (WRAPPER) {
		let list = "";
		const DATA = [
			{
				img: 1,
				name: "Hempthon Professional Makeup Setting Oil, 60ml",
				orders: 4532,
				sales: "$12,924.00",
				up: false,
				percentage: 9.43,
				category: "Skin Care",
			},
			{
				img: 2,
				name: "Fenton Shaker Bottle for Protein Mixes BPA-Free Leak Proof 750ml",
				orders: 4487,
				sales: "$11,324.67",
				up: true,
				percentage: 12.43,
				category: "Sports Nutrition",
			},
			{
				img: 3,
				name: "Bioskep Simple Hydrating Light Daily Face Moisturizer, 125ml",
				orders: 3982,
				sales: "$10,924.00",
				up: false,
				percentage: 32.38,
				category: "Men's Grooming",
			},
			{
				img: 4,
				name: "Fantasi Shaving Foam, DEEP Smooth Shave Antibacterial, 200ml",
				orders: 3241,
				sales: "$8,093.21",
				up: true,
				percentage: 8.23,
				category: "Men's Grooming",
			},
			{
				img: 5,
				name: "Davids Perfume Cool Water for women, 250ml",
				orders: 2985,
				sales: "$7,653.63",
				up: true,
				percentage: 24.54,
				category: "Perfumes",
			},
			{
				img: 6,
				name: "Turmeric Healing Night Beauty Balm for Dark Spots, 40gm",
				orders: 2543,
				sales: "$7,012.93",
				up: false,
				percentage: 2.43,
				category: "Skin Care",
			},
			{
				img: 7,
				name: "ChoicePerfect Boost 10% Azelaic Acid Booster, 1oz Tube",
				orders: 2134,
				sales: "$6,837.90",
				up: true,
				percentage: 4.56,
				category: "Hair Care",
			},
		];

		DATA.forEach((item) => {
			list += `<a href="" class="bg-hover border border-transparent d-flex align-items-start py-3 px-3 mx-n3 rounded">
                        <img alt="" class="w-11 rounded me-4" src="${images[item.img]}" />
                        <div class="flex-grow-1 pe-5 text-truncate">
                            <div class="text-body-emphasis mb-1 text-truncate">${item.name}</div>
                            <div class="fs-7 text-body-secondary ts-none">${item.orders} items sold</div>
                        </div>
                        <div class="badge rounded-pill fs-8 ms-auto text-body-emphasis d-inline-flex align-items-center mt-n1 bg-opacity-50 ${item.up ? "bg-success" : "bg-danger"}">
                            <i class="ph ${item.up ? "ph-arrow-circle-up" : "ph-arrow-circle-down"} fs-4 me-1"></i>
                            ${item.percentage}%
                        </div>
                    </a>`;
		});

		WRAPPER.innerHTML = list;
	}
};
