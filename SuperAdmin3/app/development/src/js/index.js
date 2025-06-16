import "../scss/style.scss";

import { sidebar } from "./layout/sidebar";
import { notifications } from "./layout/header/notifications";
import { tasks } from "./layout/header/tasks";
import { shortcuts } from "./layout/header/shortcuts";
import { search } from "./layout/header/search/search";
import { sales } from "./views/sales/sales";
import { crm } from "./views/crm/crm";
import { analytics } from "./views/analytics/analytics";
import { userConnections } from "./views/user/connections";
import { userPhotos } from "./views/user/photos";
import { contacts } from "./views/contacts/contacts";
import { contentSidebar } from "./layout/contentSidebar";
import { fileManager } from "./views/file-manager/fileManager";
import { mail } from "./views/mail/mail";
import { messages } from "./views/messages/messages";
import { photos } from "./views/photos/photos";
import { todoList } from "./views/todo-lists/todoLists";
import { calendarPage } from "./views/calendar/calendar";
import { searchResults } from "./views/search-results/searchResults";
import { faq } from "./views/faq/faq";
import { teams } from "./views/team/team";
import { demo } from "./demo";
import { icons } from "./views/icons/icons";
import { charts } from "./views/charts/charts";
import { emailMarketing } from "./views/email-marketing/emailMarketing";
import { projectManagement } from "./views/project-management/projectManagement";
import { maps } from "./views/maps/maps";
import { pageLoader } from "./layout/pageLoader";
import { theme } from "./layout/header/theme";

// Views
sales();
crm();
analytics();
emailMarketing();
projectManagement();
userConnections();
userPhotos();
contacts();
fileManager();
mail();
messages();
photos();
todoList();
calendarPage();
searchResults();
faq();
teams();
icons();
charts();
maps();

// Layout
sidebar();
search();
notifications();
tasks();
shortcuts();
theme();
contentSidebar();
pageLoader();

// Demo
demo();
