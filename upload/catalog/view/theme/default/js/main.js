document.addEventListener('DOMContentLoaded', () => {
  tabs();
});

function tabs() {
  document.addEventListener('click', e => {
    const tabToggle = e.target.closest('.tabToggle');
    if (!tabToggle) return;
    e.preventDefault();
    const tabs          =  tabToggle.closest('.tabs');
    const tabContainers = tabs.querySelectorAll('.tabContent');
    const tabToggles    = tabs.querySelectorAll('.tabToggle');
    const tabContent    = document.querySelector(tabToggle.hash);

    tabContainers?.forEach(e => {e.classList.remove('active')});
    tabToggles?.forEach(e => {e.classList.remove('active')});

    tabContent.classList.add('active');
    tabToggle.classList.add('active');
    console.log(tabContent);
  });
}