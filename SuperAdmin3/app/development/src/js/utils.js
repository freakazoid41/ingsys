//---------------------------------------------------
// Colors
//---------------------------------------------------
export const COLORS = {
	white: "255, 255, 255",
	black: "0, 0, 0",
	transparent: "rgba(0,0,0,0)",
	blue: "8, 103, 194",
	indigo: "66, 77, 138",
	purple: "146, 67, 158",
	pink: "135, 54, 81",
	red: "191, 50, 50",
	orange: "200, 121, 36",
	green: "51, 109, 87",
	teal: "34, 118, 110",
	cyan: "27, 154, 170",

	chart: {
		main: "254, 119, 76",
		sub: "43, 14, 18"
	}
};

//---------------------------------------------------
// Find out the platform is MAC or not
//---------------------------------------------------
export const isMac = () => {
	const platform = navigator?.userAgentData?.platform || navigator?.platform;
	return platform.toUpperCase().indexOf("MAC") >= 0;
};

//---------------------------------------------------
// Return CSS variable
// This is helpful for dark/light theme modes
//---------------------------------------------------
export const cssVar = (variable) => {
	return getComputedStyle(document.body).getPropertyValue(variable).trim();
};

//---------------------------------------------------
// Even row background for lists
//---------------------------------------------------
export const evenRow = (index, highlightClass = "bg-highlight") => {
	let rowClass = "";
	if (index % 2 === 0) {
		rowClass = highlightClass;
	} else {
		rowClass = "";
	}

	return rowClass;
};

//---------------------------------------------------
// Throttle function
//---------------------------------------------------
export const throttle = (fn, delay) => {
	let lastCall = 0;
	return function (...args) {
		const now = new Date().getTime();
		if (now - lastCall >= delay) {
			lastCall = now;
			fn(...args);
		}
	};
};

//---------------------------------------------------
// Avatar
//---------------------------------------------------
const AVATAR_BASE_CLS = `w-9 h-9 rounded-circle d-grid place-content-center me-3 text-white`;

export const avatarCap = (cap = "A", bg = "bg-active") => {
	return `<div class="text-uppercase">
				<div class="fw-bold ${AVATAR_BASE_CLS} ${bg}">${cap}</div>
			</div>`;
};

export const avatarIcon = (icon = "ph-user", bg = "bg-active") => {
	return `<div class="${AVATAR_BASE_CLS}">
				<i class="ph fs-3 bg-opacity-50 ${icon} ${AVATAR_BASE_CLS} ${bg}"></i>
			</div>`;
};

export const avatarImg = (img = "", alt = "", width = "h-8", height = "h-8") => {
	return `<div class="${AVATAR_BASE_CLS}">
				<img class="rounded-circle ${width} ${height}" src="${img}" alt="${alt}" />
			</div>`;
};
