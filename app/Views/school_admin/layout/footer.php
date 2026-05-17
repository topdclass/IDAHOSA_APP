    <script>
        function toggleSubmenu(id) {
            const menu = document.getElementById(id);
            const caret = menu.previousElementSibling.querySelector('.nav-caret');
            
            if (menu.classList.contains('open')) {
                menu.classList.remove('open');
                if(caret) {
                    caret.classList.remove('ph-caret-up');
                    caret.classList.add('ph-caret-down');
                }
            } else {
                menu.classList.add('open');
                if(caret) {
                    caret.classList.remove('ph-caret-down');
                    caret.classList.add('ph-caret-up');
                }
            }
        }
    </script>
</body>
</html>
