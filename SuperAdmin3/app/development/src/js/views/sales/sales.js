import { recentOrders } from "./recentOrders";
import { salesHistory } from "./salesHistory";
import { storeSessions } from "./storeSessions";
import { topProducts } from "./topProducts";

export const sales = () => {
	salesHistory();
	storeSessions();
	recentOrders();
	topProducts();
};
