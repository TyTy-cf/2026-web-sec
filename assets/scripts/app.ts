window.addEventListener('load', () => {
    const banner = document.getElementById('ref-banner');

    if (!banner) {
        return;
    }

    const hashParams = new URLSearchParams(window.location.hash.slice(1));
    const ref: string|null = hashParams.get('ref');

    if (ref) {
        banner.innerHTML = `<strong>${ref}</strong> pense que cette catégorie va te plaire !`;
    }
});
