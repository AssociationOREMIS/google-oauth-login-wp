document.addEventListener('DOMContentLoaded', function () {
    const toggleSecretButton = document.getElementById('toggle-secret');
    const secretField = document.getElementById('client_secret');

    if (toggleSecretButton && secretField) {
        toggleSecretButton.addEventListener('click', function () {
            if (secretField.type === 'password') {
                secretField.type = 'text';
                this.textContent = 'Masquer';
            } else {
                secretField.type = 'password';
                this.textContent = 'Afficher';
            }
        });
    }
});
