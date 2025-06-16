import { activeUsers } from "./activeUsers";
import { averageVisitDuration } from "./averageVisitDurarion";
import { browserBounceRate } from "./browserBounceRate";
import { deviceSessions } from "./deviceSessions";
import { sessionsByCountry } from "./sessionsByCountry";
import { userAcquisition } from "./userAcquisition";
import { visitsBySource } from "./visitsBySource";

export const analytics = () => {
	sessionsByCountry();
	activeUsers();
	visitsBySource();
	averageVisitDuration();
	userAcquisition();
	browserBounceRate();
	deviceSessions();
};
