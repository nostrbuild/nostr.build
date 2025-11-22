document.addEventListener('DOMContentLoaded', function () {
	// Defining a helper function for repeated tasks
	function queryAllAndAct(query, action) {
		const elements = document.querySelectorAll(query);
		elements.forEach(action);
	}

	// Defining another helper function to simplify the code
	function addClass(element, className) {
		if (!element.classList.contains(className)) {
			element.classList.add(className);
		}
	}

	function removeClass(element, className) {
		if (element.classList.contains(className)) {
			element.classList.remove(className);
		}
	}

	function hideElement(element) {
		element.classList.add("hidden_element");
	}

	function showElement(element) {
		element.classList.remove("hidden_element");
	}

	// Initial setup
	const dropArea = document.querySelector(".drag-area"),
		dragText = dropArea.querySelector(".drag-area_title"),
		dragHeader = dropArea.querySelector(".drag-area_header"),
		dragSharing = dropArea.querySelector(".drag-area_sharing"),
		dragImport = dropArea.querySelector(".import"),
		metadata = document.querySelector(".metadata_container"),
		loadingBar = document.querySelector(".loading_state"),
		loadingInfo = document.querySelector(".loading_info span"),
		loadingArea = document.querySelector(".drag-area_loading"),
		toast = document.querySelector(".toast"),
		button = dropArea.querySelector(".upload_button"),
		terms = document.querySelector(".terms"),
		input = dropArea.querySelector(".hidden_input");

	let file; // Global variable for file
	let validExtensions = ["image/jpeg", "image/jpg", "image/png", "video/mkv", "video/x-m4v", "video/mp4", "image/gif", "video/quicktime", "video/x-msvideo", "video/x-ms-wmv", "image/webp", "audio/mp3", "audio/*", "audio/wav", "audio/mpeg"];
	let fileType;

	button.onclick = () => {
		input.click();
	};

	input.addEventListener("change", function () {
		file = this.files[0];
		fileType = file.type;
		if (validExtensions.includes(fileType)) {
			queryAllAndAct(".import_button", button => {
				button.disabled = false;
				addClass(button, "upload_button_active");
			});
			addClass(document.querySelector(".upload_button"), "upload_button_disabled");
			addClass(document.querySelector(".supported_file"), "hidden_element");
			addClass(document.querySelector(".import"), "hidden_element");
			showFile();
		} else {
			alert("This is not an Image, Audio or Video File!");
		}
	});

	dropArea.addEventListener("dragover", (event) => {
		event.preventDefault();
		dropArea.classList.add("active");
	});

	dropArea.addEventListener("dragleave", () => {
		removeClass(dropArea, "active");
	});

	dropArea.addEventListener("drop", (e) => {
		e.preventDefault();
		file = e.dataTransfer.files[0];
		fileType = file.type;
		fileSize = file.size;
		if (!validExtensions.includes(fileType)) {
			alert("This is not an Image, Audio or Video File!");
		} else if (e.dataTransfer.files.length > 1) {
			alert("Please select only one file");
		} else if (fileSize > 20_971_520) {
			alert("File is too large, please select a file less than 20MiB");
		} else {
			queryAllAndAct(".import_button", button => {
				button.disabled = false;
				addClass(button, "upload_button_active");
			});
			addClass(document.querySelector(".supported_file"), "hidden_element");
			addClass(document.querySelector(".import"), "hidden_element");
			addClass(document.querySelector(".upload_button"), "upload_button_disabled");

			const fileInputs = document.getElementById("input_file");
			fileInputs.files = e.dataTransfer.files;

			showFile();
		}
	});

	document.querySelector(".input_url").addEventListener("input", (e) => {
		if (e.target.value !== "") {
			queryAllAndAct(".import_button", button => {
				button.disabled = false;
				addClass(button, "upload_button_active");
			});
			addClass(document.querySelector(".upload_button"), "upload_button_disabled");
			document.querySelector(".upload_button").disabled = true;
		} else {
			queryAllAndAct(".import_button", button => {
				button.disabled = true;
				removeClass(button, "upload_button_active");
			});
			removeClass(document.querySelector(".upload_button"), "upload_button_disabled");
			document.querySelector(".upload_button").disabled = false;
		}
	});

	function showFile() {
		const videoPlayer = document.getElementById("video-player");

		// Create new FileReader object
		let fileReader = new FileReader();

		// Remove hidden element from preview
		dropArea.querySelector(".preview").classList.remove("hidden_element");

		// Based on the file type, handle the loading
		if (fileType.includes("image")) {
			fileReader.onload = () => {
				let fileURL = fileReader.result;
				dropArea.querySelector(".uploaded_img").src = fileURL;
				videoPlayer.src = "";
				dropArea.querySelector(".uploaded_audio").src = "";
				dropArea.querySelector(".uploaded_video").classList.add("hidden_element");
				dropArea.querySelector(".uploaded_audio").classList.add("hidden_element");
			};
		} else if (fileType.includes("video")) {
			fileReader.onload = () => {
				videoPlayer.src = fileReader.result;
				dropArea.querySelector(".uploaded_img").src = "";
				dropArea.querySelector(".uploaded_audio").src = "";
				dropArea.querySelector(".uploaded_audio").classList.add("hidden_element");
				dropArea.querySelector(".uploaded_img").classList.add("hidden_element");
				videoPlayer.play();
			};
		} else if (fileType.includes("audio")) {
			fileReader.onload = () => {
				let fileURL = fileReader.result;
				dropArea.querySelector(".uploaded_audio").src = fileURL;
				videoPlayer.src = "";
				dropArea.querySelector(".uploaded_img").src = "";
				dropArea.querySelector(".uploaded_video").classList.add("hidden_element");
				dropArea.querySelector(".uploaded_img").classList.add("hidden_element");
			};
		}

		// Add event listeners for the FileReader
		fileReader.addEventListener("loadstart", function () {
			loadingBar.style.width = "0%";
			loadingInfo.innerHTML = "0";
			loadingArea.classList.remove("hidden_element");
		});

		fileReader.addEventListener("progress", function (e) {
			if (e.lengthComputable) {
				const percentLoaded = Math.round((e.loaded / e.total) * 100);
				loadingBar.style.width = `${percentLoaded}%`;
				loadingInfo.innerHTML = percentLoaded;
			}
		});

		fileReader.addEventListener("loadend", function () {
			loadingArea.classList.add("hidden_element");
			dragHeader.classList.remove("hidden_element");

			if (fileType.includes("video")) {
				dropArea.querySelector(".uploaded_video").classList.remove("hidden_element");
			}
			if (fileType.includes("audio")) {
				dropArea.querySelector(".uploaded_audio").classList.remove("hidden_element");
			}
		});

		// Read the file as Data URL
		fileReader.readAsDataURL(file);
		dragHeader.classList.add("hidden_element");
		terms.classList.add("hidden_element");
	}

	function showToast() {
		showElement(toast);
		setTimeout(() => {
			hideElement(toast);
		}, 1500);
	}

	function showSpinner() {
		var spinnerContainer = document.getElementById("spinner-container");
		spinnerContainer.style.display = "flex";
		// You can add code here to handle the file upload
		document.querySelector(".form").style.display = "none";
		document.querySelector(".image_svg").style.display = "none";
	}

	queryAllAndAct(".media_upload_btn", button => {
		button.addEventListener("click", showSpinner);
	});

	queryAllAndAct(".pfp_upload_btn", (button) => {
		button.addEventListener("click", (event) => {
			const confirmUpload = confirm("This will crop and resize media for profile picture use, proceed?");
			if (!confirmUpload) {
				event.preventDefault(); // Cancel the default action
			} else {
				showSpinner(); // Show the spinner if confirmed
			}
		});
	});

});
