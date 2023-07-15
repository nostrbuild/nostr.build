document.addEventListener('DOMContentLoaded', (event) => {
	let closeButton = document.querySelector(".close");
	if (closeButton) {
		closeButton.addEventListener("click", () => {
			let warning = document.querySelector(".warning");
			if (warning) {
				warning.classList.add("hidden_element");
			}
		});
	}
});