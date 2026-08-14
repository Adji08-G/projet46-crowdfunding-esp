// Fermeture automatique des alertes flash après 5 secondes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert-dismissible').forEach((alerte) => {
        setTimeout(() => {
            const closeBtn = alerte.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });
});
