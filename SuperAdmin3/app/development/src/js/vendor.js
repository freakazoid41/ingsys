import { vanillajsDatepicker } from "./vendors/vanillaJsDatepicker";
import { quillEditor } from "./vendors/quill";
import { bootstrapComponents } from "./vendors/bootstrap";
import { slider } from "./vendors/noUiSlider";
import { colorPicker } from "./vendors/vanillaColorful";

// Simplebar (scrollbars)
import "simplebar";
import "simplebar/dist/simplebar.min.css";

// Phosphor icons
import "@phosphor-icons/web/src/light/style.css";
import "@phosphor-icons/web/src/regular/style.css";

// Bootstrap + compomnents
import * as bootstrap from "bootstrap";
bootstrapComponents();

// Vanilla JS Datepicker
vanillajsDatepicker();

// Quill
quillEditor();

// noUiSlider
slider();

// Vanilla Colorful
colorPicker();
