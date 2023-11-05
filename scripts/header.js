const menu_button = document.querySelector(".menu_button");
if (menu_button) {
  menu_button.addEventListener("click", () => {
    if (document.querySelector(".menu").style.display === "flex") {
      document.querySelector(".menu").style.display = "none";
    } else {
      document.querySelector(".menu").style.display = "flex";
    }
  });
}

const main_menu = document.querySelector(".main");
if (main_menu) {
  main_menu.addEventListener("click", () => {
    document.querySelector(".menu").style.display = "none";
  });
}
