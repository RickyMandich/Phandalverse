import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    // Handle all collapse elements
    document.querySelectorAll('.embed-content').forEach(function (collapseElement) {
        const icon = collapseElement.previousElementSibling.querySelector('.collapse-icon');

        collapseElement.addEventListener('hide.bs.collapse', function () {
            icon.classList.remove('bi-caret-down-square');
            icon.classList.add('bi-caret-right-square');
        });

        collapseElement.addEventListener('show.bs.collapse', function () {
            icon.classList.remove('bi-caret-right-square');
            icon.classList.add('bi-caret-down-square');
        });
    });
});