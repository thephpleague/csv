(() => {
  const root = document.querySelector('article.content');
  if (!root) return;

  const isHeading = el => el && el.nodeType === 1 && /^H[1-6]$/.test(el.tagName);
  const headers = root.querySelectorAll('h2, h3, h4, h5, h6');
  const ids = new Set();
  headers.forEach(h => {
    let id = h.id || h.textContent.trim().toLowerCase().replace(/\W+/g, '-');
    let base = id;
    let i = 2;
    while (ids.has(id)) id = `${base}-${i++}`;
    ids.add(id);
    h.id = id;
  });

  for (const h of Array.from(headers)) {
    // Idempotence : si ce titre est déjà le 1er enfant d'un .section-wrapper, on ne fait rien
    if (h.parentElement?.classList.contains('section-wrapper') &&
      h.parentElement.firstElementChild === h) {
      continue;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'section-wrapper';
    // (optionnel) pour debug :
    wrapper.dataset.heading = h.tagName.toLowerCase();
    if (h.id) wrapper.dataset.anchor = h.id;

    // Insérer le wrapper juste avant le titre
    h.parentNode.insertBefore(wrapper, h);

    // Déplacer le titre dans le wrapper
    wrapper.appendChild(h);

    // Puis déplacer tout ce qui suit IMMÉDIATEMENT jusqu'au prochain heading (quel que soit le niveau)
    // -> ainsi on n'englobe jamais les sous-titres
    let node = wrapper.nextSibling; // ancien "nextSibling" du h2, devenu celui du wrapper
    while (node) {
      const next = node.nextSibling; // mémoriser avant déplacement/arrêt

      // Si on tombe sur un titre (h1..h6), on s'arrête (le prochain wrapper le prendra en charge)
      if (node.nodeType === 1 && isHeading(node)) break;

      // Sinon, c'est du contenu d'intro : on le rapatrie dans ce wrapper
      wrapper.appendChild(node);

      node = next;
    }
  }
})();

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
        const id = entry.target.getAttribute("data-anchor");
        const link = document.querySelector(`#onthispage a[href="#${id}"]`);

        if (entry.isIntersecting) {
          menuLinks.forEach(a => a.classList.remove("active"));
          link.classList.add("active");
        }
      });
    }, {
      root: null,
      rootMargin: "0px 0px -100% 0px",
      threshold: 0
    });

    sections.forEach(section => observer.observe(section.parentElement));
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
