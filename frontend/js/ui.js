/**
 * UI partagée : barre latérale, gardes d'authentification, alertes,
 * échappement HTML et helpers de formatage.
 */
const UI = {
    /** Échappement HTML — obligatoire pour toute donnée dynamique. */
    esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    },

    /** Texte multiligne sûr (échappé + sauts de ligne). */
    nl(value) {
        return this.esc(value).replace(/\n/g, '<br>');
    },

    money(v) {
        const n = Number(v || 0);
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' F CFA';
    },

    date(v) {
        if (!v) return '—';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return this.esc(v);
        return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },

    datetime(v) {
        if (!v) return '—';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return this.esc(v);
        return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },

    /** Paramètre de query string. */
    qs(name) {
        return new URLSearchParams(window.location.search).get(name);
    },

    /** Badges de statut. */
    badge(statut) {
        const map = {
            'en_attente': ['En attente', 'bg-warning text-dark'],
            'approuve': ['Approuvé', 'bg-success'],
            'refuse': ['Refusé', 'bg-danger'],
            'accepte': ['Accepté', 'bg-success'],
            'annule': ['Annulé', 'bg-secondary'],
            'termine': ['Terminé', 'bg-secondary'],
            'paye': ['Payé', 'bg-success'],
            'echec': ['Échec', 'bg-danger'],
            'En attente': ['En attente', 'bg-secondary'],
            'En cours': ['En cours', 'bg-info text-dark'],
            'Livré': ['Livré', 'bg-success']
        };
        const [label, cls] = map[statut] || [statut || '—', 'bg-light text-dark'];
        return `<span class="badge ${cls}">${this.esc(label)}</span>`;
    },

    /** Image ou placeholder. */
    img(url, alt, cls = 'rounded') {
        const ph = '/assets/img/placeholder.svg';
        return url
            ? `<img src="${this.esc(url)}" alt="${this.esc(alt || '')}" class="${cls}" onerror="this.onerror=null;this.src='${ph}';">`
            : `<img src="${ph}" alt="${this.esc(alt || '')}" class="${cls}">`;
    },

    /** Notification flottante. */
    toast(message, type = 'success') {
        let zone = document.getElementById('toast-zone');
        if (!zone) {
            zone = document.createElement('div');
            zone.id = 'toast-zone';
            document.body.appendChild(zone);
        }
        const el = document.createElement('div');
        el.className = 'toast-item ' + (type === 'error' ? 'toast-error' : type === 'info' ? 'toast-info' : 'toast-success');
        el.textContent = message;
        zone.appendChild(el);
        setTimeout(() => { el.classList.add('hide'); setTimeout(() => el.remove(), 400); }, 4000);
    },

    /** Message d'erreur dans un conteneur. */
    error(target, message) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (el) el.innerHTML = `<div class="alert alert-danger">${this.esc(message)}</div>`;
    },

    /** Demande de confirmation. */
    confirm(message) {
        return window.confirm(message);
    },

    // --- Authentification ---

    /** Redirige vers login si non connecté. Retourne le profil ou null. */
    async requireUser() {
        if (!Auth.isLoggedIn()) {
            window.location.href = 'login.html';
            return null;
        }
        try {
            return await api.get('/api/auth/me');
        } catch (e) {
            if (e.status === 401) { Auth.clear(); window.location.href = 'login.html'; }
            else this.toast(e.message, 'error');
            return null;
        }
    },

    /** Redirige vers login admin si pas administrateur. */
    async requireAdmin() {
        if (!Auth.isLoggedIn()) {
            window.location.href = 'login.html';
            return null;
        }
        try {
            const me = await api.get('/api/auth/me');
            if (me.role !== 'admin') {
                document.body.innerHTML = '<div class="p-5 text-center"><h3>Accès réservé aux administrateurs</h3>'
                    + '<a class="btn btn-primary mt-3" href="../index.html">Retour à l\'accueil</a></div>';
                return null;
            }
            return me;
        } catch (e) {
            Auth.clear();
            window.location.href = 'login.html';
            return null;
        }
    },

    async logout() {
        try { await api.post('/api/auth/logout'); } catch (e) { /* le jeton est effacé quoi qu'il arrive */ }
        Auth.clear();
        window.location.href = 'login.html';
    },

    /** Barre latérale utilisateur. `active` = nom de la page courante. */
    async renderSidebar(active = '') {
        const host = document.getElementById('sidebar');
        if (!host) return null;

        const me = await this.requireUser();
        if (!me) return null;

        const link = (page, icon, label, extra = '') =>
            `<li><a href="${page}" class="${active === page ? 'active' : ''}">${extra}
                <i class="fas ${icon}"></i> ${label}</a></li>`;

        host.innerHTML = `
        <div class="vertical-menu">
            <div class="logo-container">
                <a href="index.html"><img src="assets/img/logo.jpeg" alt="Logo" class="logo"></a>
            </div>
            <div class="user-box">
                ${me.photo_url ? `<img src="${this.esc(me.photo_url)}" alt="" class="user-avatar">` : ''}
                <div class="user-name">${this.esc(me.prenom)} ${this.esc(me.nom)}</div>
                <span class="badge bg-light text-dark">${this.esc(me.role)}</span>
            </div>
            <ul class="menu-items">
                ${link('dashboard.html', 'fa-home', 'Accueil')}
                ${link('poster-colis.html', 'fa-box', 'Postez Colis')}
                ${link('profil.html', 'fa-user', 'Profil')}
                ${link('liste-messagerie.html', 'fa-envelope', 'Messages')}
                ${link('messagerie-admin.html', 'fa-headset', "Contacter l'admin")}
                ${link('devenir-transporteur.html', 'fa-id-badge', 'Devenir Transporteur')}
                ${link('colis.html', 'fa-box-open', 'Colis disponibles')}
                ${link('suivi.html', 'fa-search-location', 'Suivi')}
                ${link('recherche.html', 'fa-magnifying-glass', 'Recherche')}
                ${me.is_transporteur ? link('transporteur-stats.html', 'fa-chart-line', 'Mes statistiques') : ''}
                ${link('reponses.html', 'fa-reply', 'Mes réponses')}
                ${link('contact.html', 'fa-phone-alt', 'Contact')}
                ${me.role === 'admin' ? link('admin/index.html', 'fa-shield-halved', 'Administration') : ''}
                <li><a href="#" id="logout-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </div>`;

        document.getElementById('logout-link').addEventListener('click', (e) => {
            e.preventDefault();
            this.logout();
        });
        return me;
    }
};
