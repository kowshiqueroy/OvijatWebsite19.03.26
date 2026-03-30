document.addEventListener('DOMContentLoaded', function () {
    const menuIcon = document.querySelector('.menu-icon');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('main');

    menuIcon.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('open');
    });

    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const row = e.target.closest('tr');
            const id = row.cells[0].textContent;
            console.log(`Editing user with ID: ${id}`);
            // You can add your edit logic here, like opening a modal
        });
    });
});
