(function () {
	var form = document.getElementById("storelink-settings-form");
	if (!form) {
		return;
	}

	var hidden = form.querySelector('input[name="storelink_tab"]');
	var tabs = form.querySelectorAll(".nav-tab");
	var panels = form.querySelectorAll(".storelink-tab-panel");

	function show(id) {
		tabs.forEach(function (tab) {
			tab.classList.toggle("nav-tab-active", tab.getAttribute("data-tab") === id);
		});
		panels.forEach(function (panel) {
			panel.classList.toggle("is-active", panel.getAttribute("data-tab") === id);
		});
		if (hidden) {
			hidden.value = id;
		}
	}

	tabs.forEach(function (tab) {
		tab.addEventListener("click", function (event) {
			event.preventDefault();
			show(tab.getAttribute("data-tab"));
		});
	});
})();
