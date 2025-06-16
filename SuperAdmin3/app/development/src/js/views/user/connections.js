import connectionImages from "../../../img/contacts/*.jpg";

export const userConnections = () => {
	const WRAPPER = document.getElementById("user-connections");
	let list = "";
	const DATA = [
		{
			name: "Essie Carlson",
			handle: "@essiecarlson",
			avatar: "1",
			mutual: "33",
		},
		{
			name: "Dominic Grant",
			handle: "@dominicgrant",
			avatar: "2",
			mutual: "10",
		},
		{
			name: "Stephen McBride",
			handle: "@stephenmcbride",
			avatar: "3",
			mutual: "45",
		},
		{
			name: "Josephine Murphy",
			handle: "@josephinemurphy",
			avatar: "4",
			mutual: "22",
		},
		{
			name: "Virginia Crawford",
			handle: "@virginiacrawford",
			avatar: "5",
			mutual: "01",
		},
		{
			name: "Chase Hines",
			handle: "@chasehines",
			avatar: "6",
			mutual: "12",
		},
		{
			name: "Lula Kim",
			handle: "@lulakim",
			avatar: "7",
			mutual: "76",
		},
		{
			name: "Curtis Ryan",
			handle: "@curtisryan",
			avatar: "8",
			mutual: "89",
		},
		{
			name: "Alma Sims",
			handle: "@almasims",
			avatar: "9",
			mutual: "65",
		},
		{
			name: "Jesus Scott",
			handle: "@jesusscott",
			avatar: "10",
			mutual: "34",
		},
		{
			name: "Cynthia Cain",
			handle: "@cynthiacain",
			avatar: "11",
			mutual: "76",
		},
		{
			name: "Melvin Townsend",
			handle: "@melvintownsend",
			avatar: "12",
			mutual: "29",
		},
		{
			name: "Lena Jensen",
			handle: "@lenajensen",
			avatar: "13",
			mutual: "45",
		},
		{
			name: "Lloyd Fisher",
			handle: "@lloydfisher",
			avatar: "14",
			mutual: "12",
		},
		{
			name: "Mark Spencer",
			handle: "@spencer",
			avatar: "15",
			mutual: "07",
		},
		{
			name: "Ben Afflect",
			handle: "@afflect",
			avatar: "16",
			mutual: "23",
		},
	];

	if (WRAPPER) {
		DATA.forEach((item) => {
			list += `<div class="card p-3 g-col-12 g-col-sm-6 g-col-lg-4 g-col-xl-3">
						<div class="d-flex align-items-center mb-5">
							<a href="#" class="flex-shrink-0 mr-3">
								<img class="w-10 h-10 ms-1 rounded-circle me-3" src="${connectionImages[item.avatar]}" alt="">
							</a>
							<div class="flex-grow-1">
								<a href="" class="text-body-emphasis fw-medium">${item.name}</a>
								<div class="text-body-secondary text-link-seondary fs-7">${item.handle}</div>
							</div>
							<div class="dropdown align-self-start">
								<button class="icon ph ph-dots-three-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false"></button>
								<nav class="dropdown-menu dropdown-menu-end">
									<a class="dropdown-item" href="#">Block</a>
									<a class="dropdown-item" href="#">Message</a>
								</nav>
							</div>
						</div>
                        
						<div class="text-center">
							<button class="btn btn-secondary btn-sm mb-1 w-40">Connected</button>
							<div class="text-body-secondary fs-8">${item.mutual} Mutual</div>
						</div>
                    </div>`;
		});

		WRAPPER.innerHTML = list;
	}
};
