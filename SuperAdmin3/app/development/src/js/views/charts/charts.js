import { area, areaChartUpdate } from "./area";
import { bar, barChartUpdate } from "./bar";
import { curvedLine, curvedLineChartUpdate } from "./curvedLine";
import { horizontalBar, horizontalBarUpdate } from "./horizontalBar";
import { line, lineChartUpdate } from "./line";
import { pie, pieChartUpdate } from "./pie";
import { doughnut, doughnutChartUpdate } from "./doughnut";
import { scatter, scatterChartUpdate } from "./scatter";
import { stackedBar, stackedBarUpdate } from "./stackedBar";
import { stackedHorizontalBar, stackedHorizontalBarUpdate } from "./stackedHorizontalBar";

export const charts = () => {
	// Bar chart
	bar();

	// Horizontal bar chart
	horizontalBar();

	// Stacked bar chart
	stackedBar();

	// Stacked horizontal bar chart
	stackedHorizontalBar();

	// Line chart
	line();

	// Curved line chart
	curvedLine();

	// Area chart
	area();

	// Scatter chart
	scatter();

	// Pie chart
	pie();

	// Daughnut chart
	doughnut();
};

// Update charts when dark/light mode is switched
export const chartsUpdate = () => {
	// Pie chart
	pieChartUpdate();

	// Doughnut chart
	doughnutChartUpdate();

	// Scatter chart
	scatterChartUpdate();

	// Area chart
	areaChartUpdate();

	// Bar chart
	barChartUpdate();

	// Curved line chart
	curvedLineChartUpdate();

	// Horizontal bar chart
	horizontalBarUpdate();

	// Line chart
	lineChartUpdate();

	// Stacked bar chart
	stackedBarUpdate();

	// Stacked horizontal bar chart
	stackedHorizontalBarUpdate();
};
