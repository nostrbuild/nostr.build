const editButton = document.querySelector(".edit_folder_button");
const doneButton = document.querySelector(".done_folder_button");
const sidebar = document.querySelector(".sidebar");
const newFolder = document.querySelector(".new_folder");
const folderIcons = document.querySelector(".folder_icons");
var folderNumber = document.querySelectorAll(".folder span");

const title = document.querySelector(".header_title");
function check() {
	// document.querySelectorAll(".folder").map((folder) => {
	// 	let content = folder.textContent;
	// 	if (content.includes(title.textContent)) {
	// 		folder.classList.add("selected_folder");
	// 		console.log("asd");
	// 	}
	// });
	let folders = document.querySelectorAll(".folder");
	let navItems = document.querySelectorAll(".nav_item");
	folders.forEach((folder) => {
		if (folder.textContent.includes(title.textContent)) {
			folder.classList.add("selected_folder");
		}
	});
	navItems.forEach((nav) => {
		if (nav.textContent.includes(title.textContent)) {
			nav.classList.add("nav_item_active");
		}
	});
}

check();

// editButton.addEventListener("click", function () {
// 	doneButton.style.display = "flex";
// 	editButton.style.display = "none";
// 	sidebar.classList.add("sidebar_edit");
// 	newFolder.style.display = "none";
// 	// folderIcons.style.display = "flex";
// 	document.querySelectorAll(".folder_icons").forEach(function (el) {
// 		el.style.display = "flex";
// 	});
// 	document.querySelectorAll(".folder span").forEach(function (el) {
// 		el.style.display = "none";
// 	});
// 	document.querySelectorAll(".folder").forEach(function (el) {
// 		el.classList.add("edit_mode");
// 	});

// 	enableDragSort("drag-sort-enable");
// 	document.querySelectorAll(".folder").forEach(function (els) {
// 		els.classList.remove("selected_folder");
// 	});
// });

// doneButton.addEventListener("click", function () {
// 	editButton.style.display = "flex";
// 	doneButton.style.display = "none";
// 	sidebar.classList.remove("sidebar_edit");
// 	newFolder.style.display = "flex";
// 	document.querySelectorAll(".folder_icons").forEach(function (el) {
// 		el.style.display = "none";
// 	});
// 	document.querySelectorAll(".folder span").forEach(function (el) {
// 		el.style.display = "flex";
// 	});
// 	document.querySelectorAll(".folder").forEach(function (el) {
// 		el.classList.remove("edit_mode");
// 	});

// 	disableDragSort("drag-sort-enable");
// 	check();
// });

document.querySelectorAll(".folder").forEach(function (el) {
	el.addEventListener("click", function () {
		let folderTitle = el.querySelector("p").textContent;
		document.querySelectorAll(".folder").forEach(function (els) {
			els.classList.remove("selected_folder");
		});
		document.querySelectorAll(".nav_item").forEach(function (nav) {
			nav.classList.remove("nav_item_active");
		});
		el.classList.add("selected_folder");
		title.textContent = folderTitle;
	});
});

document.querySelectorAll(".nav_item").forEach(function (nav) {
	nav.addEventListener("click", function () {
		let itemTitle = nav.textContent;
		document.querySelectorAll(".nav_item").forEach(function (nav) {
			nav.classList.remove("nav_item_active");
		});
		document.querySelectorAll(".folder").forEach(function (els) {
			els.classList.remove("selected_folder");
		});
		nav.classList.add("nav_item_active");
		title.textContent = itemTitle;
	});
});

function enableDragSort(listClass) {
	const sortableLists = document.getElementsByClassName(listClass);
	Array.prototype.map.call(sortableLists, (list) => {
		enableDragList(list);
	});
}

function enableDragList(list) {
	Array.prototype.map.call(list.children, (item) => {
		enableDragItem(item);
	});
}

