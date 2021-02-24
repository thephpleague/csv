(() => {

  let headers = document.querySelector('header nav h2');
  if (headers) {
    headers.addEventListener('click', function () {
      this.parentNode.querySelector('ul').classList.toggle('show');
    }, false);
  }

  let contentHeaders = document.querySelectorAll("main h2[id]");
  if (!document.querySelector('html').classList.contains('homepage') && contentHeaders) {
    const uri = new URL(location.href);
    contentHeaders.forEach((header) => {
      uri.hash = header.id;
      let link = document.createElement("a");
      link.classList.add("header-permalink");
      link.title = "Permalink";
      link.href = uri.toString();
      link.innerHTML = "&#182;";
      header.appendChild(link);
    });
  }

  const sponsorBanner = document.querySelector('.sponsors');
  if (sponsorBanner) {
    let hideUntil = localStorage.getItem('hideSponsorUntil');
    if (hideUntil === null || hideUntil < (new Date().getTime())) {
      localStorage.removeItem('hideSponsorUntil');
      sponsorBanner.classList.remove('hide');
    }

    let closeButton = document.createElement('a');
    closeButton.classList.add('close');
    closeButton.innerHTML = 'close me';
    closeButton.addEventListener('click',  () => {
      localStorage.setItem('hideSponsorUntil', new Date().getTime() + (7 * 86400 * 1e4));
      sponsorBanner.classList.add('hide');
    }, false);

    sponsorBanner.firstElementChild.appendChild(closeButton);
  }
})();
