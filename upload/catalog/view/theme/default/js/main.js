document.addEventListener('DOMContentLoaded', () => {
  tabs();
});

function tabs() {
  document.addEventListener('click', e => {
    const tabToggle = e.target.closest('.tabToggle');
    if (!tabToggle) return;
    e.preventDefault();
    const tabs          = tabToggle.closest('.tabs');
    const tabContainers = tabs.querySelectorAll('.tabContent');
    const tabToggles    = tabs.querySelectorAll('.tabToggle');
    const tabContent    = tabs.querySelector(tabToggle.hash);

    tabContainers.forEach(e => {e.classList.remove('active'); e.setAttribute('aria-hidden', 'true');});
    tabToggles.forEach(e => {e.classList.remove('active'); e.setAttribute('aria-selected', 'false')});

    tabContent.classList.add('active');
    tabContent.setAttribute('aria-hidden', 'false');
    tabToggle.classList.add('active');
    tabToggle.setAttribute('aria-selected', 'true');
  });
}
}