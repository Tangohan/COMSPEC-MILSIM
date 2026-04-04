document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-demo-login]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      window.location.href = 'ressources/html/dashboard.html';
    });
  });

  document.querySelectorAll('[data-enlistment-form]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const result = document.querySelector('[data-enlistment-result]');
      if (result) {
        result.innerHTML = `
          <div class="notice">
            ENLISTMENT REQUEST REGISTERED — STATUS: RECRUIT / PENDING REVIEW<br>
            OPERATOR ID GENERATED: OPR-24017<br>
            SERVICE NUMBER GENERATED: JSOC-AVN-8874
          </div>`;
      }
      form.reset();
    });
  });
});
