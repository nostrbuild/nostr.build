let array = ["image 1", "image 2", "image 3", "image 4", "image 5", "image 6", "image 7"];
let container = document.querySelector(".images_container");

array.forEach((element) => {});

for (let index = 0; index < Math.floor(Math.random() * 1000); index++) {
	var img = document.createElement("img");
	var number = Math.floor(Math.random() * 7) + 1;
	img.src = `../../assets/images/image ${number}.png`;
	img.classList.add("image");

	container.appendChild(img);
}
