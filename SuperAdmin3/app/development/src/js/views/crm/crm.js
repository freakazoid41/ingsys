import { convertedLeads } from "./convertedLeads";
import { leadsByIndustry } from "./leadsByIndustry";
import { leadsDistribution } from "./leadsDistribution";
import { leadsGenerationRate } from "./leadsGenerationRate";
import { leadsSources } from "./leadsSources";
import { opportunities } from "./opportunities";
import { outboundCals } from "./outboundCalls";
import { resolutionByChannel } from "./resolutionByChannel";
import { topRainmakers } from "./topRainmakers";

export const crm = () => {
	leadsGenerationRate();
	convertedLeads();
	leadsDistribution();
	leadsByIndustry();
	leadsSources();
	opportunities();
	topRainmakers();
	resolutionByChannel();
	outboundCals();
};
