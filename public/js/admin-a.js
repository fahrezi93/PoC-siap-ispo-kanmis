function openEntryForm() {
    if (window.location.hash === '#tambah') {
        const formPanel = document.getElementById('tambah');
        if (formPanel instanceof HTMLDetailsElement) {
            formPanel.open = true;
        }
    }
}

openEntryForm();
window.addEventListener('hashchange', openEntryForm);
