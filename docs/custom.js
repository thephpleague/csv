(() => {
  const uri = new URL(location.href);

  document.querySelector('.tool nav h2').addEventListener('click', function () {
    this.parentNode.querySelector('ul').classList.toggle('show-nav');
  }, false);

  document.querySelectorAll("main h2[id]").forEach((header) => {
    uri.hash = header.id;
    let link = document.createElement("a");
    link.className = "header-permalink";
    link.title = "Permalink";
    link.href = uri.toString();
    link.innerHTML = "&#182;";
    header.appendChild(link);
  });
})();