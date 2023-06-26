//selecting all required elements
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
let file; //this is a global variable and we'll use it inside multiple functions
let validExtensions = ["image/jpeg", "image/jpg", "image/png", "video/mkv", "video/x-m4v", "video/mp4", "image/gif", "video/quicktime", "video/x-msvideo", "video/x-ms-wmv", "image/webp", "audio/mp3", "audio/*", "audio/wav", "audio/mpeg"]; //adding some valid image extensions in array
let fileType; //getting selected file type
button.onclick = () => {
	input.click(); //if user click on the button then the input also clicked
};

input.addEventListener("change", function () {
	//getting user select file and [0] this means if user select multiple files then we'll select only the first one
	file = this.files[0];
	fileType = file.type;
	if (validExtensions.includes(fileType)) {
		document.querySelector(".import_button").disabled = false;
		document.querySelector(".import_button").classList.add("upload_button_active");
		document.querySelector(".upload_button").classList.add("upload_button_disabled");
		document.querySelector(".supported_file").classList.add("hidden_element");
		document.querySelector(".import").classList.add("hidden_element");
		showFile(); //calling function
	} else {
		alert("This is not an Image, Audio or Video File!");
	}
});
// If user Drag File Over DropArea
dropArea.addEventListener("dragover", (event) => {
	event.preventDefault(); //preventing from default behaviour
	dropArea.classList.add("active");
});

// If user leave dragged File from DropArea
dropArea.addEventListener("dragleave", () => {
	dropArea.classList.remove("active");
});

dropArea.addEventListener("drop", (e) => {
	e.preventDefault();
	file = e.dataTransfer.files[0];
	fileType = file.type;

	if (!validExtensions.includes(fileType)) {
		alert("This is not an Image, Audio or Video File!");
	} else if (e.dataTransfer.files.length > 1) {
		alert("Please select only one file");
	} else {
		document.querySelector(".import_button").disabled = false;
		document.querySelector(".supported_file").classList.add("hidden_element");
		document.querySelector(".import").classList.add("hidden_element");
		document.querySelector(".import_button").classList.add("upload_button_active");
		document.querySelector(".upload_button").classList.add("upload_button_disabled");

		const fileInputs = document.getElementById("input_file");
		fileInputs.files = e.dataTransfer.files;

		showFile(); //calling function
	}
});

function loadVideo(file) {
	const reader = new FileReader();

	reader.onload = () => {
		const videoPlayer = document.getElementById("video-player");
		videoPlayer.src = reader.result;
		videoPlayer.play();
	};

	reader.readAsDataURL(file);
}

document.querySelector(".input_url").addEventListener("input", (e) => {
	if (e.target.value !== "") {
		document.querySelector(".import_button").disabled = false;
		document.querySelector(".import_button").classList.add("upload_button_active");
		document.querySelector(".upload_button").classList.add("upload_button_disabled");
		document.querySelector(".upload_button").disabled = true;
	} else {
		document.querySelector(".import_button").disabled = true;
		document.querySelector(".import_button").classList.remove("upload_button_active");
		document.querySelector(".upload_button").classList.remove("upload_button_disabled");
		document.querySelector(".upload_button").disabled = false;
	}
});

function showFile() {
	const videoPlayer = document.getElementById("video-player");

	//if user selected file is an image file
	let fileReader = new FileReader(); //creating new FileReader object
	dropArea.querySelector(".preview").classList.remove("hidden_element");
	if (fileType.includes("image")) {
		fileReader.onload = () => {
			let fileURL = fileReader.result; //passing user file source in fileURL variable
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
			let fileURL = fileReader.result; //passing user file source in fileURL variable
			dropArea.querySelector(".uploaded_audio").src = fileURL;
			videoPlayer.src = "";
			dropArea.querySelector(".uploaded_img").src = "";
			dropArea.querySelector(".uploaded_video").classList.add("hidden_element");
			dropArea.querySelector(".uploaded_img").classList.add("hidden_element");
		};
	}

	fileReader.addEventListener("loadstart", function () {
		loadingBar.style.width = "0%";
		loadingInfo.innerHTML = "0";
		loadingArea.classList.remove("hidden_element");
	});
	fileReader.addEventListener("progress", function (e) {
		if (e.lengthComputable) {
			const percentLoaded = (e.loaded / e.total) * 100;
			loadingBar.style.width = `${percentLoaded}%`;
			loadingInfo.innerHTML = Math.floor(percentLoaded);
		}
	});

	fileReader.addEventListener("loadend", function () {
		loadingArea.classList.add("hidden_element");
		dragHeader.classList.remove("hidden_element");

		if (fileType.includes("video")) {
			dropArea.querySelector(".uploaded_video").classList.remove("hidden_element");
			// document.querySelector(".sharing_info span").textContent = "video";
			// document.querySelector(".image_address").textContent = "Copy Video Address";
			// document.querySelector(".toast_info span").textContent = "video";
		}
	if (fileType.includes("audio")) {
			dropArea.querySelector(".uploaded_audio").classList.remove("hidden_element");
			
		}
	});

	fileReader.readAsDataURL(file);
	dragHeader.classList.add("hidden_element");
	terms.classList.add("hidden_element");
	// dragImport.classList.add("hidden_element");
	// metadata.classList.remove("hidden_element");
}

function showToast() {
	toast.classList.remove("hidden_element");
	setTimeout(() => {
		toast.classList.add("hidden_element");
	}, 1500);
}

// let copyAddress = document.querySelector(".image_address");

// copyAddress.addEventListener("click", () => {
// 	showToast();
// });

const inputVideo = document.getElementById("video-input");

function showSpinner() {
	var spinnerContainer = document.getElementById("spinner-container");
	spinnerContainer.style.display = "flex";
	// You can add code here to handle the file upload
	document.querySelector(".form").style.display = "none";
	document.querySelector(".image_svg").style.display = "none";
}

document.querySelector(".import_button").addEventListener("click", showSpinner);

