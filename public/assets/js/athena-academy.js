(() => {
  const root = document.documentElement;
  const button = document.querySelector('[data-language]');
  const apply = (lang) => {
    root.dataset.lang = lang;
    root.lang = lang;
    document.querySelectorAll('[data-fr][data-en]').forEach((node) => {
      node.textContent = node.dataset[lang];
    });
    button?.setAttribute('aria-label', lang === 'fr' ? 'Switch to English' : 'Passer en français');
    localStorage.setItem('athena-academy-language', lang);
  };
  apply(localStorage.getItem('athena-academy-language') === 'en' ? 'en' : 'fr');
  button?.addEventListener('click', () => apply(root.dataset.lang === 'fr' ? 'en' : 'fr'));

  const links = [...document.querySelectorAll('.academy-rail a[href^="#"]')];
  const sections = links.map((link) => document.querySelector(link.hash)).filter(Boolean);
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      links.forEach((link) => link.classList.toggle('is-active', link.hash === `#${entry.target.id}`));
    }), { rootMargin: '-25% 0px -65%' });
    sections.forEach((section) => observer.observe(section));
  }
})();
