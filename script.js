window.addEventListener("load", () => {
  const loader = document.getElementById("loader");

  // Fade out loader
  loader.style.opacity = "0";
  setTimeout(() => {
    loader.style.display = "none";

    // Lazy load main content by injecting it after loader fades
    const contentContainer = document.getElementById("content");
    if (!contentContainer.hasChildNodes()) {
      const mainContent = document.createElement("div");
      mainContent.innerHTML = `
        <h1>Welcome to My Web App</h1>
        <p>This is the main content of your app.</p>
      `;
      contentContainer.appendChild(mainContent);
    }
    contentContainer.style.display = "block";
  }, 500); // match the CSS transition time
});
