import { budgetExpenses } from "./budgetExpenses";
import { budgetUtilization } from "./budgetUtilization";
import { health } from "./health";
import { overdueProjects } from "./overdueProjects";
import { plannedVsActual } from "./plannedVsActual";
import { projectsByStatus } from "./projectsByStatus";
import { ticketsReopened } from "./ticketsReopened";
import { upcomingTasks } from "./upcomingTasks";

export const projectManagement = () => {
	upcomingTasks();
	projectsByStatus();
	budgetUtilization();
	budgetExpenses();
	overdueProjects();
	health();
	ticketsReopened();
	plannedVsActual();
};
