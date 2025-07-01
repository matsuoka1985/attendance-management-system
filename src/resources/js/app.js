import './bootstrap';
import Alpine from "alpinejs"; // Core
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();
