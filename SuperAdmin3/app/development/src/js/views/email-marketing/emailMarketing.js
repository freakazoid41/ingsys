import { campaignPerformance } from "./campaignPerformance";
import { campaignSales } from "./campaignSales";
import { deliveredVsOpen } from "./deliveredVsOpen";
import { geographicalEngagementas } from "./geographicalEngagementas";
import { openRateByDevice } from "./openRateByDevice";
import { sentVsNotSent } from "./sentVsNotSent";

export const emailMarketing = () => {
	deliveredVsOpen();
	openRateByDevice();
	campaignPerformance();
	campaignSales();
	geographicalEngagementas();
	sentVsNotSent();
};
