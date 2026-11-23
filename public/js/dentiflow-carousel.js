const carousel = document.querySelector('[data-photo-carousel]');

if (carousel) {
    const photos = [...carousel.querySelectorAll('.hero-photos img')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let timer;

    function movePhoto() {
        photos[current].hidden = true;
        current = (current + 1) % photos.length;
        photos[current].hidden = false;
    }

    function updateRotation() {
        window.clearInterval(timer);
        if (!reducedMotion.matches && !document.hidden) {
            timer = window.setInterval(movePhoto, 6000);
        }
    }

    document.addEventListener('visibilitychange', updateRotation);
    reducedMotion.addEventListener('change', updateRotation);
    updateRotation();
}
