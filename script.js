document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const toggleButton = document.getElementById('light-mode-toggle');
    const fontSizeLinks = document.querySelectorAll('.font-size-link');
    toggleButton.addEventListener('click', () => {
        fetch('reader.php?mode=1')
            .then(() => {
                location.reload(); 
            });
    });
    fontSizeLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const fontSize = link.getAttribute('data-size');
            fetch(`reader.php?size=${fontSize}`)
                .then(() => {
                    location.reload(); 
                });
        });
    });
});
