import { COLORS, cssVar } from "../utils";

//---------------------------------------------------
// Chart.js constants
//---------------------------------------------------
export const PROPS = {
	font: {
		family: "Inter",
		size: "10px",
	},
};

//---------------------------------------------------
// Chart.js Helpers
//---------------------------------------------------
// Gradient background
export const chartGradient = (ctx, chartArea, gradientHeight, colorStart, colorEnd) => {
	let width, height, gradient;
	const chartWidth = chartArea.right - chartArea.left;
	const chartHeight = chartArea.bottom - chartArea.top;
	if (gradient === null || width !== chartWidth || height !== chartHeight) {
		width = chartWidth;
		height = chartHeight;
		gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
		gradient.addColorStop(0, colorEnd);
		gradient.addColorStop(gradientHeight, colorStart);
	}

	return gradient;
};

// Common tooltip style
export const CHART_TOOLTIP = {
	titleFont: {
		family: PROPS.font.family,
		size: PROPS.font.size,
		weight: "normal",
	},
	titleColor: "#ffffff",
	bodyColor: "rgba(255,255,255,0.65)",
	bodyFont: {
		family: PROPS.font.family,
		size: PROPS.font.size,
	},
	titleMarginBottom: 3,
	backgroundColor: "rgba(0,0,0,0.75)",
	padding: 10,
	cornerRadius: 6,
	multiKeyBackground: COLORS.transparent,
	displayColors: false,
	caretSize: 0,
};

// Common tick style
export const chartTicks = (padding = 8, align = "inner") => ({
	font: {
		family: PROPS.font.family,
		size: PROPS.font.size,
	},
	color: cssVar("--bs-secondary-color"),
	padding: padding,
	source: "auto",
	align: align,
	distribution: "linear",
	autoSkip: true,
	maxRotation: 0,
});

// Common legend style
export const CHART_LEGEND_LABEL = {
	pointStyle: "circle",
	boxWidth: 7,
	boxHeight: 7,
	padding: 20,
	color: cssVar("--bs-secondary-color"),
	font: {
		family: PROPS.font.family,
		size: PROPS.font.size,
	},
};

// Common grid style
export const CHART_GRID = {
	drawBorder: false,
	drawTicks: false,
	color: "rgba(255,255,255,0.075)",
};

// Reload Chart.js for dark/light mode themes.
// Not for Pie and Doughnut charts.
export const reloadChart = (chart, callback) => {
	callback;

	if (chart.config.type !== "doughnut" && chart.config.type !== "pie") {
		// Grid
		chart.config.options.scales.x.grid.color = cssVar("--bs-theme-200");
		chart.config.options.scales.y.grid.color = cssVar("--bs-theme-200");

		// Ticks
		chart.config.options.scales.x.ticks.color = cssVar("--bs-secondary-color");

		chart.config.options.scales.y.ticks.color = cssVar("--bs-secondary-color");
	}

	// Update chart
	chart.update();
};

// Generate time series data
export const generateTimeSeriesData = (baseval, count, yrange) => {
	let i = 0;
	const series = [];
	while (i < count) {
		const x = baseval;
		const y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
		series.push({ x, y });
		baseval += 86400000;
		i++;
	}
	return series;
};

// Generate data between two dates
export const getDatesBetweenDates = (startDate, endDate) => {
	let dates = [];
	const date = new Date(startDate);
	while (date < endDate) {
		dates = [...dates, new Date(date)];
		date.setDate(date.getDate() + 1);
	}
	return dates;
};
