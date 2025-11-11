(() => {
  let contentHeaders= document.querySelectorAll("main h2[id]");
  if (!document.querySelector('html').classList.contains('homepage')  && contentHeaders) {
    const sections = document.querySelector('article.content').querySelectorAll('h2, h3, h4, h5, h6');
    const headings = [...sections];
    if (headings.length > 0) {
      // on page aside generation
      const aside = document.createElement('aside');
      aside.setAttribute('id', 'onthispage');
      const tocParent = document.createElement('nav');
      const tocParentTitle = document.createElement('h3');
      tocParentTitle.textContent = 'On This Page';
      const toc = document.createElement('ul');
      let currentLevel = 1;
      let currentList = toc;
      headings.forEach(h => {
        const level = parseInt(h.tagName.slice(1), 10);

        // Going deeper
        while (level > currentLevel) {
          const newList = document.createElement('ul');

          // If there is no lastElementChild, create a dummy parent <li>
          if (!currentList.lastElementChild) {
            const placeholder = document.createElement('li');
            currentList.appendChild(placeholder);
          }

          currentList.lastElementChild.appendChild(newList);
          currentList = newList;
          currentLevel++;
        }

        // Going shallower
        while (level < currentLevel) {
          currentList = currentList.parentElement.closest('ul');
          currentLevel--;
        }

        // Add list item for current heading
        const li = document.createElement('li');
        const a = document.createElement('a');

        if (!h.id) {
          h.id = h.textContent.trim().toLowerCase().replace(/\s+/g, '-');
        }

        a.href = `#${h.id}`;
        a.textContent = h.textContent;
        li.appendChild(a);
        currentList.appendChild(li);
      });

      tocParent.append(tocParentTitle, toc);
      aside.append(tocParent);
      document.querySelector('main').append(aside);
    }

    const uri = new URL(location.href);
    // adding a pointer for headers
    contentHeaders.forEach((header) => {
      uri.hash = header.id;
      let link = document.createElement("a");
      link.classList.add("header-permalink");
      link.title = "Permalink";
      link.href = uri.toString();
      link.innerHTML = "&#182;";
      header.appendChild(link);
    });

    const menuLinks = document.querySelectorAll('#onthispage a');
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        const id = entry.target.getAttribute("id");
        const link = document.querySelector(`#onthispage a[href="#${id}"]`);

        if (entry.isIntersecting) {
          menuLinks.forEach(a => a.classList.remove("active"));
          link.classList.add("active");
        }
      });
    }, {
      rootMargin: "-50% 0px -50% 0px", // trigger when the section is centered in viewport
      threshold: 0
    });

    sections.forEach(section => observer.observe(section));
  }

  // generate code snippet copy/paste
  let codeSnippet = document.querySelectorAll('.content .language-php.highlighter-rouge');
  codeSnippet.forEach((snippet) => {
    let notification = document.createElement("div");
    notification.classList.add('copy-snippet-notification', 'hidden', 'rounded', 'p-2');
    snippet.appendChild(notification);

    let link = document.createElement("span");
    link.classList.add("copy-snippet");
    link.innerHTML = "copy 📋";
    link.addEventListener('click', function (e) {
      let snippetParent = e.target.parentNode;
      let notification = snippetParent.querySelector('.copy-snippet-notification');
      let content = snippetParent.querySelector('pre').textContent;
      try {
        navigator.clipboard.writeText(content);
        notification.innerHTML = 'Copied!';
        notification.classList.add('bg-black');
        notification.classList.remove('hidden');
        setTimeout(() => {
          notification.classList.add('hidden');
          notification.classList.remove('bg-black');
        }, 500);
      } catch (err) {
        console.error('Failed to copy: ', err);
        notification.innerHTML = 'Copy failed!';
        notification.classList.add('bg-red-800');
        notification.classList.remove('hidden');
        setTimeout(() => {
          notification.classList.add('hidden');
          notification.classList.remove('bg-red-800');
        }, 500);
      }
    }, false);
    snippet.appendChild(link);
  });

  //package menu dropdown
  const dropDownList = document.getElementById('packageDropdownList');
  const dropDownButton = document.getElementById('packageDropdown');

  dropDownButton.addEventListener('click', () => {
    dropDownList.classList.toggle('hidden');
  });

  document.addEventListener('click',  (event) => {
    if (!dropDownButton.contains(event.target) && !dropDownList.contains(event.target)) {
      dropDownList.classList.add('hidden');
    }
  });
})();