function enableDragItem(item) {
	item.setAttribute("draggable", true);
	item.ondrag = handleDrag;
	item.ondragend = handleDrop;
	const deleteButton = item.querySelector(".delete_folder");
	if (deleteButton) {
		deleteButton.addEventListener("click", handleDelete);
	}
}

function handleDrag(item) {
	const selectedItem = item.target,
		list = selectedItem.parentNode,
		x = event.clientX,
		y = event.clientY;

	selectedItem.classList.add("drag-sort-active");
	let swapItem = document.elementFromPoint(x, y) === null ? selectedItem : document.elementFromPoint(x, y);

	if (list === swapItem.parentNode) {
		swapItem = swapItem !== selectedItem.nextSibling ? swapItem : swapItem.nextSibling;
		list.insertBefore(selectedItem, swapItem);
	}
}

function handleDrop(item) {
	item.target.classList.remove("drag-sort-active");
}

// Remove the enableDragSort functionality
function disableDragSort(listClass) {
	const sortableLists = document.getElementsByClassName(listClass);
	Array.prototype.map.call(sortableLists, (list) => {
		disableDragList(list);
	});
}

function disableDragList(list) {
	Array.prototype.map.call(list.children, (item) => {
		disableDragItem(item);
	});
}

function disableDragItem(item) {
	item.removeAttribute("draggable");
	item.ondrag = null;
	item.ondragend = null;
}

function handleDelete(event) {
	const item = event.target.closest("li");
	if (item) {
		item.parentNode.removeChild(item);
	}
}

const menuIcon = document.querySelector(".menu_button");

// Show/hide sidebar when clicking on menu icon
menuIcon.addEventListener("click", () => {
	sidebar.style.transform = "translateX(0)";
	document.querySelector("body").style.overflow = "hidden";
});

document.addEventListener("click", (event) => {
	const isClickInsideSidebar = sidebar.contains(event.target);
	const isClickOnShowButton = menuIcon.contains(event.target);
	const isDesktop = window.matchMedia("(min-width: 769px)").matches;

	if (!isClickInsideSidebar && !isClickOnShowButton && !isDesktop) {
		sidebar.style.transform = "translateX(-100%)";
		document.querySelector("body").style.overflow = "unset";
	}
});

/*
const checkboxes = document.querySelectorAll('.toggle-switch input[type="checkbox"]');

checkboxes.forEach((checkbox) => {
	checkbox.addEventListener("change", function () {
		const container = this.closest(".image_card");
		if (this.checked) {
			container.classList.add("image_card_checked");
		} else {
			container.classList.remove("image_card_checked");
		}
	});
});
*/
const copyButtons = document.querySelectorAll(".copy_link");

let toast = document.querySelector(".toast");

function showToast() {
	toast.classList.remove("hidden_element");
	setTimeout(() => {
		toast.classList.add("hidden_element");
	}, 1500);
}

let copyAddress = document.querySelectorAll(".copy_link");

copyButtons.forEach((button) => {
	button.addEventListener("click", function () {
		showToast();
		console.log("Hola");
	});
});

// pie chart

const circularProgress = document.querySelectorAll(".circular-progress");

Array.from(circularProgress).forEach((progressBar) => {
	const progressValue = progressBar.querySelector(".percentage");
	const innerCircle = progressBar.querySelector(".inner-circle");
	let startValue = 0,
		endValue = Number(progressBar.getAttribute("data-percentage")),
		speed = 10,
		progressColor = progressBar.getAttribute("data-progress-color");

	const progress = setInterval(() => {
		startValue++;
		progressValue.textContent = `${startValue}%`;
		progressValue.style.color = `${progressColor}`;

		innerCircle.style.backgroundColor = `${progressBar.getAttribute("data-inner-circle-color")}`;

		progressBar.style.background = `conic-gradient(${progressColor} ${startValue * 3.6}deg,${progressBar.getAttribute("data-bg-color")} 0deg)`;
		if (startValue === endValue) {
			clearInterval(progress);
		}
	}, speed);
});
