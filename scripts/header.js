document.querySelector(".menu_button").addEventListener("click", () => {
  if (document.querySelector(".menu").style.display === "flex") {
    document.querySelector(".menu").style.display = "none";
  } else {
    document.querySelector(".menu").style.display = "flex";
  }
});
document.querySelector("main").addEventListener("click", () => {
  document.querySelector(".menu").style.display = "none";
});
