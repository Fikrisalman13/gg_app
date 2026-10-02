(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var body = document.body;
    if (!body) return;
    var theme = body.getAttribute('data-theme') || '';
    if (!theme && window.ticketThemeName) {
      theme = window.ticketThemeName;
      body.setAttribute('data-theme', theme);
    }
    if (!theme) return;
    var darkThemes = ['light','warning','lime','orange'];
    if (darkThemes.indexOf(theme) === -1) {
      body.classList.add('ticket-theme-dark');
      return;
    }
    body.classList.add('ticket-theme-light');
  });
})();
