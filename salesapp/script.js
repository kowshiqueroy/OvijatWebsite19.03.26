
        // Toggle Nested Table Row
        function toggleRow(icon) {
            const row = icon.closest('tr');
            const subRow = row.nextElementSibling;
            if(subRow && subRow.classList.contains('sub-row')){
                subRow.classList.toggle('open');
                // Rotate Icon
                if(subRow.classList.contains('open')){
                    icon.style.transform = "rotate(90deg)";
                } else {
                    icon.style.transform = "rotate(0deg)";
                }
            }
        }

        // Toggle Popup Menu
        function togglePopup() {
            const menu = document.getElementById('popupMenu');
            menu.style.display = (menu.style.display === 'flex') ? 'none' : 'flex';
        }

        // Close popup if clicked outside
        window.onclick = function(event) {
            const menu = document.getElementById('popupMenu');
            const trigger = document.querySelector('.nav-item-more');
            if (!trigger.contains(event.target) && !menu.contains(event.target) && menu.style.display === 'flex') {
                menu.style.display = 'none';
            }
        }
